<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentItem;
use App\Entity\EquipmentReservation;
use App\Entity\Regulator;
use App\Entity\Tank;
use App\Entity\TankConditionHistory;
use App\Entity\TankMaintenanceOrder;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentMaintenanceService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly EquipmentInventory $inventory) {}

    public function open(string $type, int $id, int $clubId, string $kind, string $description, string $responsible, string $actor): int
    {
        self::text($description);
        if (trim($responsible) === '' || mb_strlen($responsible) > 180) { throw new \DomainException('Verantwortliche Person erforderlich (maximal 180 Zeichen).'); }
        return $this->em->wrapInTransaction(function () use ($type, $id, $clubId, $kind, $description, $responsible, $actor): int {
            $asset = EquipmentInventory::physicalAsset($this->inventory->asset($type, $id, $clubId, true));
            if ($this->hasOpenOrder($asset)) { throw new \DomainException('Es besteht bereits ein offener Wartungsauftrag.'); }
            $order = new TankMaintenanceOrder($asset, trim($description), $kind, trim($responsible), $actor);
            $this->setTechnicalState($asset, $kind === 'defect' ? 'damaged' : 'maintenance', false);
            $this->em->persist($order);
            $this->history($asset, 'maintenance_opened', $description, $actor);
            $this->em->flush();
            return $order->getId();
        });
    }

    public function close(string $type, int $id, int $clubId, int $orderId, string $resolution, string $actor): void
    {
        self::text($resolution);
        $this->em->wrapInTransaction(function () use ($type, $id, $clubId, $orderId, $resolution, $actor): void {
            $asset = EquipmentInventory::physicalAsset($this->inventory->asset($type, $id, $clubId, true));
            $order = $this->em->find(TankMaintenanceOrder::class, $orderId, LockMode::PESSIMISTIC_WRITE);
            if ($order) { $this->em->refresh($order, LockMode::PESSIMISTIC_WRITE); }
            if (!$order || EquipmentInventory::physicalAsset($order->getAsset()) !== $asset) { throw new \DomainException('Wartungsauftrag gehört nicht zum Gegenstand.'); }
            $order->close($actor, $resolution);
            $this->history($asset, 'maintenance_closed', $resolution, $actor);
        });
    }

    public function hasOpenOrder(EquipmentItem|Regulator|Tank $asset): bool
    {
        $asset = EquipmentInventory::physicalAsset($asset);
        if ($asset instanceof Tank) {
            return (int) $this->em->createQueryBuilder()->select('COUNT(o.id)')->from(TankMaintenanceOrder::class, 'o')
                ->leftJoin('o.equipmentItem', 'linked')
                ->where('(o.tank = :asset OR linked.tank = :asset) AND o.status = :open')
                ->setParameter('asset', $asset)->setParameter('open', 'open')->getQuery()->getSingleScalarResult() > 0;
        }
        return $this->em->getRepository(TankMaintenanceOrder::class)->findOneBy([EquipmentInventory::field($asset) => $asset, 'status' => 'open']) !== null;
    }

    public function inspect(string $type, int $id, int $clubId, string $performedRaw, string $nextRaw, string $reference, string $actor): void
    {
        self::text($reference);
        $performed = \DateTimeImmutable::createFromFormat('!Y-m-d', $performedRaw);
        if (!$performed || $performed->format('Y-m-d') !== $performedRaw || $performed > new \DateTimeImmutable('today')
        ) {
            throw new \DomainException('Prüfdatum und nächste Fälligkeit sind ungültig.');
        }
        $this->em->wrapInTransaction(function () use ($type, $id, $clubId, $performed, $nextRaw, $reference, $actor): void {
            $asset = EquipmentInventory::physicalAsset($this->inventory->asset($type, $id, $clubId, true));
            if ($nextRaw === '') {
                $interval = $this->inventory->findRecord($asset)?->getInspectionIntervalMonths();
                if ($interval === null) { throw new \DomainException('Nächste Fälligkeit oder Prüfintervall erforderlich.'); }
                $month = $performed->modify('first day of this month')->modify('+'.$interval.' months');
                $day = min((int) $performed->format('d'), (int) $month->format('t'));
                $next = $month->setDate((int) $month->format('Y'), (int) $month->format('m'), $day);
            } else {
                $next = \DateTimeImmutable::createFromFormat('!Y-m-d', $nextRaw);
                if (!$next || $next->format('Y-m-d') !== $nextRaw) { throw new \DomainException('Nächste Fälligkeit ist ungültig.'); }
            }
            if ($next <= $performed) { throw new \DomainException('Nächste Fälligkeit muss nach der Prüfung liegen.'); }
            if ($asset instanceof Tank) {
                if ($asset->getLastCheckDate() && $performed->format('Y-m-d') < $asset->getLastCheckDate()->format('Y-m-d')) {
                    throw new \DomainException('Prüfdatum darf nicht vor der zuletzt erfassten Flaschenprüfung liegen.');
                }
                $asset->setLastCheckDate($performed); $asset->setNextCheckDate($next);
            }
            elseif ($asset instanceof Regulator) { $asset->setNextServiceAt($next); }
            else { $this->inventory->record($asset)->setNextInspectionAt($next); }
            $this->history($asset, 'inspection_recorded', $performed->format('Y-m-d').' / nächste Prüfung '.$next->format('Y-m-d').': '.$reference, $actor);
        });
    }

    public function release(string $type, int $id, int $clubId, string $reason, string $actor): void
    {
        self::text($reason);
        $this->em->wrapInTransaction(function () use ($type, $id, $clubId, $reason, $actor): void {
            $asset = EquipmentInventory::physicalAsset($this->inventory->asset($type, $id, $clubId, true));
            $this->assertReleasable($asset);
            $this->setTechnicalState($asset, 'ready', true);
            $this->history($asset, 'manual_release', $reason, $actor);
        });
    }

    /** The caller must hold the physical asset row lock. */
    public function assertReleasable(EquipmentItem|Regulator|Tank $asset): void
    {
        $asset = EquipmentInventory::physicalAsset($asset);
        if ($this->hasOpenOrder($asset)) { throw new \DomainException('Offenen Wartungsauftrag zuerst abschließen.'); }
        $active = (int) $this->em->createQueryBuilder()->select('COUNT(i.id)')->from(EquipmentBookingItem::class, 'i')
            ->leftJoin('i.equipmentItem', 'linked')
            ->where(($asset instanceof Tank ? '(i.tank = :asset OR linked.tank = :asset)' : 'i.'.EquipmentInventory::field($asset).' = :asset').' AND i.loanStatus = :issued')
            ->setParameter('asset', $asset)->setParameter('issued', 'issued')->getQuery()->getSingleScalarResult();
        if ($asset instanceof EquipmentItem) {
            $active += $this->em->getRepository(EquipmentReservation::class)->count(['equipmentItem' => $asset, 'status' => 'issued']);
        } elseif ($asset instanceof Tank) {
            $active += (int) $this->em->createQueryBuilder()->select('COUNT(r.id)')->from(EquipmentReservation::class, 'r')
                ->join('r.equipmentItem', 'linked')->where('linked.tank = :asset AND r.status = :issued')
                ->setParameter('asset', $asset)->setParameter('issued', 'issued')->getQuery()->getSingleScalarResult();
        }
        if ($active > 0) { throw new \DomainException('Gegenstand ist noch ausgegeben.'); }
        $due = $this->inventory->dueAt($asset);
        $record = $this->inventory->findRecord($asset);
        if ((($asset instanceof Tank || $asset instanceof Regulator || $record?->getInspectionIntervalMonths() !== null) && $due === null)
            || ($due !== null && $due->format('Y-m-d') < date('Y-m-d'))) {
            throw new \DomainException('Prüfung oder Revision fehlt oder ist abgelaufen.');
        }
        if ($asset instanceof EquipmentItem && $asset->getConditionStatus() === 'retired') {
            throw new \DomainException('Ausgemusterte Ausrüstung kann nicht freigegeben werden.');
        }
    }

    public function history(EquipmentItem|Regulator|Tank $asset, string $event, string $description, string $actor): void
    {
        $asset = EquipmentInventory::physicalAsset($asset);
        $this->inventory->record($asset)->record($event, $actor, $description);
        if ($asset instanceof Tank) {
            $this->em->persist(new TankConditionHistory($asset, $event, $asset->getConditionStatus(), $asset->isLendable(), '['.$actor.'] '.$description));
        }
    }

    /** The caller must hold the physical asset row lock. */
    public function setTechnicalState(EquipmentItem|Regulator|Tank $asset, string $condition, bool $lendable): void
    {
        $asset->setConditionStatus($condition);
        $asset->setLendable($lendable);
        if ($asset instanceof Tank) {
            foreach ($this->em->getRepository(EquipmentItem::class)->findBy(['tank' => $asset]) as $linked) {
                $this->em->refresh($linked, LockMode::PESSIMISTIC_WRITE);
                if ($condition === 'ready' && $linked->getConditionStatus() === 'retired') { continue; }
                $linked->setConditionStatus($condition);
                $linked->setLendable($lendable);
            }
        }
    }

    private static function text(string $value): void
    {
        if (mb_strlen(trim($value)) < 10 || mb_strlen($value) > 2000) { throw new \DomainException('Beschreibung muss 10 bis 2000 Zeichen enthalten.'); }
    }
}
