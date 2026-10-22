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
            $key = get_class($asset).':'.$asset->getId();
            if (!$asset->getId() || isset($assets[$key])) { throw new \DomainException('Gegenstand doppelt oder nicht gespeichert.'); }
            $assets[$key] = $asset;
        }
        ksort($assets, SORT_STRING);
        $this->em->wrapInTransaction(function () use ($booking, $assets, $clubId, $start, $end): void {
            foreach ($assets as $asset) {
                $this->availability->assertAvailable($asset, $clubId, $start, $end);
            }
            // DICOMA_RENTAL_PRICE_SNAPSHOT_V1: snapshot all asset fees in cents.
            // Do not silently save a partial price if an asset has no valid fee.
            $totalCents = 0;
            $complete = true;
            foreach ($booking->getItems() as $line) {
                $asset = $line->getEquipmentItem() ?? $line->getRegulator() ?? $line->getTank();
                if ($asset === null || !method_exists($asset, 'getRentalFee')) {
                    $complete = false;
                    break;
                }
                $fee = $asset->getRentalFee();
                if (!is_string($fee) || !preg_match('/^\d{1,8}\.\d{2}$/D', $fee)) {
                    $complete = false;
                    break;
                }
                [$euros, $cents] = explode('.', $fee);
                $totalCents += (int) $euros * 100 + (int) $cents;
                if ($totalCents > 2147483647) { throw new \DomainException('Rental amount too large.'); }
            }
            if (!$complete) {
                throw new \DomainException('Rental fee is missing or invalid for an asset. Please configure all fees before booking.');
            }
            $booking->setAgreedRentalFeeCents($totalCents);
            $this->em->persist($booking);
            $this->em->flush();
        });
    }
}
