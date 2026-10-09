<?php
namespace App\Enum;

enum TrainingUnitResultStatus: string
{
    case OPEN = 'open';
    case PASSED = 'passed';
    case FAILED = 'failed';
    case REPEAT_REQUIRED = 'repeat_required';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Offen',
            self::PASSED => 'Bestanden',
            self::FAILED => 'Nicht bestanden',
            self::REPEAT_REQUIRED => 'Wiederholung erforderlich',
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
