<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_division_contribution', columns: ['division_id', 'year'])]
#[ORM\Index(name: 'IDX_2048BE2F41859289', columns: ['division_id'])]
class DivisionContribution
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Sport $division;
    #[ORM\Column]
    private int $year;
    #[ORM\Column]
    private int $annualCents;
    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    public function __construct(Sport $division, int $year, int $annualCents)
    {
        if ($year < 2000 || $year > 2200 || $annualCents < 0 || $annualCents > 100000000) {
            throw new \DomainException('Ungültiger Spartenzuschlag.');
        }
        $this->division = $division; $this->year = $year; $this->annualCents = $annualCents;
    }
    public function getId(): ?int { return $this->id; }
    public function getDivision(): Sport { return $this->division; }
    public function getYear(): int { return $this->year; }
    public function getAnnualCents(): int { return $this->annualCents; }
    public function setAnnualCents(int $value): void { $this->annualCents = $value; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $value): void { $this->active = $value; }
}
