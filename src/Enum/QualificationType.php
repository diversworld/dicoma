<?php

namespace App\Enum;

enum QualificationType: string
{
    case DIVING_CERTIFICATION = 'diving_certification';
    case SPECIALTY = 'specialty';
    case INSTRUCTOR = 'instructor';
    case TRAINER = 'trainer';
    case FIRST_AID = 'first_aid';
    case OXYGEN = 'oxygen';
    case LIFEGUARD = 'lifeguard';
    case COMPRESSOR = 'compressor';
    case EQUIPMENT_TECHNICIAN = 'equipment_technician';
    case OFFICIAL = 'official';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::DIVING_CERTIFICATION => 'Tauchbrevet',
            self::SPECIALTY => 'Spezialkurs',
            self::INSTRUCTOR => 'Tauchlehrer/in',
            self::TRAINER => 'Trainer/in',
            self::FIRST_AID => 'Erste Hilfe / HLW',
            self::OXYGEN => 'Sauerstoff',
            self::LIFEGUARD => 'Rettungsschwimmer/in',
            self::COMPRESSOR => 'Kompressorberechtigung',
            self::EQUIPMENT_TECHNICIAN => 'Gerätetechnik',
            self::OFFICIAL => 'Funktionär / Schiedsrichter',
            self::OTHER => 'Sonstige',
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