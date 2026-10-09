<?php

namespace App\Enum;

enum BookingAttendanceStatus: string
{
    case PLANNED = 'planned';
    case PRESENT = 'present';
    case EXCUSED = 'excused';
    case ABSENT = 'absent';
    case MAKEUP_REQUIRED = 'makeup_required';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Geplant',
            self::PRESENT => 'Anwesend',
            self::EXCUSED => 'Entschuldigt',
            self::ABSENT => 'Unentschuldigt',
            self::MAKEUP_REQUIRED => 'Nachholtermin erforderlich',
        };
    }

    /**
     * @return array<string, self>
     */
    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}