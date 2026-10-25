<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class EquipmentBookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipmentAssetAvailability $availability,
        private readonly ValidatorInterface $validator,
        private readonly EquipmentInventory $inventory,
    ) {}

    public function saveNew(EquipmentBooking $booking): void
    {
        if ($booking->getId() !== null || $booking->getStatus() !== 'reserved') {
            throw new \DomainException('Ungueltiger Buchungsstatus oder bereits gespeicherte Buchung.');
        }
        if (count($this->validator->validate($booking)) > 0) {
            throw new \DomainException('Buchung unvollstaendig oder ungueltig.');
        }
        $member = $booking->getMember();
        $start = $booking->getStartsAt();
        $end = $booking->getEndsAt();
        $clubId = $member?->getClub()?->getId();
        if (!$clubId || !$start || !$end || $start >= $end) {
            throw new \DomainException('Ungueltiges Mitglied oder Zeitraum.');
        }
        $lines = $booking->getItems()->toArray();
        if ($lines === []) { throw new \DomainException('Keine Gegenstaende ausgewaehlt.'); }
        $assets = [];
        foreach ($lines as $line) {
            if (!$line instanceof EquipmentBookingItem || $line->getQuantity() !== 1 || $line->getLoanStatus() !== 'reserved') {
                throw new \DomainException('Nur einzelne reservierte Gegenstaende sind zulaessig.');
            }
            $candidates = array_values(array_filter([$line->getEquipmentItem(), $line->getRegulator(), $line->getTank()]));
            if (count($candidates) !== 1) { throw new \DomainException('Jede Position muss genau einen Gegenstand enthalten.'); }
            $asset = $candidates[0];
            $physical = EquipmentInventory::physicalAsset($asset);
            $key = EquipmentInventory::field($physical).':'.$physical->getId();
            if (!$asset->getId() || isset($assets[$key])) { throw new \DomainException('Gegenstand doppelt oder nicht gespeichert.'); }
            $assets[$key] = $asset;
        }
        ksort($assets, SORT_STRING);
        $this->em->wrapInTransaction(function () use ($booking, $assets, $clubId, $start, $end): void {
            foreach ($assets as $asset) {
                $this->availability->assertAvailable($asset, $clubId, $start, $end);
                if ($this->inventory->requiresApproval($asset)) { $booking->requireApproval(); }
            }
            // DICOMA_RENTAL_PRICE_SNAPSHOT_V1: snapshot all asset fees in cents.
            // Do not silently save a partial price if an asset has no valid fee.
            $totalCents = 0;
            foreach ($booking->getItems() as $line) {
                $asset = $line->getEquipmentItem() ?? $line->getRegulator() ?? $line->getTank();
                $totalCents += EquipmentInventory::feeCents($asset);
                if ($totalCents > 2147483647) { throw new \DomainException('Rental amount too large.'); }
            }
            $booking->setAgreedRentalFeeCents($totalCents);
            $this->em->persist($booking);
            $this->em->flush();
        });
    }
}
