<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_tournament_roster_member', columns: ['entry_id', 'team_member_id'])]
class TournamentPlayer
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private TournamentEntry $entry;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'RESTRICT')]
    private ?TeamMember $teamMember = null;
    #[ORM\Column(length: 150)]
    #[Assert\NotBlank, Assert\Length(max: 150)]
    private string $name = '';
    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $position = null;
    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 999)]
    private ?int $jerseyNumber = null;
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank, Assert\Length(max: 255)]
    private string $eligibilityEvidence = '';

    public function __construct(TournamentEntry $entry) { $this->entry = $entry; }
    public function getId(): ?int { return $this->id; }
    public function getEntry(): TournamentEntry { return $this->entry; }
    public function getTeamMember(): ?TeamMember { return $this->teamMember; }
    public function setTeamMember(?TeamMember $value): void { $this->teamMember = $value; }
    public function getName(): string { return $this->name; }
    public function setName(string $value): void { $this->name = trim($value); }
    public function getPosition(): ?string { return $this->position; }
    public function setPosition(?string $value): void { $this->position = $value; }
    public function getJerseyNumber(): ?int { return $this->jerseyNumber; }
    public function setJerseyNumber(?int $value): void { $this->jerseyNumber = $value; }
    public function getEligibilityEvidence(): string { return $this->eligibilityEvidence; }
    public function setEligibilityEvidence(string $value): void { $this->eligibilityEvidence = trim($value); }
}
