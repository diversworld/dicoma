<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\EquipmentReservation;
use App\Service\EquipmentReservationService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class AvailableEquipmentReservationValidator extends ConstraintValidator
{
    public function __construct(private readonly EquipmentReservationService $service) {}
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$value instanceof EquipmentReservation || !$constraint instanceof AvailableEquipmentReservation) { return; }
        if ($value->getEquipmentItem() === null || $value->getMember() === null || $value->getStartsAt() === null || $value->getEndsAt() === null) { return; }
        if (!$this->service->available($value)) {
            $this->context->buildViolation($constraint->message)->atPath('quantity')->addViolation();
        }
    }
}
