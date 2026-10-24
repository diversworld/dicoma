<?php

declare(strict_types=1);

namespace App\Enum;

enum TournamentStatus: string
{
    case DRAFT = 'draft';
    case OPEN = 'open';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Entwurf', self::OPEN => 'Meldung geöffnet',
            self::RUNNING => 'Läuft', self::COMPLETED => 'Abgeschlossen', self::CANCELLED => 'Abgesagt',
        };
    }
}
