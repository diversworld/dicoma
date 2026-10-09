<?php

namespace App\Enum;

enum TeamType: string
{
    case TEAM = 'team';
    case TRAINING_GROUP = 'training_group';
    case YOUTH = 'youth';
    case EDUCATION = 'education';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TEAM => 'Mannschaft',
            self::TRAINING_GROUP => 'Trainingsgruppe',
            self::YOUTH => 'Jugendgruppe',
            self::EDUCATION => 'Ausbildungsgruppe',
            self::OTHER => 'Sonstige Gruppe',
        };
    }

    public static function choices(): array
    {
        $choices = [];

        foreach (self::cases() as $case) {
            $choices[$case->label()] = $case;
        }

        return $choices;
    }
}