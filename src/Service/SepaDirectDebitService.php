<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Club;
use App\Entity\MembershipCharge;
use App\Entity\MembershipPolicy;
use App\Entity\SepaDirectDebitBatch;
use App\Entity\SepaDirectDebitItem;
use App\Entity\SepaMandate;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use Doctrine\ORM\EntityManagerInterface;

final class SepaDirectDebitService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MembershipFinanceService $finance,
        private readonly AuditLogger $audit,
        private readonly ModuleAccess $access,
        private readonly ProductionReadiness $readiness,
    ) {}

    /** @param list<int> $chargeIds */
    public function create(Club $club, User $creator, string $batchKey, \DateTimeImmutable $collectionDate, array $chargeIds): SepaDirectDebitBatch
    {
        if (!$this->access->can($creator, $club, PermissionModule::FINANCE, PermissionAction::CREATE)) { throw new \DomainException('Keine Berechtigung zum Erstellen.'); }
        $batchKey = trim($batchKey);
        $existing = $this->em->getRepository(SepaDirectDebitBatch::class)->findOneBy(['club' => $club, 'batchKey' => $batchKey]);
        if ($existing !== null) { throw new \DomainException('Ein Lastschriftlauf mit diesem Schlüssel existiert bereits.'); }
        if ($collectionDate < $this->secondBankWorkingDay()) {
            throw new \DomainException('Das Einzugsdatum muss mindestens zwei Bankarbeitstage im Voraus liegen.');
        }
        $charges = $this->loadCharges($club, $chargeIds);
        $alreadyReserved = (int) $this->em->getRepository(SepaDirectDebitItem::class)->createQueryBuilder('item')
            ->select('COUNT(item.id)')
            ->join('item.batch', 'batch')
            ->where('item.charge IN (:charges)')
            ->andWhere('batch.club = :club')
            ->andWhere('batch.status IN (:statuses)')
            ->setParameter('charges', $charges)
            ->setParameter('club', $club)
            ->setParameter('statuses', [
                SepaDirectDebitBatch::STATUS_DRAFT,
                SepaDirectDebitBatch::STATUS_APPROVED,
                SepaDirectDebitBatch::STATUS_EXPORTED,
            ])
            ->getQuery()->getSingleScalarResult();
        if ($alreadyReserved > 0) {
            throw new \DomainException('Mindestens eine Forderung ist bereits in einem aktiven oder exportierten Lastschriftlauf enthalten.');
        }
        $batch = new SepaDirectDebitBatch($club, $creator, $batchKey, $collectionDate);
        foreach ($charges as $charge) {
            $mandates = $this->em->getRepository(SepaMandate::class)->findBy(['member' => $charge->getMember()], ['id' => 'DESC']);
            $mandate = array_values(array_filter($mandates, static fn (SepaMandate $item): bool => $item->getRevokedAt() === null))[0] ?? null;
            if (!$mandate instanceof SepaMandate) {
                throw new \DomainException('Für Rechnung '.$charge->getInvoiceNumber().' fehlt ein aktives SEPA-Mandat.');
            }
            $batch->addItem(new SepaDirectDebitItem($charge, $mandate, $charge->getOutstandingCents()));
        }
        $this->em->persist($batch);
        $this->audit->record($club, $creator->getUserIdentifier(), 'sepa.batch.created', $batch, [
            'chargeIds' => array_map(static fn (MembershipCharge $charge): int => (int) $charge->getId(), $charges),
            'amountCents' => $batch->getTotalCents(),
        ]);
        return $batch;
    }

    public function approve(SepaDirectDebitBatch $batch, User $approver): void
    {
        if (!$this->access->can($approver, $batch->getClub(), PermissionModule::FINANCE, PermissionAction::EDIT)) { throw new \DomainException('Keine Freigabeberechtigung.'); }
        foreach ($batch->getItems() as $item) {
            $charge = $this->em->find(MembershipCharge::class, $item->getCharge()->getId());
            if (!$charge instanceof MembershipCharge || $charge->getOutstandingCents() < $item->getAmountCents()
                || $item->getMandate()->getRevokedAt() !== null) {
                throw new \DomainException('Lastschriftlauf ist seit Erstellung verändert oder das Mandat widerrufen.');
            }
        }
        $batch->approve($approver);
        $this->audit->record($batch->getClub(), $approver->getUserIdentifier(), 'sepa.batch.approved', $batch, ['amountCents' => $batch->getTotalCents()]);
    }

    public function export(SepaDirectDebitBatch $batch, User $exporter): array
    {
        $this->readiness->requireApproved($batch->getClub(), 'sepa');
        if (!$this->access->can($exporter, $batch->getClub(), PermissionModule::FINANCE, PermissionAction::EDIT)) { throw new \DomainException('Keine Exportberechtigung.'); }
        if ($batch->getStatus() === SepaDirectDebitBatch::STATUS_EXPORTED) {
            $xml = $this->xml($batch);
            return ['xml' => $xml, 'sha256' => hash('sha256', $xml)];
        }
        $xml = $this->xml($batch);
        $hash = hash('sha256', $xml);
        $batch->markExported($hash);
        $this->audit->record($batch->getClub(), $exporter->getUserIdentifier(), 'sepa.batch.exported', $batch, ['sha256' => $hash]);
        return ['xml' => $xml, 'sha256' => $hash];
    }

    private function xml(SepaDirectDebitBatch $batch): string
    {
        $policy = $this->em->getRepository(MembershipPolicy::class)->findOneBy(['club' => $batch->getClub()]);
        if (!$policy instanceof MembershipPolicy || !$policy->getCreditorIdentifier() || !$policy->getSepaCreditorName()
            || !$policy->getSepaCreditorIban() || !$policy->getSepaCreditorBic()) {
            throw new \DomainException('SEPA-Gläubigerdaten sind unvollständig.');
        }
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $ns = 'urn:iso:std:iso:20022:tech:xsd:pain.008.001.02';
        $document = $dom->createElementNS($ns, 'Document');
        $dom->appendChild($document);
        $transfer = $this->child($dom, $document, $ns, 'CstmrDrctDbtInitn');
        $header = $this->child($dom, $transfer, $ns, 'GrpHdr');
        $this->text($dom, $header, $ns, 'MsgId', 'DICOMA-'.$batch->getId().'-'.$batch->getBatchKey());
        $this->text($dom, $header, $ns, 'CreDtTm', $batch->getCreatedAt()->format(DATE_ATOM));
        $this->text($dom, $header, $ns, 'NbOfTxs', (string) count($batch->getItems()));
        $this->text($dom, $header, $ns, 'CtrlSum', number_format($batch->getTotalCents() / 100, 2, '.', ''));
        $party = $this->child($dom, $header, $ns, 'InitgPty');
        $this->text($dom, $party, $ns, 'Nm', (string) $batch->getClub()->getName());
        $paymentInfo = $this->child($dom, $transfer, $ns, 'PmtInf');
        $this->text($dom, $paymentInfo, $ns, 'PmtInfId', 'PI-'.$batch->getId());
        $this->text($dom, $paymentInfo, $ns, 'PmtMtd', 'DD');
        $this->text($dom, $paymentInfo, $ns, 'NbOfTxs', (string) count($batch->getItems()));
        $this->text($dom, $paymentInfo, $ns, 'CtrlSum', number_format($batch->getTotalCents() / 100, 2, '.', ''));
        $paymentType = $this->child($dom, $paymentInfo, $ns, 'PmtTpInf');
        $svcLvl = $this->child($dom, $paymentType, $ns, 'SvcLvl');
        $this->text($dom, $svcLvl, $ns, 'Cd', 'SEPA');
        $localInstrument = $this->child($dom, $paymentType, $ns, 'LclInstrm');
        $this->text($dom, $localInstrument, $ns, 'Cd', 'CORE');
        $this->text($dom, $paymentType, $ns, 'SeqTp', 'RCUR');
        $this->text($dom, $paymentInfo, $ns, 'ReqdColltnDt', $batch->getCollectionDate()->format('Y-m-d'));
        $creditor = $this->child($dom, $paymentInfo, $ns, 'Cdtr');
        $this->text($dom, $creditor, $ns, 'Nm', $policy->getSepaCreditorName());
        $creditorAccount = $this->child($dom, $paymentInfo, $ns, 'CdtrAcct');
        $creditorAccountId = $this->child($dom, $creditorAccount, $ns, 'Id');
        $this->text($dom, $creditorAccountId, $ns, 'IBAN', $policy->getSepaCreditorIban());
        $creditorAgent = $this->child($dom, $paymentInfo, $ns, 'CdtrAgt');
        $creditorFin = $this->child($dom, $creditorAgent, $ns, 'FinInstnId');
        $this->text($dom, $creditorFin, $ns, 'BIC', $policy->getSepaCreditorBic());
        $scheme = $this->child($dom, $paymentInfo, $ns, 'CdtrSchmeId');
        $schemeId = $this->child($dom, $scheme, $ns, 'Id');
        $schemePrivate = $this->child($dom, $schemeId, $ns, 'PrvtId');
        $schemeOther = $this->child($dom, $schemePrivate, $ns, 'Othr');
        $this->text($dom, $schemeOther, $ns, 'Id', $policy->getCreditorIdentifier());
        $schemeType = $this->child($dom, $schemeOther, $ns, 'SchmeNm');
        $this->text($dom, $schemeType, $ns, 'Prtry', 'SEPA');

        foreach ($batch->getItems() as $index => $item) {
            $instruction = $this->child($dom, $paymentInfo, $ns, 'DrctDbtTxInf');
            $instructionId = $this->child($dom, $instruction, $ns, 'PmtId');
            $this->text($dom, $instructionId, $ns, 'EndToEndId', 'DICOMA-'.$batch->getId().'-'.($index + 1));
            $amount = $this->child($dom, $instruction, $ns, 'InstdAmt', 'Ccy', 'EUR');
            $amount->appendChild($dom->createTextNode(number_format($item->getAmountCents() / 100, 2, '.', '')));
            $directDebit = $this->child($dom, $instruction, $ns, 'DrctDbtTx');
            $mandateRelated = $this->child($dom, $directDebit, $ns, 'MndtRltdInf');
            $this->text($dom, $mandateRelated, $ns, 'MndtId', $item->getMandate()->getReference());
            $this->text($dom, $mandateRelated, $ns, 'DtOfSgntr', $item->getMandate()->getSignedAt()->format('Y-m-d'));
            $debtorAgent = $this->child($dom, $instruction, $ns, 'DbtrAgt');
            $debtorFin = $this->child($dom, $debtorAgent, $ns, 'FinInstnId');
            $bic = $this->finance->decryptBic($item->getMandate());
            if ($bic !== null) { $this->text($dom, $debtorFin, $ns, 'BIC', $bic); }
            $debtor = $this->child($dom, $instruction, $ns, 'Dbtr');
            $this->text($dom, $debtor, $ns, 'Nm', $item->getMandate()->getHolder());
            $debtorAccount = $this->child($dom, $instruction, $ns, 'DbtrAcct');
            $debtorAccountId = $this->child($dom, $debtorAccount, $ns, 'Id');
            $this->text($dom, $debtorAccountId, $ns, 'IBAN', $this->finance->decryptIban($item->getMandate()));
            $remittance = $this->child($dom, $instruction, $ns, 'RmtInf');
            $this->text($dom, $remittance, $ns, 'Ustrd', mb_substr((string) $item->getCharge()->getInvoiceNumber().' '.$item->getCharge()->getDescription(), 0, 140));
        }
        $xml = $dom->saveXML();
        if ($xml === false) { throw new \RuntimeException('pain.008 konnte nicht erzeugt werden.'); }
        return $xml;
    }

    /** @return list<MembershipCharge> */
    private function loadCharges(Club $club, array $chargeIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $chargeIds)));
        if ($ids === [] || count($ids) !== count($chargeIds)) { throw new \DomainException('Mindestens eine eindeutige Forderung auswählen.'); }
        $charges = $this->em->getRepository(MembershipCharge::class)->createQueryBuilder('charge')
            ->join('charge.member', 'm')->addSelect('m')
            ->where('charge.id IN (:ids)')->andWhere('m.club = :club')
            ->andWhere('charge.amountCents > charge.paidCents')
            ->setParameter('ids', $ids)->setParameter('club', $club)
            ->getQuery()->getResult();
        if (count($charges) !== count($ids)) { throw new \DomainException('Auswahl enthält fremde, bezahlte oder unbekannte Forderungen.'); }
        return $charges;
    }

    private function child(\DOMDocument $dom, \DOMElement $parent, string $ns, string $name, ?string $attribute = null, ?string $attributeValue = null): \DOMElement
    {
        $child = $dom->createElementNS($ns, $name);
        if ($attribute !== null && $attributeValue !== null) { $child->setAttribute($attribute, $attributeValue); }
        $parent->appendChild($child);
        return $child;
    }
    private function secondBankWorkingDay(): \DateTimeImmutable
    {
        $date = new \DateTimeImmutable('today');
        $workingDays = 0;
        while ($workingDays < 2) {
            $date = $date->modify('+1 day');
            if (!in_array((int) $date->format('N'), [6, 7], true)) {
                ++$workingDays;
            }
        }
        return $date;
    }
    private function text(\DOMDocument $dom, \DOMElement $parent, string $ns, string $name, string $value): void
    {
        $this->child($dom, $parent, $ns, $name)->appendChild($dom->createTextNode($value));
    }
}
