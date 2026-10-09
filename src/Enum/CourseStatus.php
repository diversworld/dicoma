<?php

namespace App\Enum;

enum CourseStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf',
            self::OPEN => 'Anmeldung geöffnet',
            self::RUNNING => 'Läuft',
            self::COMPLETED => 'Abgeschlossen',
            self::CANCELLED => 'Abgesagt',
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