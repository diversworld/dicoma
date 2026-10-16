<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\EquipmentAttribute;
use App\Entity\EquipmentItem;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidEquipmentAttributesValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidEquipmentAttributes) {
            throw new UnexpectedTypeException($constraint, ValidEquipmentAttributes::class);
        }
        if (!$value instanceof EquipmentItem || $value->getCategory() === null) {
            return;
        }

        $category = $value->getCategory();
        $values = $value->getAttributeValues();
        $definitions = $this->entityManager->getRepository(EquipmentAttribute::class)
            ->findBy(['category' => $category], ['id' => 'ASC']);
        $allowed = [];

        foreach ($definitions as $definition) {
            $code = $definition->getCode();
            if ($code === null || $code === '') {
                continue;
            }
            $allowed[$code] = true;
            $present = array_key_exists($code, $values);
            $actual = $present ? $values[$code] : null;
            $empty = !$present || $actual === null || $actual === '';
            if ($definition->isRequired() && $empty) {
                $this->violate(sprintf('Das Merkmal "%s" ist erforderlich.', $definition->getLabel()), $code);
                continue;
            }
            if ($empty) {
                continue;
            }
            $valid = match ($definition->getDataType()) {
                'text' => is_string($actual) && mb_strlen($actual) <= 1000,
                'number' => (is_int($actual) || is_float($actual)) && is_finite((float) $actual),
                'boolean' => is_bool($actual),
                'date' => $this->isIsoDate($actual),
                default => false,
            };
            if (!$valid) {
                $this->violate(sprintf('Ungültiger Wert für "%s".', $definition->getLabel()), $code);
            }
        }
        foreach (array_keys($values) as $code) {
            if (!isset($allowed[$code])) {
                $this->violate(sprintf('Das Merkmal "%s" gehört nicht zur ausgewählten Kategorie.', (string) $code), (string) $code);
            }
        }
    }

    private function isIsoDate(mixed $value): bool
    {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function violate(string $message, string $code): void
    {
        $this->context->buildViolation($message)
            ->atPath('attributeValuesJson')
            ->addViolation();
    }
}
