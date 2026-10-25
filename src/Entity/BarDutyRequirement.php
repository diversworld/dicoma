<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_duty_requirement')]
#[ORM\UniqueConstraint(name: 'uniq_bar_member_year', columns: ['member_id', 'year'])]
class BarDutyRequirement
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Member $member;
    #[ORM\Column] private int $year;
    #[ORM\Column(nullable: true)] private ?int $requiredMinutes = null;
    #[ORM\Column(type: 'json')] private array $history = [];
    public function __construct(Member $member, int $year) { $this->member = $member; $this->year = $year; }
    public function getId(): ?int { return $this->id; }
    public function getMember(): Member { return $this->member; }
    public function getYear(): int { return $this->year; }
    public function getRequiredMinutes(): ?int { return $this->requiredMinutes; }
    public function getHistory(): array { return $this->history; }
    public function change(?int $minutes, string $reason, string $actor): void
    {
        if (($minutes !== null && ($minutes < 0 || $minutes > 60000)) || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 500) { throw new \DomainException('Ausnahme und Begründung prüfen.'); }
        $this->requiredMinutes = $minutes;
        $this->history[] = ['at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'actor' => $actor, 'minutes' => $minutes, 'reason' => trim($reason)];
    }
}
