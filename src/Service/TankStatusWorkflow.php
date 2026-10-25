<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;
use App\Entity\EquipmentBookingItem;
use App\Entity\TankConditionHistory;
use App\Entity\TankMaintenanceOrder;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class TankStatusWorkflow
{
    public function __construct(private readonly EntityManagerInterface $em) {}

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
                $openOrder = $this->em->getRepository(TankMaintenanceOrder::class)
                    ->findOneBy(['tank' => $tank, 'status' => 'open']);
                if ($openOrder !== null) {
                    throw new \DomainException('Freigabe verweigert: Es existiert ein offener Wartungsauftrag.');
                }

                // Under the tank row lock, reject release while a physical loan is open.
                // Issuing a tank also locks the same tank row, serializing both operations.
                $activeLoan = $this->em->createQueryBuilder()
                    ->select('i.id')
                    ->from(EquipmentBookingItem::class, 'i')
                    ->where('i.tank = :tank')
                    ->andWhere('i.loanStatus = :issued')
                    ->setParameter('tank', $tank)
                    ->setParameter('issued', 'issued')
                    ->setMaxResults(1)
                    ->getQuery()->getOneOrNullResult();
                if ($activeLoan !== null) {
                    throw new \DomainException('Freigabe verweigert: Die Flasche ist noch ausgegeben.');
                }
                if ($tank->getConditionStatus() === 'ready' && $tank->isLendable()) {
                    throw new \DomainException('Die Flasche ist bereits freigegeben.');
                }
                $next = $tank->getNextCheckDate();
                if (!$next || $next->format('Y-m-d') < (new \DateTimeImmutable('today'))->format('Y-m-d')) {
                    throw new \DomainException('Freigabe verweigert: Flaschenprüfung ist abgelaufen oder fehlt.');
                }
                $tank->setConditionStatus('ready');
                $tank->setLendable(true);
            } else {
                if ($tank->getConditionStatus() === $operation && !$tank->isLendable()) {
                    throw new \DomainException('Dieser Sperrstatus ist bereits aktiv.');
                }
                $tank->setConditionStatus($operation);
                $tank->setLendable(false);
            }
            $this->em->persist(new TankConditionHistory(
                $tank,
                $operation === 'release' ? 'manual_release' : 'manual_block',
                $tank->getConditionStatus(),
                $tank->isLendable(),
                sprintf('[%s] %s', $actor, $reason),
            ));
        });
    }
}
