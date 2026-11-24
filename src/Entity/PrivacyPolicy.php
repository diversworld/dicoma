<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class PrivacyPolicy
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(options: ['default' => 3])]
    private int $memberRetentionYears = 3;
    #[ORM\Column(options: ['default' => 365])]
    private int $messageRetentionDays = 365;
    #[ORM\Column(options: ['default' => 10])]
    private int $financialRetentionYears = 10;
    #[ORM\Column(options: ['default' => 3])]
    private int $auditRetentionYears = 3;
    #[ORM\Column(options: ['default' => false])]
    private bool $purgeEnabled = false;
    public function __construct(Club $club) { $this->club = $club; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getMemberRetentionYears(): int { return $this->memberRetentionYears; }
    public function setMemberRetentionYears(int $value): void { $this->guard($value, 1, 10); $this->memberRetentionYears = $value; }
    public function getMessageRetentionDays(): int { return $this->messageRetentionDays; }
    public function setMessageRetentionDays(int $value): void { $this->guard($value, 30, 3650); $this->messageRetentionDays = $value; }
    public function getFinancialRetentionYears(): int { return $this->financialRetentionYears; }
    public function setFinancialRetentionYears(int $value): void { $this->guard($value, 10, 30); $this->financialRetentionYears = $value; }
    public function getAuditRetentionYears(): int { return $this->auditRetentionYears; }
    public function setAuditRetentionYears(int $value): void { $this->guard($value, 1, 10); $this->auditRetentionYears = $value; }
    public function isPurgeEnabled(): bool { return $this->purgeEnabled; }
    public function setPurgeEnabled(bool $purgeEnabled): void { $this->purgeEnabled = $purgeEnabled; }
    private function guard(int $value, int $min, int $max): void { if ($value < $min || $value > $max) { throw new \DomainException('Ungültige Aufbewahrungsfrist.'); } }
}
