<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentReservation;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentReservationService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly EquipmentAssetAvailability $availability) {}

    public function available(EquipmentReservation $reservation): bool
    {
        $item = $reservation->getEquipmentItem();
        $member = $reservation->getMember();
        $start = $reservation->getStartsAt();
        $end = $reservation->getEndsAt();
        if (!$item || !$member || !$start || !$end || $start >= $end || $reservation->getQuantity() < 1) { return false; }
        $clubId = $member->getClub()?->getId();
        return $clubId !== null && $reservation->getQuantity() === 1
            && $this->availability->isAvailablePreview($item, $clubId, $start, $end, null, $reservation->getId());
    }

    public function save(EquipmentReservation $reservation): void
    {
        $this->em->wrapInTransaction(function () use ($reservation): void {
            $item = $reservation->getEquipmentItem();
            if (!$item || !$item->getId()) { throw new \DomainException('Ungültiger Inventarartikel.'); }
            $clubId = $reservation->getMember()?->getClub()?->getId();
            if (!$clubId || !$reservation->getStartsAt() || !$reservation->getEndsAt()) { throw new \DomainException('Mitglied oder Zeitraum fehlt.'); }
            $this->availability->assertAvailable($item, $clubId, $reservation->getStartsAt(), $reservation->getEndsAt(), null, $reservation->getId());
            if (!$this->available($reservation)) { throw new \DomainException('Ausrüstung im Zeitraum nicht verfügbar oder nicht zulässig.'); }
            $this->em->persist($reservation);
            $this->em->flush();
        });
    }
}
