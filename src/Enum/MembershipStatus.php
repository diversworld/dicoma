<?php

namespace App\Enum;

enum MembershipStatus: string
{
    case ACTIVE = 'active';
    case PASSIVE = 'passive';
    case HONORARY = 'honorary';
    case PROSPECT = 'prospect';
    case SUSPENDED = 'suspended';
    case FORMER = 'former';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Aktiv',
            self::PASSIVE => 'Passiv',
            self::HONORARY => 'Ehrenmitglied',
            self::PROSPECT => 'Anwärter',
            self::SUSPENDED => 'Ruhend',
            self::FORMER => 'Ausgetreten',
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