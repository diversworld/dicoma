<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/** Position-wise issue/return for the three separate inventory tables. */
final class EquipmentBookingLoanService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipmentAssetAvailability $availability,
        private readonly TankConditionHistoryRecorder $historyRecorder,
        private readonly EquipmentInventory $inventory,
        private readonly EquipmentMaintenanceService $maintenance,
    ) {}

    public function change(int $bookingId, int $lineId, string $operation, ?string $condition = null, ?string $notes = null): void
    {
        if (!in_array($operation, ['issue', 'return'], true)) {
            throw new \DomainException('Unbekannte Verleihaktion.');
        }
        if ($operation === 'return' && !in_array($condition, ['ready', 'maintenance', 'damaged'], true)) {
            throw new \DomainException('Rueckgabezustand fehlt oder ist ungueltig.');
        }
        if ($notes !== null && mb_strlen($notes) > 2000) { throw new \DomainException('Rückgabenotiz ist zu lang.'); }
        if ($operation === 'return' && $condition !== 'ready' && mb_strlen(trim($notes ?? '')) < 10) {
            throw new \DomainException('Defekt oder Wartungsbedarf muss mit mindestens 10 Zeichen beschrieben werden.');
        }

        $this->em->wrapInTransaction(function () use ($bookingId, $lineId, $operation, $condition, $notes): void {
            $this->changeWithinTransaction($bookingId, $lineId, $operation, $condition, $notes);
        });
    }

    // DICOMA_BATCH_ISSUE_V1
    public function issueAll(int $bookingId): int
    {
        return $this->em->wrapInTransaction(function () use ($bookingId): int {
            $booking = $this->em->find(EquipmentBooking::class, $bookingId, LockMode::PESSIMISTIC_WRITE);
            if (!$booking || !in_array($booking->getStatus(), ['reserved', 'partially_issued', 'partially_returned'], true)) {
                throw new \DomainException('Buchung ist nicht zur Sammelausgabe freigegeben.');
            }
            $ids = [];
            foreach ($booking->getItems() as $line) {
                if ($line->getLoanStatus() === 'reserved') { $ids[] = $line->getId(); }
            }
            sort($ids, SORT_NUMERIC);
            if (!$ids) { throw new \DomainException('Keine reservierten Gegenstaende vorhanden.'); }
            foreach ($ids as $id) {
                $this->changeWithinTransaction($bookingId, $id, 'issue', null, null);
            }
            return count($ids);
        });
    }

    private function changeWithinTransaction(int $bookingId, int $lineId, string $operation, ?string $condition, ?string $notes): void
    {
            $booking = $this->em->find(EquipmentBooking::class, $bookingId, LockMode::PESSIMISTIC_WRITE);
            $line = $this->em->find(EquipmentBookingItem::class, $lineId, LockMode::PESSIMISTIC_WRITE);
            if (!$booking || !$line || $line->getBooking()?->getId() !== $booking->getId()) {
                throw new \DomainException('Buchung oder zugehoerige Position nicht gefunden.');
            }
            $this->em->refresh($booking, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($line, LockMode::PESSIMISTIC_WRITE);
            $assets = array_filter([$line->getEquipmentItem(), $line->getRegulator(), $line->getTank()]);
            if (count($assets) !== 1 || $line->getQuantity() !== 1) {
                throw new \DomainException('Position muss genau einen Inventargegenstand enthalten.');
            }
            $asset = reset($assets);
            $this->em->refresh(EquipmentInventory::physicalAsset($asset), LockMode::PESSIMISTIC_WRITE);
            if (EquipmentInventory::physicalAsset($asset) !== $asset) { $this->em->refresh($asset, LockMode::PESSIMISTIC_WRITE); }

            if ($operation === 'issue') {
                foreach ($booking->getItems() as $other) {
                    $otherAsset = $other->getEquipmentItem() ?? $other->getRegulator() ?? $other->getTank();
                    if ($other->getId() !== $lineId && $otherAsset && EquipmentInventory::physicalAsset($otherAsset) === EquipmentInventory::physicalAsset($asset)) {
                        throw new \DomainException('Die physische Ausrüstung ist mehrfach in dieser Buchung enthalten.');
                    }
                }
                if (($booking->isApprovalRequired() || $this->inventory->requiresApproval($asset)) && $booking->getApprovedAt() === null) {
                    throw new \DomainException('Diese Buchung muss zuerst vom Gerätewart genehmigt werden.');
                }
                $memberClubId = $booking->getMember()?->getClub()?->getId();
                if (!$memberClubId || !$booking->getStartsAt() || !$booking->getEndsAt()) {
                    throw new \DomainException('Verein oder Buchungszeitraum fehlt.');
                }
                $now = new \DateTimeImmutable();
                if ($booking->getStartsAt() > $now || $booking->getEndsAt() <= $now) {
                    throw new \DomainException('Ausgabe ist nur innerhalb des reservierten Zeitraums möglich.');
                }
                $this->availability->assertAvailable(
                    $asset, $memberClubId, $booking->getStartsAt(), $booking->getEndsAt(), $booking->getId()
                );
                if ($line->getLoanStatus() !== 'reserved' || !in_array($booking->getStatus(), ['reserved', 'partially_issued', 'partially_returned'], true)) {
                    throw new \DomainException('Diese Position kann nicht ausgegeben werden.');
                }
                $line->markIssued(new \DateTimeImmutable());
                $this->inventory->record($asset)->record('issued', 'Buchung #'.$bookingId, 'Position #'.$lineId.' ausgegeben.');
            } else {
                if ($line->getLoanStatus() !== 'issued') {
                    throw new \DomainException('Nur ausgegebene Positionen koennen zurueckgenommen werden.');
                }

                $line->markReturned(new \DateTimeImmutable(), (string) $condition, $notes);
                if ($line->getTank()) {
                    $this->historyRecorder->recordReturn($line->getTank(), $bookingId, $lineId, (string) $condition, $notes);
                }
                if ($condition !== 'ready') {
                    $asset->setConditionStatus((string) $condition);
                    $asset->setLendable(false);
                    $physical = EquipmentInventory::physicalAsset($asset);
                    if ($physical !== $asset) { $physical->setConditionStatus((string) $condition); $physical->setLendable(false); }
                    if (!$this->maintenance->hasOpenOrder($asset)) {
                        $this->em->persist(new \App\Entity\TankMaintenanceOrder($physical, (string) $notes, $condition === 'damaged' ? 'defect' : 'maintenance', 'Gerätewart', 'Buchung #'.$bookingId));
                    }
                }
                $this->inventory->record($asset)->record('returned', 'Buchung #'.$bookingId, (string) $condition.': '.($notes ?? ''));
            }

            $statuses = array_map(
                static fn (EquipmentBookingItem $position): string => $position->getLoanStatus(),
                $booking->getItems()->toArray()
            );
            if (!$statuses) { throw new \DomainException('Buchung enthaelt keine Positionen.'); }
            $reserved = count(array_filter($statuses, static fn (string $s): bool => $s === 'reserved'));
            $issued = count(array_filter($statuses, static fn (string $s): bool => $s === 'issued'));
            $returned = count(array_filter($statuses, static fn (string $s): bool => $s === 'returned'));
            if ($returned === count($statuses)) {
                $booking->setStatus('returned');
            } elseif ($issued === count($statuses)) {
                $booking->setStatus('issued');
            } elseif ($returned > 0) {
                $booking->setStatus('partially_returned');
            } elseif ($issued > 0) {
                $booking->setStatus('partially_issued');
            } elseif ($reserved === count($statuses)) {
                $booking->setStatus('reserved');
            } else {
                throw new \DomainException('Unbekannte Positionsstatus in der Buchung.');
            }
            $this->em->flush();
    }
}
