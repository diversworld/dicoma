<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tank;
use App\Entity\TankConditionHistory;
use Doctrine\ORM\EntityManagerInterface;

final class TankConditionHistoryRecorder
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** Called inside the loan service transaction; flush is owned by the caller. */
    public function recordReturn(Tank $tank, int $bookingId, int $lineId, string $condition, ?string $notes): void
    {
        $this->em->persist(new TankConditionHistory(
            $tank,
            'return',
            $condition,
            $condition === 'ready' ? $tank->isLendable() : false,
            $notes,
            $bookingId,
            $lineId,
        ));
    }
}
