<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TankStatusWorkflow
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly EquipmentMaintenanceService $maintenance) {}

    public function apply(int $tankId, string $operation, string $reason, string $actor, ?int $requiredClubId = null): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 2000) {
            throw new \DomainException('Eine Begründung mit 10 bis 2000 Zeichen ist erforderlich.');
        }
        if (!in_array($operation, ['maintenance', 'damaged', 'release'], true)) {
            throw new \DomainException('Unzulässige Statusänderung.');
        }

        $this->em->wrapInTransaction(function () use ($tankId, $operation, $reason, $actor, $requiredClubId): void {
            $tank = $this->em->find(Tank::class, $tankId, LockMode::PESSIMISTIC_WRITE);
            if (!$tank) {
                throw new \DomainException('Tauchflasche nicht gefunden.');
            }
            $this->em->refresh($tank, LockMode::PESSIMISTIC_WRITE);
            if ($requiredClubId !== null && $tank->getClub()?->getId() !== $requiredClubId) {
                throw new \DomainException('Flasche gehört nicht zum berechtigten Verein.');
            }
            if ($operation === 'release') {
                $this->maintenance->assertReleasable($tank);
                if ($tank->getConditionStatus() === 'ready' && $tank->isLendable()) {
                    throw new \DomainException('Die Flasche ist bereits freigegeben.');
                }
                $this->maintenance->setTechnicalState($tank, 'ready', true);
            } else {
                if ($tank->getConditionStatus() === $operation && !$tank->isLendable()) {
                    throw new \DomainException('Dieser Sperrstatus ist bereits aktiv.');
                }
                $this->maintenance->setTechnicalState($tank, $operation, false);
            }
            $this->maintenance->history(
                $tank,
                $operation === 'release' ? 'manual_release' : 'manual_block',
                $reason,
                $actor,
            );
        });
    }
}
