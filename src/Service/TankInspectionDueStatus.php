<?php

declare(strict_types=1);

namespace App\Service;

/** Gemeinsame Fälligkeitsanzeige; 90 Tage Vorlauf, ohne Datenbankänderung. */
final class TankInspectionDueStatus
{
    public static function label(?\DateTimeInterface $next, ?\DateTimeInterface $today = null): string
    {
        if ($next === null) { return 'Kein Termin hinterlegt'; }
        $todayDate = \DateTimeImmutable::createFromInterface($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        $due = \DateTimeImmutable::createFromInterface($next)->setTime(0, 0);
        if ($due < $todayDate) { return 'Überfällig'; }
        if ($due <= $todayDate->modify('+90 days')) { return 'Bald fällig'; }
        return 'Aktuell';
    }
}
