<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_model')]
#[ORM\UniqueConstraint(name: 'uniq_eq_model_club_category_manufacturer_name', columns: ['club_id', 'category_id', 'manufacturer_id', 'name'])]
#[Assert\Callback('validateRelations')]
class EquipmentModel
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
    #[ORM\ManyToOne(targetEntity: EquipmentManufacturer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?EquipmentManufacturer $manufacturer = null;
    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    private ?string $name = null;
    #[ORM\Column]
    private bool $active = true;
    public function getId(): ?int { return $this->id; }
    public function getClub(): ?Club { return $this->club; }
    public function setClub(?Club $v): static { $this->club = $v; return $this; }
    public function getCategory(): ?EquipmentCategory { return $this->category; }
    public function setCategory(?EquipmentCategory $v): static { $this->category = $v; return $this; }
    public function getManufacturer(): ?EquipmentManufacturer { return $this->manufacturer; }
    public function setManufacturer(?EquipmentManufacturer $v): static { $this->manufacturer = $v; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): static { $this->name = $v; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $v): static { $this->active = $v; return $this; }
    public function __toString(): string { return $this->name ?? ''; }
    public function validateRelations(ExecutionContextInterface $context): void
    {
        if (!$this->club) return;
        foreach (['category' => $this->category, 'manufacturer' => $this->manufacturer] as $field => $reference) {
            if ($reference && $reference->getClub()?->getId() !== $this->club->getId()) {
                $context->buildViolation('Stammdaten müssen zum selben Verein gehören.')->atPath($field)->addViolation();
            }
        }
    }
}
