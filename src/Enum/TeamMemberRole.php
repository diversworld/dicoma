<?php

namespace App\Enum;

enum TeamMemberRole: string
{
    case MEMBER = 'member';
    case PLAYER = 'player';
    case TRAINER = 'trainer';
    case ASSISTANT_TRAINER = 'assistant_trainer';
    case TEAM_LEADER = 'team_leader';

    public function label(): string
    {
        return match ($this) {
            self::MEMBER => 'Mitglied',
            self::PLAYER => 'Spieler/in',
            self::TRAINER => 'Trainer/in',
            self::ASSISTANT_TRAINER => 'Co-Trainer/in',
            self::TEAM_LEADER => 'Mannschaftsführer/in',
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