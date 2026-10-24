<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_contribution_club_name', columns: ['club_id', 'name'])]
class ContributionClass
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank, Assert\Length(max: 100)]
    private string $name = '';
    #[ORM\Column]
    #[Assert\Range(min: 0, max: 100000000)]
    private int $annualCents = 0;
    #[ORM\Column]
    private bool $family = false;
    #[ORM\Column]
    private bool $active = true;

    public function __construct(Club $club) { $this->club = $club; }
    public function __toString(): string { return $this->name; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): void { $this->name = trim($value); }
    public function getAnnualCents(): int { return $this->annualCents; }
    public function setAnnualCents(int $value): void { $this->annualCents = $value; }
    public function isFamily(): bool { return $this->family; }
    public function setFamily(bool $value): void { $this->family = $value; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): void { $this->active = $value; }
}
