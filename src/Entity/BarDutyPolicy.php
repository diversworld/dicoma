<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_duty_policy')]
#[ORM\UniqueConstraint(name: 'uniq_bar_policy_year', columns: ['club_id', 'year'])]
class BarDutyPolicy
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Club $club;
    #[ORM\Column] private int $year;
    #[ORM\Column] private int $requiredMinutes;
    #[ORM\Column] private bool $cashEnabled;
    #[ORM\Column(type: 'json')] private array $history = [];

    public function __construct(Club $club, int $year) { $this->club = $club; $this->year = $year; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getYear(): int { return $this->year; }
    public function getRequiredMinutes(): int { return $this->requiredMinutes; }
    public function isCashEnabled(): bool { return $this->cashEnabled; }
    public function getHistory(): array { return $this->history; }
    public function configure(int $minutes, bool $cashEnabled, string $actor, string $reason): void
    {
        if ($minutes < 0 || $minutes > 60000 || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 500) { throw new \DomainException('Pflichtminuten (0–60000) und Begründung (10–500 Zeichen) prüfen.'); }
        $this->requiredMinutes = $minutes; $this->cashEnabled = $cashEnabled;
        $this->history[] = ['at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'actor' => $actor, 'minutes' => $minutes, 'cash' => $cashEnabled, 'reason' => trim($reason)];
    }
}
