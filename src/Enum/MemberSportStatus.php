<?php

namespace App\Enum;

enum MemberSportStatus: string
{
    case ACTIVE = 'active';
    case PASSIVE = 'passive';
    case PAUSED = 'paused';
    case FORMER = 'former';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::PASSIVE => 'Passiv',
            self::PAUSED => 'Pausiert',
            self::FORMER => 'Beendet',
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