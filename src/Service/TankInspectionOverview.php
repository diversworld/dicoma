<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;
use Doctrine\ORM\EntityManagerInterface;

/** Read-only overview. An unknown next check date is never treated as valid. */
final class TankInspectionOverview
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function getOverview(): array
    {
        $today = new \DateTimeImmutable('today');
        $soon = $today->modify('+30 days');
        $counts = ['total' => 0, 'overdue' => 0, 'dueSoon' => 0, 'valid' => 0, 'unknown' => 0];
        $attention = [];
        foreach ($this->em->getRepository(Tank::class)->findAll() as $tank) {
            ++$counts['total'];
            $date = $tank->getNextCheckDate();
            if ($date === null) {
                ++$counts['unknown'];
                $status = 'Unbekannt';
            } elseif ($date->format('Y-m-d') < $today->format('Y-m-d')) {
                ++$counts['overdue'];
                $status = 'Überfällig';
            } elseif ($date->format('Y-m-d') <= $soon->format('Y-m-d')) {
                ++$counts['dueSoon'];
                $status = 'Innerhalb von 30 Tagen';
            } else {
                ++$counts['valid'];
                continue;
            }
            $attention[] = ['id' => $tank->getId(), 'inventory' => $tank->getInventory(), 'serial' => $tank->getSerialnumber(), 'date' => $date, 'status' => $status];
        }
        usort($attention, static fn(array $a, array $b): int => ($a['date']?->format('Y-m-d') ?? '0000-00-00') <=> ($b['date']?->format('Y-m-d') ?? '0000-00-00'));
        return ['counts' => $counts, 'attention' => $attention];
    }
}
