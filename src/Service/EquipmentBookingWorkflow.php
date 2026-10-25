<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentBooking;
use App\Entity\Club;
use App\Entity\EquipmentRentalReceipt;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentBookingWorkflow
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipmentAssetAvailability $availability,
        private readonly EquipmentMaintenanceService $maintenance,
    ) {}

    public function approve(int $id, int $clubId, string $actor): void
    {
        $this->em->wrapInTransaction(function () use ($id, $clubId, $actor): void {
            $booking = $this->booking($id, $clubId);
            if ($booking->getStatus() !== 'reserved' || $booking->getApprovedAt() !== null || $booking->getItems()->isEmpty()) {
                throw new \DomainException('Nur offene Reservierungen können genehmigt werden.');
            }
            foreach ($booking->getItems() as $line) {
                $asset = $line->getEquipmentItem() ?? $line->getRegulator() ?? $line->getTank();
                if (!$asset || !$booking->getStartsAt() || !$booking->getEndsAt()) { throw new \DomainException('Buchung ist unvollständig.'); }
                $this->availability->assertAvailable($asset, $clubId, $booking->getStartsAt(), $booking->getEndsAt(), $id);
            }
            $booking->approve($actor);
        });
    }

    public function close(int $id, int $clubId, string $actor): void
    {
        $this->em->wrapInTransaction(function () use ($id, $clubId, $actor): void {
            $this->em->lock($this->em->getReference(Club::class, $clubId), LockMode::PESSIMISTIC_WRITE);
            $booking = $this->booking($id, $clubId);
            if ($booking->getStatus() !== 'returned' || $booking->getItems()->isEmpty()) { throw new \DomainException('Zuerst alle Gegenstände zurücknehmen.'); }
            $fee = $booking->getAgreedRentalFeeCents();
            if ($fee === null) { throw new \DomainException('Vereinbarte Leihgebühr fehlt.'); }
            $receipt = $this->em->getRepository(EquipmentRentalReceipt::class)->findOneBy(['booking' => $booking]);
            if ($fee > 0 && (!$receipt || $receipt->getAmountCents() < $fee)) {
                throw new \DomainException('Leihgebühr ist noch nicht vollständig bezahlt.');
            }
            if ($receipt && $receipt->getMethod() === 'cash') {
                $refund = (int) $this->em->getConnection()->fetchOne(
                    "SELECT COALESCE(SUM(amount_cents), 0) FROM equipment_cash_correction WHERE source_type = 'rental' AND source_id = :receipt AND club_id = :club AND direction = 'out'",
                    ['receipt' => $receipt->getId(), 'club' => $clubId],
                );
                if ($receipt->getAmountCents() - $refund < $fee) { throw new \DomainException('Leihgebühr wurde zurückgezahlt und ist nicht ausgeglichen.'); }
            }
            foreach ($booking->getItems() as $line) {
                if ($line->getLoanStatus() !== 'returned') { throw new \DomainException('Rückgabe ist unvollständig.'); }
                $asset = $line->getEquipmentItem() ?? $line->getRegulator() ?? $line->getTank();
                if (!$asset) { throw new \DomainException('Inventargegenstand fehlt.'); }
                $physical = EquipmentInventory::physicalAsset($asset);
                $this->em->refresh($physical, LockMode::PESSIMISTIC_WRITE);
                if ($physical !== $asset) { $this->em->refresh($asset, LockMode::PESSIMISTIC_WRITE); }
                if ($asset->getConditionStatus() !== 'ready' || !$asset->isLendable() || $this->maintenance->hasOpenOrder($asset)) {
                    throw new \DomainException('Schadens-/Wartungsbearbeitung zuerst abschließen und Gegenstand freigeben.');
                }
                if ($physical->getConditionStatus() !== 'ready' || !$physical->isLendable()) {
                    throw new \DomainException('Schadens-/Wartungsbearbeitung zuerst abschließen und Gegenstand freigeben.');
                }
            }
            $booking->close($actor);
        });
    }

    public function confirmLegacyFee(int $id, int $clubId, string $amount, string $actor): void
    {
        $cents = TuvPriceCalculator::cents($amount);
        if ($cents === null || $cents > 999999999) { throw new \DomainException('Ungültige vereinbarte Leihgebühr.'); }
        $this->em->wrapInTransaction(function () use ($id, $clubId, $cents, $actor): void {
            $booking = $this->booking($id, $clubId);
            if ($booking->getAgreedRentalFeeCents() !== null || in_array($booking->getStatus(), ['closed', 'cancelled'], true)) {
                throw new \DomainException('Preis ist bereits festgeschrieben oder Buchung abgeschlossen.');
            }
            $booking->setAgreedRentalFeeCents($cents);
            $booking->setNotes(trim(($booking->getNotes() ?? '')."\n".'Altpreis bestätigt durch '.$actor.': '.TuvPriceCalculator::format($cents).' EUR'));
        });
    }

    public function cancel(int $id, int $clubId, string $actor, string $reason, ?int $memberId = null): void
    {
        if (mb_strlen(trim($reason)) < 5 || mb_strlen($reason) > 500) { throw new \DomainException('Stornogrund muss 5 bis 500 Zeichen enthalten.'); }
        $this->em->wrapInTransaction(function () use ($id, $clubId, $actor, $reason, $memberId): void {
            $booking = $this->booking($id, $clubId);
            if ($memberId !== null && ($booking->getMember()?->getId() !== $memberId || $booking->getStartsAt() <= new \DateTimeImmutable())) {
                throw new \DomainException('Diese Reservierung kann nicht mehr storniert werden.');
            }
            if ($booking->getStatus() !== 'reserved' || $this->em->getRepository(EquipmentRentalReceipt::class)->findOneBy(['booking' => $booking])) {
                throw new \DomainException('Nur unausgegebene, unbezahlte Reservierungen können storniert werden.');
            }
            foreach ($booking->getItems() as $line) {
                if ($line->getLoanStatus() !== 'reserved') { throw new \DomainException('Gegenstand wurde bereits ausgegeben.'); }
            }
            $booking->setStatus('cancelled');
            $booking->setNotes(trim(($booking->getNotes() ?? '')."\n".'Storniert durch '.$actor.': '.$reason));
        });
    }

    private function booking(int $id, int $clubId): EquipmentBooking
    {
        $booking = $this->em->find(EquipmentBooking::class, $id);
        if (!$booking) { throw new \DomainException('Buchung nicht gefunden.'); }
        $this->em->refresh($booking, LockMode::PESSIMISTIC_WRITE);
        if ($booking->getMember()?->getClub()?->getId() !== $clubId) { throw new \DomainException('Buchung nicht im eigenen Verein.'); }
        return $booking;
    }
}
