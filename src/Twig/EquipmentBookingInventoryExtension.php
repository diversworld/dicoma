<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\EquipmentBooking;
use App\Entity\EquipmentItem;
use App\Entity\Regulator;
use App\Entity\Tank;
use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentReservation;
use Doctrine\ORM\EntityManagerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EquipmentBookingInventoryExtension extends AbstractExtension
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('equipment_booking_inventory', [$this, 'inventory'])];
    }

    /** @return array<int, array{id:int, name:string, inventory:string, categoryId:int, category:string}> */
    public function inventory(EquipmentBooking $booking): array
    {
        if ($booking->getStatus() !== 'reserved' || !$booking->getMember()?->getClub()?->getId()) {
            return [];
        }
        $items = $this->em->getRepository(EquipmentItem::class)->findBy([
            'club' => $booking->getMember()->getClub(),
            'lendable' => true,
            'conditionStatus' => 'ready',
            'stockType' => 'individual',
        ], ['name' => 'ASC']);
        $result = [];
        foreach ($items as $item) {
            $category = $item->getCategory();
            if (!$category || !$category->getId()) { continue; }
            if ($this->occupied($booking, 'equipmentItem', $item)) { continue; }
            $result[] = [
                'id' => (int) $item->getId(),
                'name' => (string) $item->getName(),
                'inventory' => (string) $item->getInventoryNumber(),
                'categoryId' => (int) $category->getId(),
                'category' => (string) $category->getName(),
                'type' => 'equipment',
            ];
        }
        foreach ($this->em->getRepository(Regulator::class)->findBy(['club'=>$booking->getMember()->getClub(),'lendable'=>true,'conditionStatus'=>'ready']) as $reg) {
            if (!$reg->getNextServiceAt() || !$booking->getEndsAt() || $reg->getNextServiceAt() < $booking->getEndsAt()->setTime(0,0)) {continue;}
            if ($this->occupied($booking, 'regulator', $reg)) { continue; }
            $result[]=['id'=>$reg->getId(),'name'=>(string)$reg,'inventory'=>(string)$reg->getInventoryNumber(),'categoryId'=>'regulator','category'=>'Atemregler','type'=>'regulator'];
        }
        foreach ($this->em->getRepository(Tank::class)->findBy(['club'=>$booking->getMember()->getClub()]) as $tank) {
            if (!$tank->getNextCheckDate() || !$booking->getEndsAt() || $tank->getNextCheckDate() < $booking->getEndsAt()) {continue;}
            if ($this->occupied($booking, 'tank', $tank)) { continue; }
            $result[]=['id'=>$tank->getId(),'name'=>(string)$tank->getInventory(),'inventory'=>(string)$tank->getInventory(),'categoryId'=>'tank','category'=>'Tauchflaschen','type'=>'tank'];
        }
        return $result;
    }
    private function occupied(EquipmentBooking $booking, string $relation, object $asset): bool
    {
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(EquipmentBookingItem::class, 'i')
            ->join('i.booking', 'b')
            ->where('i.'.$relation.' = :asset')
            ->andWhere('(i.loanStatus = :issued OR (i.loanStatus = :reserved AND b.startsAt < :end AND b.endsAt > :start))')
            ->setParameter('asset', $asset)
            ->setParameter('issued', 'issued')
            ->setParameter('reserved', 'reserved')
            ->setParameter('start', $booking->getStartsAt())
            ->setParameter('end', $booking->getEndsAt())
            ->getQuery()->getSingleScalarResult();
        if ($count > 0) { return true; }
        if ($relation !== 'equipmentItem') { return false; }
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(EquipmentReservation::class, 'r')
            ->where('r.equipmentItem = :asset')
            ->andWhere('(r.status = :issued OR (r.status = :reserved AND r.startsAt < :end AND r.endsAt > :start))')
            ->setParameter('asset', $asset)
            ->setParameter('issued', 'issued')
            ->setParameter('reserved', 'reserved')
            ->setParameter('start', $booking->getStartsAt())
            ->setParameter('end', $booking->getEndsAt())
            ->getQuery()->getSingleScalarResult() > 0;
    }
}
