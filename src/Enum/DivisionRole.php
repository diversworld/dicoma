<?php

declare(strict_types=1);

namespace App\Enum;

enum DivisionRole: string
{
    case LEADER = 'leader';
    case TRAINER = 'trainer';
    case EQUIPMENT_MANAGER = 'equipment_manager';
    case YOUTH_OFFICER = 'youth_officer';

    public function label(): string
    {
        return match ($this) {
            self::LEADER => 'Spartenleitung',
            self::TRAINER => 'Trainer/in',
            self::EQUIPMENT_MANAGER => 'Gerätewart/in',
            self::YOUTH_OFFICER => 'Jugendwart/in',
        };
    }
}
