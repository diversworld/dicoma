<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_location')]
#[ORM\UniqueConstraint(name: 'uniq_equipment_location_club_name', columns: ['club_id', 'name'])]
#[ORM\Index(name: 'IDX_6E5D0DED61190A32', columns: ['club_id'])]
class EquipmentLocation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\Column(length: 180), Assert\NotBlank, Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function __construct(Club $club) { $this->club = $club; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): static { $this->name = trim($value); return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): static { $this->active = $value; return $this; }
    public function __toString(): string { return $this->name; }
}
