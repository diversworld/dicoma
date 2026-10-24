<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
class TeamTraining
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Team $team;
    #[ORM\Column(length: 150)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private string $title = '';
    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt = null;
    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull, Assert\GreaterThan(propertyPath: 'startsAt')]
    private ?\DateTimeImmutable $endsAt = null;
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private string $location = '';
    #[ORM\Column(options: ['default' => false])]
    private bool $cancelled = false;

    public function __construct(Team $team) { $this->team = $team; }
    public function getId(): ?int { return $this->id; }
    public function getTeam(): Team { return $this->team; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $value): void { $this->title = trim($value); }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): void { $this->startsAt = $value; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $value): void { $this->endsAt = $value; }
    public function getLocation(): string { return $this->location; }
    public function setLocation(string $value): void { $this->location = trim($value); }
    public function isCancelled(): bool { return $this->cancelled; }
    public function setCancelled(bool $value): void { $this->cancelled = $value; }
}
