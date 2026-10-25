<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_shift')]
#[ORM\Index(name: 'idx_bar_shift_period', columns: ['club_id', 'starts_at', 'ends_at'])]
#[ORM\UniqueConstraint(name: 'uniq_bar_definition_start', columns: ['definition_id', 'starts_at'])]
class BarShift
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Club $club;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private BarShiftDefinition $definition;
    #[ORM\Column(length: 120)] private string $title;
    #[ORM\Column(length: 180)] private string $location;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $startsAt;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $endsAt;
    #[ORM\Column] private int $capacity;
    #[ORM\Column(length: 20)] private string $status = 'open';
    #[ORM\Column(type: 'json')] private array $history = [];
    public function __construct(BarShiftDefinition $definition, \DateTimeImmutable $start)
    {
        $this->definition = $definition; $this->club = $definition->getClub();
        $this->title = $definition->getTitle(); $this->location = $definition->getLocation(); $this->capacity = $definition->getCapacity();
        $this->startsAt = $start; $this->endsAt = $start->modify('+'.$definition->getDurationMinutes().' minutes');
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getDefinition(): BarShiftDefinition { return $this->definition; }
    public function getTitle(): string { return $this->title; }
    public function getLocation(): string { return $this->location; }
    public function getStartsAt(): \DateTimeImmutable { return $this->startsAt; }
    public function getEndsAt(): \DateTimeImmutable { return $this->endsAt; }
    public function getCapacity(): int { return $this->capacity; }
    public function getStatus(): string { return $this->status; }
    public function getHistory(): array { return $this->history; }
    public function finish(string $status, string $reason, string $actor): void
    {
        if ($this->status !== 'open' || !in_array($status, ['cancelled', 'completed'], true)) { throw new \DomainException('Schicht ist bereits abgeschlossen oder abgesagt.'); }
        $this->status = $status; $this->record($status, $reason, $actor);
    }
    public function record(string $event, string $reason, string $actor): void
    {
        $this->history[] = ['at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'event' => $event, 'actor' => $actor, 'reason' => $reason];
    }
}
