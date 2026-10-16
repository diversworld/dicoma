<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EquipmentBooking;
use App\Entity\EquipmentBookingItem;
use App\Entity\EquipmentItem;
use App\Entity\Regulator;
use App\Entity\Tank;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/** Adds a single inventoried asset to an existing, still reserved booking. */
final class EquipmentBookingPositionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EquipmentAssetAvailability $availability,
    ) {}

    public function add(int $bookingId, int $itemId, string $type = 'equipment'): void
    {
        $classes = [
            'equipment' => EquipmentItem::class,
            'regulator' => Regulator::class,
            'tank' => Tank::class,
        ];
        if (!isset($classes[$type]) || $bookingId < 1 || $itemId < 1) {
            throw new \DomainException('Ungueltiger Inventartyp oder Datensatz.');
        }

        $this->em->wrapInTransaction(function () use ($bookingId, $itemId, $type, $classes): void {
            $booking = $this->em->find(EquipmentBooking::class, $bookingId, LockMode::PESSIMISTIC_WRITE);
            if (!$booking || $booking->getStatus() !== 'reserved') {
                throw new \DomainException('Nur offene Reservierungen koennen erweitert werden.');
            }
            $clubId = $booking->getMember()?->getClub()?->getId();
            $start = $booking->getStartsAt();
            $end = $booking->getEndsAt();
            if (!$clubId || !$start || !$end || $start >= $end) {
                throw new \DomainException('Verein oder Reservierungszeitraum ist ungueltig.');
            }

            $asset = $this->em->find($classes[$type], $itemId, LockMode::PESSIMISTIC_WRITE);
            if (!$asset) {
                throw new \DomainException('Inventargegenstand nicht gefunden.');
            }
            $field = [
                'equipment' => 'equipmentItem',
                'regulator' => 'regulator',
                'tank' => 'tank',
            ][$type];
            $alreadyInBooking = (int) $this->em->createQueryBuilder()
                ->select('COUNT(i.id)')
                ->from(EquipmentBookingItem::class, 'i')
                ->where('i.booking = :booking')
                ->andWhere('i.'.$field.' = :asset')
                ->setParameter('booking', $booking)
                ->setParameter('asset', $asset)
                ->getQuery()->getSingleScalarResult();
            if ($alreadyInBooking > 0) {
                throw new \DomainException('Dieser Gegenstand ist bereits in der Reservierung.');
            }

            // Shared check also covers other bookings, legacy equipment reservations,
            // the asset's club, condition and the applicable inspection/service date.
            $this->availability->assertAvailable($asset, $clubId, $start, $end);

            $line = new EquipmentBookingItem();
            if ($asset instanceof EquipmentItem) {
                $line->setEquipmentItem($asset);
            } elseif ($asset instanceof Regulator) {
                $line->setRegulator($asset);
            } else {
                $line->setTank($asset);
            }
            $line->setQuantity(1);
            $booking->addItem($line);
            $this->em->persist($line);
            $this->em->flush();
        });
    }
}
