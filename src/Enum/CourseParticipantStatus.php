<?php

namespace App\Enum;

enum CourseParticipantStatus: string
{
    case REGISTERED = 'registered';
    case ACTIVE = 'active';
    case PASSED = 'passed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Angemeldet',
            self::ACTIVE => 'In Ausbildung',
            self::PASSED => 'Bestanden',
            self::FAILED => 'Nicht bestanden',
            self::CANCELLED => 'Abgebrochen / storniert',
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