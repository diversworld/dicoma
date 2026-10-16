<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentItem;
use App\Entity\EquipmentReservation;
use App\Entity\Regulator;
use App\Entity\Tank;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/** Checks one individually inventoried asset, under a transaction-held row lock. */
final class EquipmentAssetAvailability
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function assertAvailable(
        EquipmentItem|Regulator|Tank $asset,
        int $clubId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $excludeBookingId = null
    ): void {
        if ($start >= $end) {
            throw new \DomainException('Der Reservierungszeitraum ist ungueltig.');
        }
        if (!$asset->getId()) {
            throw new \DomainException('Der Inventargegenstand ist nicht gespeichert.');
        }
        $this->em->lock($asset, LockMode::PESSIMISTIC_WRITE);
        if ($asset->getClub()?->getId() !== $clubId) {
            throw new \DomainException('Der Gegenstand gehoert zu einem anderen Verein oder ist keinem Verein zugeordnet.');
        }
        if ($asset instanceof Tank) {
            if (!$asset->isLendable() || $asset->getConditionStatus() !== 'ready') {
                throw new \DomainException('Die Flasche ist technisch gesperrt.');
            }
            $check = $asset->getNextCheckDate();
            if (!$check || $check->format('Y-m-d') < $end->format('Y-m-d')) {
                throw new \DomainException('Die Flaschenpruefung ist nicht bis zum Reservierungsende gueltig.');
            }
            $field = 'tank';
        } elseif ($asset instanceof Regulator) {
            if (!$asset->isLendable() || $asset->getConditionStatus() !== 'ready') {
                throw new \DomainException('Der Atemregler ist nicht verleihfaehig.');
            }
            $due = $asset->getNextServiceAt();
            if (!$due || $due->format('Y-m-d') < $end->format('Y-m-d')) {
                throw new \DomainException('Die Atemreglerrevision ist nicht bis zum Reservierungsende gueltig.');
            }
            $field = 'regulator';
        } else {
            if (!$asset->isLendable() || $asset->getConditionStatus() !== 'ready'
                || $asset->getStockType() !== 'individual' || $asset->getTotalQuantity() !== 1) {
                throw new \DomainException('Der Gegenstand ist nicht verleihfaehig.');
            }
            $field = 'equipmentItem';
        }

        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(bi.id)')
            ->from(EquipmentBookingItem::class, 'bi')
            ->join('bi.booking', 'b')
            ->where('bi.'.$field.' = :asset')
            ->andWhere('(bi.loanStatus = :issued OR (bi.loanStatus = :reserved AND b.startsAt < :end AND b.endsAt > :start))')
            ->setParameter('asset', $asset)
            ->setParameter('issued', 'issued')
            ->setParameter('reserved', 'reserved')
            ->setParameter('start', $start)
            ->setParameter('end', $end);
        if ($excludeBookingId !== null) {
            $qb->andWhere('b.id != :excluded')->setParameter('excluded', $excludeBookingId);
        }
        if ((int) $qb->getQuery()->getSingleScalarResult() > 0) {
            throw new \DomainException('Dieser Gegenstand ist bereits reserviert oder noch ausgeliehen.');
        }
        if ($asset instanceof EquipmentItem) {
            $legacy = (int) $this->em->createQueryBuilder()
                ->select('COUNT(r.id)')->from(EquipmentReservation::class, 'r')
                ->where('r.equipmentItem = :asset')
                ->andWhere('(r.status = :issued OR (r.status = :reserved AND r.startsAt < :end AND r.endsAt > :start))')
                ->setParameter('asset', $asset)->setParameter('issued', 'issued')
                ->setParameter('reserved', 'reserved')->setParameter('start', $start)->setParameter('end', $end)
                ->getQuery()->getSingleScalarResult();
            if ($legacy > 0) {
                throw new \DomainException('Der Gegenstand ist durch eine Einzelreservierung belegt.');
            }
        }
    }
}
