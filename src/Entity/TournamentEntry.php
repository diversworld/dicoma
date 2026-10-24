<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_tournament_team', columns: ['tournament_id', 'team_id'])]
class TournamentEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Tournament $tournament;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?Team $team = null;
    #[ORM\Column(length: 150)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private string $name = '';
    #[ORM\Column(length: 40)]
    #[Assert\NotBlank, Assert\Length(max: 40)]
    private string $groupName = 'A';
    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email, Assert\Length(max: 180)]
    private ?string $contactEmail = null;
    #[ORM\Column]
    private int $feeCents = 0;
    #[ORM\Column(options: ['default' => false])]
    private bool $withdrawn = false;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $registeredAt;

    public function __construct(Tournament $tournament) { $this->tournament = $tournament; $this->registeredAt = new \DateTimeImmutable(); }
    public function __toString(): string { return $this->name; }
    public function getId(): ?int { return $this->id; }
    public function getTournament(): Tournament { return $this->tournament; }
    public function getTeam(): ?Team { return $this->team; }
    public function setTeam(?Team $value): void { $this->team = $value; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): void { $this->name = trim($value); }
    public function getGroupName(): string { return $this->groupName; }
    public function setGroupName(string $value): void { $this->groupName = trim($value); }
    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(?string $value): void { $this->contactEmail = $value; }
    public function getFeeCents(): int { return $this->feeCents; }
    public function setFeeCents(int $value): void { $this->feeCents = $value; }
    public function isWithdrawn(): bool { return $this->withdrawn; }
    public function setWithdrawn(bool $value): void { $this->withdrawn = $value; }
    public function getRegisteredAt(): \DateTimeImmutable { return $this->registeredAt; }
}
