<?php

declare(strict_types=1);
namespace App\Service;

use App\Entity\{BarCashEntry, BarCashSession, BarDutyAssignment, BarDutyPolicy, BarShift, Member};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class BarCashWorkflow
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly BarDutyWorkflow $duty, private readonly BarDutyAccess $access) {}

    public function canUse(BarShift $shift, bool $management = true): bool
    {
        $member = $this->access->member();
        if ($this->em->getConnection()->isTransactionActive()) {
            $member = $this->duty->locked(Member::class, $member->getId());
        }
        if ($shift->getClub()->getId() !== $member->getClub()?->getId()) { return false; }
        if ($management && $this->access->isManager()) { return true; }
        if (!$member->isActiveMember() || $shift->getStatus() === 'cancelled') { return false; }
        $criteria = ['shift' => $shift, 'member' => $member, 'status' => 'active'];
        $assignment = $this->em->getConnection()->isTransactionActive()
            ? $this->duty->one(BarDutyAssignment::class, $criteria)
            : $this->em->getRepository(BarDutyAssignment::class)->findOneBy($criteria);
        return $assignment !== null && $assignment->getAttendance() !== 'absent';
    }

    public function requireAccess(BarShift $shift, bool $management = true): void
    {
        if (!$this->canUse($shift, $management)) {
            throw new AccessDeniedException('Thekenkasse nur für die aktive Einteilung dieser Schicht oder die Clubheimverwaltung.');
        }
    }

    public function open(int $shiftId, string $amount, string $reason, bool $management = true): void
    {
        BarDutyWorkflow::reason($reason); $cents = self::cents($amount);
        $this->duty->transaction(function () use ($shiftId, $cents, $reason, $management): void {
            $shift = $this->duty->shift($shiftId);
            $this->requireAccess($shift, $management);
            $policy = $this->duty->one(BarDutyPolicy::class, ['club' => $shift->getClub(), 'year' => (int) $shift->getStartsAt()->format('Y')]);
            if (!$policy?->isCashEnabled()) { throw new \DomainException('Separate Thekenkasse ist für dieses Jahr nicht aktiviert.'); }
            if ($shift->getStatus() === 'cancelled' || $this->duty->one(BarCashSession::class, ['shift' => $shift]) !== null) { throw new \DomainException('Schicht ist abgesagt oder besitzt bereits eine Kasse.'); }
            $this->em->persist(new BarCashSession($shift, $cents, $this->access->actor(), trim($reason)));
        });
    }

    public function book(int $sessionId, string $direction, string $amount, string $reason, string $reference, bool $management = true): void
    {
        $cents = self::cents($amount);
        if (!in_array($direction, ['in', 'out'], true) || $cents <= 0) { throw new \DomainException('Eingang oder Ausgang mit positivem Betrag erforderlich.'); }
        $this->duty->transaction(function () use ($sessionId, $direction, $cents, $reason, $reference, $management): void {
            $session = $this->session($sessionId, $management);
            $signed = $direction === 'out' ? -$cents : $cents;
            $balance = $this->balance($session) + $signed;
            if ($balance < 0) { throw new \DomainException('Nicht genug Bargeld in dieser Thekenkasse.'); }
            if ($balance > 999999999) { throw new \DomainException('Maximalen Thekenkassenbestand überschritten.'); }
            $this->em->persist(new BarCashEntry($session, $signed, $reason, $reference, $this->access->actor()));
        });
    }

    public function reverse(int $sessionId, int $entryId, string $reason, bool $management = true): void
    {
        BarDutyWorkflow::reason($reason);
        $this->duty->transaction(function () use ($sessionId, $entryId, $reason, $management): void {
            $session = $this->session($sessionId, $management); $entry = $this->duty->locked(BarCashEntry::class, $entryId);
            if ($entry->getSession() !== $session || $entry->getReversalOf() !== null || $this->duty->one(BarCashEntry::class, ['reversalOf' => $entry]) !== null) { throw new \DomainException('Beleg gehört nicht zur Kasse oder wurde bereits korrigiert.'); }
            $balance = $this->balance($session) - $entry->getAmountCents();
            if ($balance < 0 || $balance > 999999999) { throw new \DomainException('Korrektur überschreitet den zulässigen Kassenbestand.'); }
            $this->em->persist(new BarCashEntry($session, -$entry->getAmountCents(), $reason, 'Korrektur #'.$entryId, $this->access->actor(), $entry));
        });
    }

    public function close(int $sessionId, string $counted, string $reason, bool $management = true): void
    {
        if (mb_strlen($reason) > 500) { throw new \DomainException('Abschlussnotiz ist zu lang.'); }
        $cents = self::cents($counted);
        $this->duty->transaction(function () use ($sessionId, $cents, $reason, $management): void {
            $session = $this->session($sessionId, $management);
            $entries = $this->entries($session);
            $evidence = array_map(static fn (BarCashEntry $e): string => $e->getId().':'.$e->getAmountCents().':'.$e->getCreatedAt()->format(DATE_ATOM), $entries);
            $session->close($this->balance($session, $entries), $cents, trim($reason), hash('sha256', implode("\n", $evidence)), $this->access->actor());
        });
    }
    private function session(int $id, bool $management): BarCashSession
    {
        $session = $this->duty->locked(BarCashSession::class, $id); $this->access->ownsClub($session->getShift()->getClub());
        $shift = $this->duty->shift($session->getShift()->getId());
        $this->requireAccess($shift, $management);
        if ($shift->getStatus() === 'cancelled') { throw new \DomainException('Schicht ist abgesagt.'); }
        if ($session->getClosedAt() !== null) { throw new \DomainException('Thekenkasse ist unveränderlich abgeschlossen.'); }
        return $session;
    }
    public function entries(BarCashSession $session): array
    {
        $query = $this->em->getRepository(BarCashEntry::class)->createQueryBuilder('e')->where('e.session = :session')->setParameter('session', $session)->orderBy('e.id', 'ASC')->getQuery();
        return $this->em->getConnection()->isTransactionActive() ? $this->duty->current($query) : $query->getResult();
    }
    public function balance(BarCashSession $session, ?array $entries = null): int
    {
        return $session->getOpeningCents() + array_sum(array_map(static fn (BarCashEntry $e): int => $e->getAmountCents(), $entries ?? $this->entries($session)));
    }
    public static function cents(string $raw): int
    {
        $cents = TuvPriceCalculator::cents($raw);
        if ($cents === null || $cents > 999999999) { throw new \DomainException('Geldbetrag ausdrücklich und mit maximal zwei Nachkommastellen angeben.'); }
        return $cents;
    }
}
