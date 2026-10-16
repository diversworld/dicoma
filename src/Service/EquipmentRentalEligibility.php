<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentItem;

/** Shared safety checks for all three equipment types. */
final class EquipmentRentalEligibility
{
    public function assertEligible(EquipmentItem $item, ?\DateTimeImmutable $until = null): void
    {
        if (!$item->isLendable() || $item->getConditionStatus() !== 'ready'
            || $item->getStockType() !== 'individual' || $item->getTotalQuantity() !== 1) {
            throw new \DomainException('Dieser Gegenstand ist nicht zum Verleih freigegeben.');
        }
        if ($item->getTank() !== null) {
            $due = $item->getTank()->getNextCheckDate();
            $end = $until ?? new \DateTimeImmutable('today');
            if ($due === null || $due->format('Y-m-d') < $end->format('Y-m-d')) {
                throw new \DomainException('Flasche ohne gueltige Prueffreigabe.');
            }
        }
        $regulator = $item->getRegulatorDetails();
        if ($regulator !== null) {
            $due = $regulator->getNextServiceAt();
            $end = $until ?? new \DateTimeImmutable('today');
            if ($due === null || $due < $end->setTime(0, 0)) {
                throw new \DomainException('Atemregler ohne gueltige Revisionsfreigabe.');
            }
        }
    }
}
