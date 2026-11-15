<?php

declare(strict_types=1);

namespace App\Enum;

enum PermissionModule: string
{
    case MEMBERSHIP = 'membership';
    case FINANCE = 'finance';
    case TRAINING = 'training';
    case SPORT = 'sport';
    case EQUIPMENT = 'equipment';
    case EQUIPMENT_CASH = 'equipment_cash';
    case BAR_DUTY = 'bar_duty';
    case BAR_CASH = 'bar_cash';
    case WEBSITE = 'website';
    case FILES = 'files';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::MEMBERSHIP => 'Mitglieder und Mitgliedschaft',
            self::FINANCE => 'Finanzen und Beiträge',
            self::TRAINING => 'Ausbildung und Kurse',
            self::SPORT => 'Sport und Wettkampf',
            self::EQUIPMENT => 'Ausrüstung und TÜV',
            self::EQUIPMENT_CASH => 'Gerätekasse',
            self::BAR_DUTY => 'Thekendienst',
            self::BAR_CASH => 'Thekenkasse',
            self::WEBSITE => 'Website und Veranstaltungen',
            self::FILES => 'Dateiverwaltung',
            self::SYSTEM => 'System- und Rollenverwaltung',
        };
    }

    public function isDelegable(): bool
    {
        return match ($this) {
            self::MEMBERSHIP, self::TRAINING, self::SPORT, self::EQUIPMENT, self::WEBSITE, self::FILES => true,
            self::FINANCE, self::EQUIPMENT_CASH, self::BAR_DUTY, self::BAR_CASH, self::SYSTEM => false,
        };
    }
}
