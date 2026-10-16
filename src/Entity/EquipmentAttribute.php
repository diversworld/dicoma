<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_attribute')]
#[ORM\UniqueConstraint(name: 'uniq_equipment_attribute_category_code', columns: ['category_id', 'code'])]
class EquipmentAttribute
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EquipmentCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?EquipmentCategory $category = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank]
    private ?string $code = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private ?string $label = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['text', 'number', 'boolean', 'date'])]
    private string $dataType = 'text';

    #[ORM\Column]
    private bool $required = false;

    public function __toString(): string { return $this->label ?? 'Neues Merkmal'; }
    public function getId(): ?int { return $this->id; }
    public function getCategory(): ?EquipmentCategory { return $this->category; }
    public function setCategory(?EquipmentCategory $value): static { $this->category = $value; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $value): static { $this->code = $value; return $this; }
    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $value): static { $this->label = $value; return $this; }
    public function getDataType(): string { return $this->dataType; }
    public function setDataType(string $value): static { $this->dataType = $value; return $this; }
    public function isRequired(): bool { return $this->required; }
    public function setRequired(bool $value): static { $this->required = $value; return $this; }
}
