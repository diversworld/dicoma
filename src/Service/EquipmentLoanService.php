<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentReservation;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentLoanService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipmentAssetAvailability $availability,
        private readonly EquipmentMaintenanceService $maintenance,
        private readonly EquipmentInventory $inventory,
    ) {}

    public function issue(int $id): void
    {
        $this->em->wrapInTransaction(function () use ($id): void {
            $reservation = $this->lockReservation($id);
            if ($reservation->getStatus() !== 'reserved') {
                throw new \DomainException('Nur reservierte Ausruestung kann ausgegeben werden.');
            }
            $item = $reservation->getEquipmentItem();
            if ($item === null || !$item->isLendable() || $item->getConditionStatus() !== 'ready') {
                throw new \DomainException('Ausrustung ist nicht verleihbar.');
            }
            $clubId = $reservation->getMember()?->getClub()?->getId();
            if (!$clubId || !$reservation->getStartsAt() || !$reservation->getEndsAt()) { throw new \DomainException('Mitglied oder Zeitraum fehlt.'); }
            if ($this->inventory->requiresApproval($item)) { throw new \DomainException('Genehmigungspflichtige Altreservierung stornieren und im konsolidierten Verleih neu anlegen.'); }
            $now = new \DateTimeImmutable();
            if ($reservation->getStartsAt() > $now || $reservation->getEndsAt() <= $now) { throw new \DomainException('Ausgabe ist nur innerhalb des reservierten Zeitraums möglich.'); }
            $this->availability->assertAvailable($item, $clubId, $reservation->getStartsAt(), $reservation->getEndsAt(), null, $reservation->getId());
            $reservation->setConditionAtIssue($item->getConditionStatus());
            $reservation->setIssuedAt(new \DateTimeImmutable());
            $reservation->setStatus('issued');
        });
    }

    public function returnItem(int $id, string $condition, ?string $notes = null): void
    {
        if (!in_array($condition, ['ready', 'maintenance', 'damaged'], true)) {
            throw new \InvalidArgumentException('Ungueltiger Rueckgabezustand.');
        }
        if (mb_strlen($notes ?? '') > 2000 || ($condition !== 'ready' && mb_strlen(trim($notes ?? '')) < 10)) {
            throw new \DomainException('Bei Defekt oder Wartung ist eine Beschreibung mit 10 bis 2000 Zeichen erforderlich.');
        }
        $this->em->wrapInTransaction(function () use ($id, $condition, $notes): void {
            $reservation = $this->lockReservation($id);
            if ($reservation->getStatus() !== 'issued') {
                throw new \DomainException('Nur ausgegebene Ausruestung kann zurueckgenommen werden.');
            }
            $item = $reservation->getEquipmentItem();
            if ($item === null) { throw new \DomainException('Inventarartikel fehlt.'); }
            $physical = EquipmentInventory::physicalAsset($item);
            $this->em->refresh($physical, LockMode::PESSIMISTIC_WRITE);
            if ($physical !== $item) { $this->em->refresh($item, LockMode::PESSIMISTIC_WRITE); }
            $reservation->setReturnedAt(new \DateTimeImmutable());
            $reservation->setConditionAtReturn($condition);
            $reservation->setReturnNotes($notes);
            $reservation->setStatus('returned');
            if ($condition !== 'ready') {
                // For bulk stock, this conservatively blocks the whole item until inspected.
                $item->setConditionStatus($condition);
                $item->setLendable(false);
                $physical->setConditionStatus($condition);
                $physical->setLendable(false);
                if (!$this->maintenance->hasOpenOrder($item)) {
                    $this->em->persist(new \App\Entity\TankMaintenanceOrder($physical, (string) $notes, $condition === 'damaged' ? 'defect' : 'maintenance', 'Gerätewart', 'Altreservierung #'.$id));
                }
            }
            $this->maintenance->history($item, 'returned', $condition.': '.($notes ?? ''), 'Altreservierung #'.$id);
        });
    }

    public function cancel(int $id): void
    {
        $this->em->wrapInTransaction(function () use ($id): void {
            $reservation = $this->lockReservation($id);
            if ($reservation->getStatus() !== 'reserved') {
                throw new \DomainException('Nur offene Reservierungen koennen storniert werden.');
            }
            $reservation->setStatus('cancelled');
        });
    }

    private function lockReservation(int $id): EquipmentReservation
    {
        $reservation = $this->em->find(EquipmentReservation::class, $id, LockMode::PESSIMISTIC_WRITE);
        if (!$reservation instanceof EquipmentReservation) {
            throw new \DomainException('Reservierung nicht gefunden.');
        }
        $this->em->refresh($reservation, LockMode::PESSIMISTIC_WRITE);
        return $reservation;
    }
}
