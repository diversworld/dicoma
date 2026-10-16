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
    ) {}

    public function change(int $bookingId, int $lineId, string $operation, ?string $condition = null, ?string $notes = null): void
    {
        if (!in_array($operation, ['issue', 'return'], true)) {
            throw new \DomainException('Unbekannte Verleihaktion.');
        }
        if ($operation === 'return' && !in_array($condition, ['ready', 'maintenance', 'damaged'], true)) {
            throw new \DomainException('Rueckgabezustand fehlt oder ist ungueltig.');
        }

        $this->em->wrapInTransaction(function () use ($bookingId, $lineId, $operation, $condition, $notes): void {
            $booking = $this->em->find(EquipmentBooking::class, $bookingId, LockMode::PESSIMISTIC_WRITE);
            $line = $this->em->find(EquipmentBookingItem::class, $lineId, LockMode::PESSIMISTIC_WRITE);
            if (!$booking || !$line || $line->getBooking()?->getId() !== $booking->getId()) {
                throw new \DomainException('Buchung oder zugehoerige Position nicht gefunden.');
            }
            $assets = array_filter([$line->getEquipmentItem(), $line->getRegulator(), $line->getTank()]);
            if (count($assets) !== 1 || $line->getQuantity() !== 1) {
                throw new \DomainException('Position muss genau einen Inventargegenstand enthalten.');
            }
            $asset = reset($assets);
            $this->em->lock($asset, LockMode::PESSIMISTIC_WRITE);

            if ($operation === 'issue') {
                $memberClubId = $booking->getMember()?->getClub()?->getId();
                if (!$memberClubId || !$booking->getStartsAt() || !$booking->getEndsAt()) {
                    throw new \DomainException('Verein oder Buchungszeitraum fehlt.');
                }
                $this->availability->assertAvailable(
                    $asset, $memberClubId, $booking->getStartsAt(), $booking->getEndsAt(), $booking->getId()
                );
                if ($line->getLoanStatus() !== 'reserved' || !in_array($booking->getStatus(), ['reserved', 'partially_issued', 'partially_returned'], true)) {
                    throw new \DomainException('Diese Position kann nicht ausgegeben werden.');
                }
                $end = $booking->getEndsAt();
                if (!$end) { throw new \DomainException('Buchungsende fehlt.'); }
                if ($line->getTank()) {
                    if (!$asset->isLendable() || $asset->getConditionStatus() !== 'ready') {
                        throw new \DomainException('Tauchflasche ist technisch gesperrt.');
                    }
                    $check = $line->getTank()->getNextCheckDate();
                    if (!$check || $check < $end) {
                        throw new \DomainException('Tauchflaschenpruefung ist nicht bis zum Mietende gueltig.');
                    }
                } else {
                    if (!$asset->isLendable() || $asset->getConditionStatus() !== 'ready') {
                        throw new \DomainException('Gegenstand ist nicht verleihfaehig.');
                    }
                    if ($line->getRegulator()) {
                        $service = $line->getRegulator()->getNextServiceAt();
                        if (!$service || $service < $end->setTime(0, 0)) {
                            throw new \DomainException('Atemreglerrevision ist nicht bis zum Mietende gueltig.');
                        }
                    }
                }
                $line->markIssued(new \DateTimeImmutable());
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
                }
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
        });
    }
}
