<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_duty_assignment')]
#[ORM\Index(name: 'idx_bar_assignment_member', columns: ['member_id', 'status'])]
class BarDutyAssignment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private BarShift $shift;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Member $member;
    #[ORM\Column(length: 20)] private string $status = 'active';
    #[ORM\Column(length: 20)] private string $attendance = 'pending';
    #[ORM\Column] private int $creditedMinutes = 0;
    #[ORM\Column(type: 'json')] private array $history = [];
    public function __construct(BarShift $shift, Member $member, string $actor)
    {
        $this->shift = $shift; $this->member = $member; $this->record('assigned', 'Dienst übernommen / zugewiesen', $actor);
    }
    public function getId(): ?int { return $this->id; }
    public function getShift(): BarShift { return $this->shift; }
    public function getMember(): Member { return $this->member; }
    public function getStatus(): string { return $this->status; }
    public function getAttendance(): string { return $this->attendance; }
    public function getCreditedMinutes(): int { return $this->creditedMinutes; }
    public function getHistory(): array { return $this->history; }
    public function cancel(string $actor, string $reason): void
    {
        if ($this->status !== 'active' || $this->attendance !== 'pending') { throw new \DomainException('Dienst kann nicht mehr zurückgezogen oder getauscht werden.'); }
        $this->status = 'cancelled'; $this->record('cancelled', $reason, $actor);
    }
    public function confirm(string $attendance, int $minutes, string $reason, string $actor): void
    {
        if ($this->status !== 'active' || !in_array($attendance, ['present', 'absent'], true) || $minutes < 0 || $minutes > 1440
            || ($attendance === 'absent' && $minutes !== 0) || ($attendance === 'present' && $minutes === 0)) { throw new \DomainException('Anwesenheit und geleistete Minuten prüfen (1–1440, bei Abwesenheit 0).'); }
        $this->attendance = $attendance; $this->creditedMinutes = $minutes; $this->record('attendance', $attendance.' / '.$minutes.' Minuten: '.$reason, $actor);
    }
    private function record(string $event, string $reason, string $actor): void
    {
        $this->history[] = ['at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'event' => $event, 'reason' => $reason, 'actor' => $actor];
    }
}
