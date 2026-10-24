<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContributionClass;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MembershipCharge;
use App\Entity\MembershipPayment;
use App\Entity\SepaMandate;
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class MembershipFinanceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MembershipService $memberships,
        private readonly ValidatorInterface $validator,
        private readonly PrivateMemberDocumentStorage $documents,
    ) {}

    public function assign(Member $member, ?ContributionClass $class, ?Member $payer, int $discount, ?string $reason, string $actor): void
    {
        $clubId = $member->getClub()?->getId();
        if ($clubId === null || ($class !== null && ($class->getClub()->getId() !== $clubId || !$class->isActive()))
            || $discount < 0 || $discount > ($class?->getAnnualCents() ?? 0)
            || ($discount > 0 && trim((string) $reason) === '') || mb_strlen((string) $reason) > 500) {
            throw new \DomainException('Ungültige Beitragsklasse oder Ermäßigung. Bei Ermäßigung ist eine Begründung erforderlich.');
        }
        if ($payer !== null && ($payer === $member || $payer->getId() === $member->getId()
            || $payer->getClub()?->getId() !== $clubId || $payer->getFamilyPayer() !== null
            || !$payer->getContributionClass()?->isFamily() || $payer->getArchivedAt() !== null
            || $payer->getMembershipStatus() === MembershipStatus::FORMER || $class !== null || $discount !== 0)) {
            throw new \DomainException('Familienmitglieder benötigen einen eigenständigen Familienzahler im selben Verein und keine eigene Beitragsklasse/Ermäßigung.');
        }
        $dependants = $this->em->getRepository(Member::class)->count(['familyPayer' => $member]);
        if ($dependants > 0 && ($payer !== null || !$class?->isFamily())) {
            throw new \DomainException('Zuerst die abhängigen Familienmitglieder neu zuordnen.');
        }
        $this->em->wrapInTransaction(function () use ($member, $class, $payer, $discount, $reason, $actor): void {
            $member->setContributionClass($class);
            $member->setFamilyPayer($payer);
            $member->setContributionDiscountCents($discount);
            $member->setContributionDiscountReason($reason);
            $this->memberships->record($member, 'contribution_changed', $actor, 'Beitragszuordnung oder Ermäßigung durch die Verwaltung geändert.');
        });
    }

    public function annual(Member $member, int $year, \DateTimeImmutable $due, string $actor): MembershipCharge
    {
        if ($year < 2000 || $year > 2200 || $member->getClub() === null || $member->getArchivedAt() !== null
            || $member->getMembershipStatus() === MembershipStatus::FORMER
            || ($member->getJoinedAt() !== null && (int) $member->getJoinedAt()->format('Y') > $year)) {
            throw new \DomainException('Ungültiges Beitragsjahr oder keine beitragsfähige Mitgliedschaft.');
        }
        if ($member->getFamilyPayer() !== null) {
            throw new \DomainException('Familienbeitrag ausschließlich beim zahlungsverantwortlichen Mitglied erheben.');
        }
        $class = $member->getContributionClass();
        if ($class === null || !$class->isActive() || $class->getClub()->getId() !== $member->getClub()->getId()) {
            throw new \DomainException('Keine gültige aktive Beitragsklasse zugeordnet.');
        }
        $key = 'annual:' . $member->getId() . ':' . $year;
        if ($this->em->getRepository(MembershipCharge::class)->findOneBy(['chargeKey' => $key]) !== null) {
            throw new \DomainException('Der Jahresbeitrag wurde bereits erhoben; keine doppelte Forderung erstellt.');
        }
        $amount = $class->getAnnualCents() - $member->getContributionDiscountCents();
        return $this->issue($member, $key, ($class->isFamily() ? 'Familienbeitrag ' : 'Mitgliedsbeitrag ') . $year . ' – ' . $class->getName(), $amount, $due, $actor);
    }

    public function special(Member $member, string $description, int $cents, \DateTimeImmutable $due, string $actor): MembershipCharge
    {
        if ($cents <= 0) { throw new \DomainException('Sonderbeitrag muss größer als null sein.'); }
        return $this->issue($member, 'special:' . bin2hex(random_bytes(16)), $description, $cents, $due, $actor);
    }

    private function issue(Member $member, string $key, string $description, int $cents, \DateTimeImmutable $due, string $actor): MembershipCharge
    {
        return $this->em->wrapInTransaction(function () use ($member, $key, $description, $cents, $due, $actor): MembershipCharge {
            $charge = new MembershipCharge($member, $key, $description, $cents, $due);
            $this->em->persist($charge);
            $this->memberships->record($member, 'charge_created', $actor, $description . ': ' . number_format($cents / 100, 2, ',', '.') . ' EUR, fällig am ' . $due->format('d.m.Y'));
            return $charge;
        });
    }

    public function pay(MembershipCharge $charge, int $cents, string $reference, string $actor): void
    {
        if ($cents <= 0 || trim($reference) === '' || mb_strlen($reference) > 180) {
            throw new \DomainException('Positiver Zahlungsbetrag und Zahlungsreferenz erforderlich.');
        }
        $this->em->wrapInTransaction(function () use ($charge, $cents, $reference, $actor): void {
            $changed = $this->em->getConnection()->executeStatement(
                'UPDATE membership_charge SET paid_cents = paid_cents + :amount WHERE id = :id AND amount_cents - paid_cents >= :amount',
                ['amount' => $cents, 'id' => $charge->getId()],
                ['amount' => \Doctrine\DBAL\ParameterType::INTEGER, 'id' => \Doctrine\DBAL\ParameterType::INTEGER],
            );
            if ($changed !== 1) { throw new \DomainException('Zahlung übersteigt die offene Forderung oder Forderung nicht vorhanden.'); }
            $this->em->refresh($charge);
            $this->em->persist(new MembershipPayment($charge, $cents, trim($reference), $actor));
            $this->memberships->record($charge->getMember(), 'payment_recorded', $actor, 'Beitragszahlung über ' . number_format($cents / 100, 2, ',', '.') . ' EUR verbucht.');
        });
    }

    public function createMandate(Member $member, string $reference, string $holder, string $iban, \DateTimeImmutable $signed, MemberDocument $proof, string $actor): void
    {
        $iban = strtoupper(str_replace(' ', '', $iban));
        $creditor = $member->getClub() !== null ? $this->memberships->policy($member->getClub())?->getCreditorIdentifier() : null;
        if (!$creditor || !preg_match('/^[A-Za-z0-9\-]{1,35}$/D', $reference)
            || trim($holder) === '' || mb_strlen($holder) > 180 || $signed > new \DateTimeImmutable('today')
            || $proof->getMember()?->getId() !== $member->getId()
            || !is_file($this->documents->path($proof->getStorageName()))
            || count($this->validator->validate($iban, new Assert\Iban())) !== 0) {
            throw new \DomainException('Mandatsdaten ungültig: Gläubiger-ID, Referenz, Kontoinhaber, gültige IBAN, Unterschriftsdatum und eigener Nachweis erforderlich.');
        }
        if ($this->em->getRepository(SepaMandate::class)->findOneBy(['club' => $member->getClub(), 'reference' => $reference]) !== null) {
            throw new \DomainException('Diese Mandatsreferenz ist im Verein bereits vergeben.');
        }
        $key = $this->bankKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = base64_encode($nonce . sodium_crypto_secretbox($iban, $nonce, $key));
        $this->em->wrapInTransaction(function () use ($member, $reference, $holder, $iban, $signed, $encrypted, $creditor, $actor, $proof): void {
            $mandate = new SepaMandate($member, $reference, $creditor, $holder, $encrypted, substr($iban, -4), $signed);
            $mandate->setDocument($proof);
            $this->em->persist($mandate);
            $this->memberships->record($member, 'sepa_created', $actor, 'SEPA-Mandat angelegt. Es wurde kein Bankeinzug ausgelöst.');
        });
    }

    public function decryptIban(SepaMandate $mandate): string
    {
        $key = $this->bankKey();
        $binary = base64_decode($mandate->getEncryptedIban(), true);
        if ($binary === false || strlen($binary) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Beschädigte verschlüsselte Mandatsdaten.');
        }
        $plaintext = sodium_crypto_secretbox_open(substr($binary, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($binary, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
        if ($plaintext === false) { throw new \RuntimeException('Mandatsdaten können mit dem konfigurierten Schlüssel nicht entschlüsselt werden.'); }
        return $plaintext;
    }

    private function bankKey(): string
    {
        if (!extension_loaded('sodium')) { throw new \DomainException('SEPA-Verschlüsselung erfordert die PHP-Erweiterung sodium.'); }
        $encoded = $_SERVER['DICOMA_MEMBERSHIP_BANK_KEY'] ?? $_ENV['DICOMA_MEMBERSHIP_BANK_KEY'] ?? getenv('DICOMA_MEMBERSHIP_BANK_KEY');
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \DomainException('DICOMA_MEMBERSHIP_BANK_KEY muss als geheimer Base64-Schlüssel mit 32 Bytes konfiguriert sein. Kein Mandat wurde gespeichert.');
        }
        return $key;
    }
}
