<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AvailableEquipmentReservation extends Constraint
{
    public string $message = 'Die Ausrüstung ist für diesen Zeitraum nicht in der gewünschten Menge verfügbar.';
    public function getTargets(): string { return self::CLASS_CONSTRAINT; }
}
