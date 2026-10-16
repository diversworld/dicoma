<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_sub_type')]
#[Assert\Callback('validateClub')]
class EquipmentSubType
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Club $club = null;
    #[ORM\ManyToOne(targetEntity: EquipmentCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?EquipmentCategory $category = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column]
    private bool $active = true;

    public function getId(): ?int { return $this->id; }
    public function getClub(): ?Club { return $this->club; }
    public function setClub(?Club $club): static { $this->club = $club; return $this; }
    public function getCategory(): ?EquipmentCategory { return $this->category; }
    public function setCategory(?EquipmentCategory $category): static { $this->category = $category; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function __toString(): string { return $this->name ?? ''; }
    public function validateClub(ExecutionContextInterface $context): void
    {
        if ($this->club && $this->category && $this->category->getClub()?->getId() !== $this->club->getId()) {
            $context->buildViolation('Kategorie und Stammdatensatz müssen demselben Verein angehören.')->atPath('category')->addViolation();
        }

    }
}
