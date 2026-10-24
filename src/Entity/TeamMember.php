<?php

namespace App\Entity;

use App\Enum\TeamMemberRole;
use App\Repository\TeamMemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_team_member', columns: ['team_id', 'member_id'])]
#[ORM\Table(
    name: 'team_member',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_team_member',
            columns: ['team_id', 'member_id']
        )
    ]
)]
#[Assert\Callback('validateAssignment')]
#[UniqueEntity(fields: ['team', 'member'], message: 'Dieses Mitglied gehört bereits zum Kader.')]
class TeamMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\Column(
        type: Types::STRING,
        length: 30,
        enumType: TeamMemberRole::class,
        options: ['default' => 'member']
    )]
    private TeamMemberRole $role = TeamMemberRole::MEMBER;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $leftAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80)]
    private ?string $position = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 999)]
    private ?int $jerseyNumber = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $competitionEligible = false;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $eligibilityUntil = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $eligibilityNote = null;

    public function getPosition(): ?string { return $this->position; }
    public function setPosition(?string $value): static { $this->position = $value; return $this; }
    public function getJerseyNumber(): ?int { return $this->jerseyNumber; }
    public function setJerseyNumber(?int $value): static { $this->jerseyNumber = $value; return $this; }
    public function isCompetitionEligible(): bool { return $this->competitionEligible; }
    public function setCompetitionEligible(bool $value): static { $this->competitionEligible = $value; return $this; }
    public function getEligibilityUntil(): ?\DateTimeInterface { return $this->eligibilityUntil; }
    public function setEligibilityUntil(?\DateTimeInterface $value): static { $this->eligibilityUntil = $value; return $this; }
    public function getEligibilityNote(): ?string { return $this->eligibilityNote; }
    public function setEligibilityNote(?string $value): static { $this->eligibilityNote = $value; return $this; }

    public function isAssignedOn(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');
        return ($this->joinedAt === null || $this->joinedAt->format('Y-m-d') <= $day)
            && ($this->leftAt === null || $this->leftAt->format('Y-m-d') >= $day);
    }

    /** Explicit consent for adding a missing sport; not persisted. */
    private bool $assignMissingSport = false;

    public function isAssignMissingSport(): bool
    {
        return $this->assignMissingSport;
    }

    public function setAssignMissingSport(bool $assignMissingSport): static
    {
        $this->assignMissingSport = $assignMissingSport;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getMember(): ?Member
    {
        return $this->member;
    }

    public function setMember(?Member $member): static
    {
        $this->member = $member;

        return $this;
    }

    public function getRole(): TeamMemberRole
    {
        return $this->role;
    }

    public function setRole(TeamMemberRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(?\DateTimeInterface $joinedAt): static
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }

    public function getLeftAt(): ?\DateTimeInterface
    {
        return $this->leftAt;
    }

    public function setLeftAt(?\DateTimeInterface $leftAt): static
    {
        $this->leftAt = $leftAt;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function validateAssignment(
        ExecutionContextInterface $context
    ): void {
        if ($this->joinedAt !== null && $this->leftAt !== null && $this->leftAt < $this->joinedAt) {
            $context->buildViolation('Das Enddatum darf nicht vor dem Beginn liegen.')->atPath('leftAt')->addViolation();
        }
        if ($this->team === null || $this->member === null) {
            return;
        }

        $sport = $this->team->getSport();

        if ($sport === null) {
            return;
        }

        if ($sport->getClub() !== $this->member->getClub()) {
            $context
                ->buildViolation(
                    'Mitglied und Gruppe müssen demselben Verein angehören.'
                )
                ->atPath('member')
                ->addViolation();

            return;
        }

        /*
         * Ein Teammitglied sollte grundsätzlich auch der
         * entsprechenden Sportart zugeordnet sein.
         */
        $hasSport = false;

        foreach ($this->member->getSports() as $memberSport) {
            if ($memberSport->getSport() === $sport) {
                $hasSport = true;
                break;
            }
        }

        if (!$hasSport && !$this->assignMissingSport) {
            $context
                ->buildViolation(
                    'team_member.missing_sport'
                )
                ->setParameter('{{ sport }}',$sport?->getName() ?? 'Unbekannte Sportart')
                ->atPath('member')
                ->addViolation();
        }
    }
}