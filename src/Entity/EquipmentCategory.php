<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_category')]
#[ORM\UniqueConstraint(name: 'uniq_equipment_category_club_code', columns: ['club_id', 'code'])]
class EquipmentCategory
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Club $club = null;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank, Assert\Length(max: 60)]
    private ?string $code = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private ?string $name = null;

    #[ORM\Column]
    private bool $active = true;

    public function __toString(): string { return ($this->name ?? 'Neue Kategorie').' ['.($this->club?->__toString() ?? '-').']'; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): ?Club { return $this->club; }
    public function setClub(?Club $value): static { $this->club = $value; return $this; }
    public function getCode(): ?string { return $this->code; }
    public function setCode(?string $value): static { $this->code = $value; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $value): static { $this->name = $value; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): static { $this->active = $value; return $this; }
}
