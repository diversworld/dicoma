<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentItem;
use App\Entity\EquipmentReservation;
use App\Entity\Regulator;
use App\Entity\Tank;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/** The authoritative check serializes reservations, issue and maintenance on the asset row. */
final class EquipmentAssetAvailability
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipmentInventory $inventory,
        private readonly EquipmentMaintenanceService $maintenance,
        private readonly EquipmentRentalEligibility $eligibility,
    ) {}

    public function isAvailablePreview(
        EquipmentItem|Regulator|Tank $asset, int $clubId, \DateTimeImmutable $start, \DateTimeImmutable $end,
        ?int $excludeBookingId = null, ?int $excludeReservationId = null,
    ): bool {
        if ($start >= $end || !$asset->getId() || $asset->getClub()?->getId() !== $clubId) { return false; }
        try { $this->assertEligible($asset, $end); }
        catch (\DomainException) { return false; }
        return $this->isFree($asset, $start, $end, $excludeBookingId, $excludeReservationId);
    }

    public function assertAvailable(
        EquipmentItem|Regulator|Tank $asset, int $clubId, \DateTimeImmutable $start, \DateTimeImmutable $end,
        ?int $excludeBookingId = null, ?int $excludeReservationId = null,
    ): void {
        if ($start >= $end || !$asset->getId()) { throw new \DomainException('Inventargegenstand oder Zeitraum ist ungültig.'); }
        $physical = EquipmentInventory::physicalAsset($asset);
        $this->em->refresh($physical, LockMode::PESSIMISTIC_WRITE);
        if ($physical !== $asset) { $this->em->refresh($asset, LockMode::PESSIMISTIC_WRITE); }
        if ($asset->getClub()?->getId() !== $clubId) { throw new \DomainException('Gegenstand gehört nicht zum eigenen Verein.'); }
        $this->assertEligible($asset, $end);
        if (!$this->isFree($asset, $start, $end, $excludeBookingId, $excludeReservationId)) {
            throw new \DomainException('Gegenstand ist bereits reserviert oder noch ausgegeben.');
        }
    }

    public function assertEligible(EquipmentItem|Regulator|Tank $asset, \DateTimeImmutable $end): void
    {
        if (!$asset->isLendable() || $asset->getConditionStatus() !== 'ready' || $this->maintenance->hasOpenOrder($asset)) {
            throw new \DomainException('Gegenstand ist technisch gesperrt oder in Wartung.');
        }
        if ($asset instanceof EquipmentItem) {
            $this->eligibility->assertEligible($asset, $end);
            if (!$asset->getCategory()?->isActive()) { throw new \DomainException('Ausrüstungskategorie ist nicht aktiv.'); }
            if ($asset->getTank() !== null) {
                if ($asset->getTank()->getClub()?->getId() !== $asset->getClub()?->getId()) { throw new \DomainException('Verknüpfte Flasche gehört nicht zum Verein.'); }
                $this->assertEligible($asset->getTank(), $end);
            }
        }
        $due = $this->inventory->dueAt($asset);
        $record = $this->inventory->findRecord($asset);
        $required = $asset instanceof Tank || $asset instanceof Regulator || $record?->getInspectionIntervalMonths() !== null;
        if (($required && $due === null) || ($due !== null && $due->format('Y-m-d') < $end->format('Y-m-d'))) {
            throw new \DomainException('Prüfung oder Revision ist nicht bis zum Verleihende gültig.');
        }
    }

    private function isFree(
        EquipmentItem|Regulator|Tank $asset, \DateTimeImmutable $start, \DateTimeImmutable $end,
        ?int $excludeBookingId, ?int $excludeReservationId,
    ): bool {
        $physical = EquipmentInventory::physicalAsset($asset);
        $qb = $this->em->createQueryBuilder()->select('COUNT(bi.id)')
            ->from(EquipmentBookingItem::class, 'bi')->join('bi.booking', 'b')
            ->leftJoin('bi.equipmentItem', 'linked')
            ->where($physical instanceof Tank ? '(bi.tank = :asset OR linked.tank = :asset)' : 'bi.'.EquipmentInventory::field($physical).' = :asset')
            ->andWhere('(bi.loanStatus = :issued OR (bi.loanStatus = :reserved AND b.status != :cancelled AND b.startsAt < :end AND b.endsAt > :start))')
            ->setParameter('asset', $physical)->setParameter('issued', 'issued')->setParameter('reserved', 'reserved')
            ->setParameter('cancelled', 'cancelled')->setParameter('start', $start)->setParameter('end', $end);
        if ($excludeBookingId !== null) { $qb->andWhere('b.id != :excluded')->setParameter('excluded', $excludeBookingId); }
        if ((int) $qb->getQuery()->getSingleScalarResult() > 0) { return false; }
        if ($asset instanceof EquipmentItem || $physical instanceof Tank) {
            $legacy = $this->em->createQueryBuilder()->select('COUNT(r.id)')->from(EquipmentReservation::class, 'r')
                ->join('r.equipmentItem', 'legacyItem')
                ->where($physical instanceof Tank ? 'legacyItem.tank = :asset' : 'r.equipmentItem = :asset')
                ->andWhere('(r.status = :issued OR (r.status = :reserved AND r.startsAt < :end AND r.endsAt > :start))')
                ->setParameter('asset', $physical)->setParameter('issued', 'issued')->setParameter('reserved', 'reserved')
                ->setParameter('start', $start)->setParameter('end', $end);
            if ($excludeReservationId !== null) { $legacy->andWhere('r.id != :excluded')->setParameter('excluded', $excludeReservationId); }
            if ((int) $legacy->getQuery()->getSingleScalarResult() > 0) { return false; }
        }
        return true;
    }
}
