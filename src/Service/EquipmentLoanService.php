<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentReservation;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class EquipmentLoanService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

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
            // Lock the item to serialize with reservation creation and other issues.
            $this->em->lock($item, LockMode::PESSIMISTIC_WRITE);
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
        $this->em->wrapInTransaction(function () use ($id, $condition, $notes): void {
            $reservation = $this->lockReservation($id);
            if ($reservation->getStatus() !== 'issued') {
                throw new \DomainException('Nur ausgegebene Ausruestung kann zurueckgenommen werden.');
            }
            $item = $reservation->getEquipmentItem();
            if ($item === null) { throw new \DomainException('Inventarartikel fehlt.'); }
            $this->em->lock($item, LockMode::PESSIMISTIC_WRITE);
            $reservation->setReturnedAt(new \DateTimeImmutable());
            $reservation->setConditionAtReturn($condition);
            $reservation->setReturnNotes($notes);
            $reservation->setStatus('returned');
            if ($condition !== 'ready') {
                // For bulk stock, this conservatively blocks the whole item until inspected.
                $item->setConditionStatus($condition);
            }
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
        return $reservation;
    }
}
