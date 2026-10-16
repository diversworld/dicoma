<?php

declare(strict_types=1);

namespace App\DiveClub\Inspection\Domain;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Club policy: a successful cylinder inspection is valid for 30 calendar months.
 * Failed inspections must never be passed to this policy as a new valid inspection.
 */
final readonly class InspectionIntervalPolicy
{
    public const INTERVAL_MONTHS = 30;

    public function nextDueDate(DateTimeInterface $successfulInspectionDate): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromInterface($successfulInspectionDate);
        $day = (int) $date->format('j');
        $targetMonth = $date->modify('first day of this month')->modify(sprintf('+%d months', self::INTERVAL_MONTHS));
        $lastDay = (int) $targetMonth->format('t');

        // Clamp to the last valid day of the target month (e.g. 31 January -> 31 July).
        return $targetMonth->setDate(
            (int) $targetMonth->format('Y'),
            (int) $targetMonth->format('n'),
            min($day, $lastDay),
        )->setTime(0, 0, 0);
    }
}
