<?php

declare(strict_types=1);

namespace App\Service;

/** Berechnet 2,5 Jahre als 30 Kalendermonate ab dem tatsächlichen Prüfdatum. */
final class TuvNextInspectionDateCalculator
{
    public function calculate(\DateTimeImmutable $inspectionDate): \DateTimeImmutable
    {
        // Monatsende begrenzen: 31.08.2026 + 30 Monate = 28.02.2029,
        // nicht 03.03.2029 (PHP-DateInterval-Überlauf).
        $start = $inspectionDate->setTime(0, 0);
        $monthStart = $start->modify('first day of this month')->modify('+30 months');
        $targetDay = min((int) $start->format('j'), (int) $monthStart->format('t'));

        return $monthStart->setDate(
            (int) $monthStart->format('Y'),
            (int) $monthStart->format('n'),
            $targetDay
        );
    }
}
