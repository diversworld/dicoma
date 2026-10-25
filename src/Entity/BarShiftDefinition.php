<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_shift_definition')]
class BarShiftDefinition
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Club $club;
    #[ORM\Column(length: 120)] private string $title;
    #[ORM\Column(length: 180)] private string $location;
    #[ORM\Column(length: 5)] private string $startTime;
    #[ORM\Column] private int $durationMinutes;
    #[ORM\Column] private int $capacity;
    #[ORM\Column] private bool $active = true;
    public function __construct(Club $club, string $title, string $location, string $time, int $duration, int $capacity)
    {
        if (trim($title) === '' || mb_strlen($title) > 120 || trim($location) === '' || mb_strlen($location) > 180
            || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $time) || $duration < 1 || $duration > 1440 || $capacity < 1 || $capacity > 50) { throw new \DomainException('Schichtdefinition ungültig (Dauer 1–1440 Minuten, Plätze 1–50).'); }
        $this->club = $club; $this->title = trim($title); $this->location = trim($location);
        $this->startTime = $time; $this->durationMinutes = $duration; $this->capacity = $capacity;
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getTitle(): string { return $this->title; }
    public function getLocation(): string { return $this->location; }
    public function getStartTime(): string { return $this->startTime; }
    public function getDurationMinutes(): int { return $this->durationMinutes; }
    public function getCapacity(): int { return $this->capacity; }
    public function isActive(): bool { return $this->active; }
    public function deactivate(): void { $this->active = false; }
}
