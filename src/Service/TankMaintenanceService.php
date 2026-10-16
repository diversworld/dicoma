<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;
use App\Entity\TankMaintenanceOrder;
use App\Entity\TankConditionHistory;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TankMaintenanceService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function open(int $tankId, string $description, string $actor, ?int $requiredClubId = null): int
    {
        self::validateText($description, 'Wartungsbeschreibung');
        return $this->em->wrapInTransaction(function () use ($tankId, $description, $actor, $requiredClubId): int {
            $tank = $this->em->find(Tank::class, $tankId, LockMode::PESSIMISTIC_WRITE);
            if (!$tank) { throw new \DomainException('Tauchflasche nicht gefunden.'); }
            $this->assertClubScope($tank, $requiredClubId);
            $existing = $this->em->getRepository(TankMaintenanceOrder::class)->findOneBy(['tank' => $tank, 'status' => 'open']);
            if ($existing) { throw new \DomainException('Es existiert bereits ein offener Wartungsauftrag.'); }
            $order = new TankMaintenanceOrder($tank, $description);
            $tank->setConditionStatus('maintenance');
            $tank->setLendable(false);
            $this->em->persist($order);
            $this->em->persist(new TankConditionHistory($tank, 'maintenance_opened', 'maintenance', false, sprintf('[%s] %s', $actor, trim($description))));
            $this->em->flush();
            return $order->getId();
        });
    }

    public function closeForTank(int $tankId, int $orderId, string $resolution, string $actor, ?int $requiredClubId = null): void
    {
        self::validateText($resolution, 'Abschlussbericht');
        $this->em->wrapInTransaction(function () use ($tankId, $orderId, $resolution, $actor, $requiredClubId): void {
            $tank = $this->em->find(Tank::class, $tankId, LockMode::PESSIMISTIC_WRITE);
            if (!$tank) { throw new \DomainException('Tauchflasche nicht gefunden.'); }
            $this->assertClubScope($tank, $requiredClubId);
            $order = $this->em->find(TankMaintenanceOrder::class, $orderId, LockMode::PESSIMISTIC_WRITE);
            if (!$order || $order->getTank()->getId() !== $tankId) {
                throw new \DomainException('Wartungsauftrag gehört nicht zu dieser Flasche.');
            }
            $order->close($actor, $resolution);
            $this->em->persist(new TankConditionHistory(
                $tank, 'maintenance_closed', $tank->getConditionStatus(), $tank->isLendable(),
                sprintf('[%s] %s', $actor, trim($resolution)),
            ));
        });
    }

    public function close(int $orderId, string $resolution, string $actor): void
    {
        $order = $this->em->find(TankMaintenanceOrder::class, $orderId);
        if (!$order) { throw new \DomainException('Wartungsauftrag nicht gefunden.'); }
        // Always acquire locks in the same order: tank first, order second.
        $this->closeForTank($order->getTank()->getId(), $orderId, $resolution, $actor);
    }

    /** Enforce frontend club scope while the tank row is locked. Null preserves admin calls. */
    private function assertClubScope(Tank $tank, ?int $requiredClubId): void
    {
        if ($requiredClubId !== null && $tank->getClub()?->getId() !== $requiredClubId) {
            throw new \DomainException('Flasche gehört nicht zum berechtigten Verein.');
        }
    }

    private static function validateText(string $value, string $label): void
    {
        $length = mb_strlen(trim($value));
        if ($length < 10 || $length > 2000) {
            throw new \DomainException(sprintf('%s muss zwischen 10 und 2000 Zeichen enthalten.', $label));
        }
    }
}
