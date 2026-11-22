<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_contribution_club_name', columns: ['club_id', 'name'])]
#[ORM\Index(name: 'idx_contribution_class_active_period', columns: ['club_id', 'active', 'billing_period'])]
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

    #[ORM\Column(length: 10, options: ['default' => 'yearly'])]
    private string $billingPeriod = 'yearly';

    #[ORM\Column(options: ['default' => 0])]
    private int $monthlyCents = 0;

    #[ORM\Column(length: 30, options: ['default' => 'standard'])]
    private string $memberCategory = 'standard';

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
    public function getBillingPeriod(): string { return $this->billingPeriod; }
    public function setBillingPeriod(string $value): void
    {
        if (!in_array($value, ['yearly', 'monthly'], true)) { throw new \DomainException('Unbekannter Beitragsrhythmus.'); }
        $this->billingPeriod = $value;
    }
    public function getMonthlyCents(): int { return $this->monthlyCents; }
    public function setMonthlyCents(int $value): void { if ($value < 0 || $value > 100000000) { throw new \DomainException('Ungültiger Monatsbeitrag.'); } $this->monthlyCents = $value; }
    public function getMemberCategory(): string { return $this->memberCategory; }
    public function setMemberCategory(string $value): void
    {
        if (!in_array($value, ['standard', 'youth', 'honorary', 'supporter', 'family'], true)) { throw new \DomainException('Unbekannte Beitragskategorie.'); }
        $this->memberCategory = $value;
    }
    public function getPeriodCents(): int { return $this->billingPeriod === 'monthly' ? $this->monthlyCents : $this->annualCents; }
    public function validateBilling(): void
    {
        if ($this->getPeriodCents() < 0 || ($this->active && $this->getPeriodCents() === 0 && $this->memberCategory !== 'honorary')) {
            throw new \DomainException('Eine aktive Beitragsklasse benötigt einen Betrag; Ehrenmitgliedschaft darf beitragsfrei sein.');
        }
    }
}
