<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentReservation;
use App\Entity\EquipmentBookingItem;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentReservationService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function available(EquipmentReservation $reservation): bool
    {
        $item = $reservation->getEquipmentItem();
        $member = $reservation->getMember();
        $start = $reservation->getStartsAt();
        $end = $reservation->getEndsAt();
        if (!$item || !$member || !$start || !$end || $start >= $end || $reservation->getQuantity() < 1) { return false; }
        if (!$item->isLendable() || $item->getConditionStatus() !== 'ready') { return false; }
        if (!$item->getClub()?->getId() || $item->getClub()->getId() !== $member->getClub()?->getId()) { return false; }
        if ($item->getStockType() === 'individual' && $reservation->getQuantity() !== 1) { return false; }
        $used = (int) $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(r.quantity), 0)')
            ->from(EquipmentReservation::class, 'r')
            ->where('r.equipmentItem = :item')
            ->andWhere('(r.status = :issued OR (r.status = :reserved AND r.startsAt < :end AND r.endsAt > :start))')
            ->andWhere('(:exclude IS NULL OR r.id != :exclude)')
            ->setParameter('item', $item)
            ->setParameter('issued', 'issued')
            ->setParameter('reserved', 'reserved')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('exclude', $reservation->getId())
            ->getQuery()->getSingleScalarResult();
        // New multi-item bookings share the same physical stock.
        $bookingUsed = (int) $this->em->createQueryBuilder()
            ->select('COALESCE(SUM(bi.quantity), 0)')
            ->from(EquipmentBookingItem::class, 'bi')
            ->join('bi.booking', 'b')
            ->where('bi.equipmentItem = :item')
            ->andWhere('b.status = :reserved AND b.startsAt < :end AND b.endsAt > :start')
            ->setParameter('item', $item)
            ->setParameter('reserved', 'reserved')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()->getSingleScalarResult();
        return $used + $bookingUsed + $reservation->getQuantity() <= $item->getTotalQuantity();
    }

    public function save(EquipmentReservation $reservation): void
    {
        $this->em->wrapInTransaction(function () use ($reservation): void {
            $item = $reservation->getEquipmentItem();
            if (!$item || !$item->getId()) { throw new \DomainException('Ungültiger Inventarartikel.'); }
            $this->em->lock($item, LockMode::PESSIMISTIC_WRITE);
            if (!$this->available($reservation)) { throw new \DomainException('Ausrüstung im Zeitraum nicht verfügbar oder nicht zulässig.'); }
            $this->em->persist($reservation);
            $this->em->flush();
        });
    }
}
