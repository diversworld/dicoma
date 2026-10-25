<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;

final class TankFillingEligibility
{
    public function __construct(private readonly EquipmentMaintenanceService $maintenance) {}

    public function assertFillable(Tank $tank, int $clubId): void
    {
        if ($tank->getClub()?->getId() !== $clubId || $tank->getConditionStatus() !== 'ready') {
            throw new \DomainException('Flasche ist technisch gesperrt oder nicht im eigenen Verein.');
        }
        $next = $tank->getNextCheckDate();
        if (!$next || $next->format('Y-m-d') < date('Y-m-d')) { throw new \DomainException('Flaschenprüfung fehlt oder ist abgelaufen.'); }
        if ($this->maintenance->hasOpenOrder($tank)) { throw new \DomainException('Flasche hat einen offenen Wartungsauftrag.'); }
    }
}
