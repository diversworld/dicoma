<?php

namespace App\Entity;

use App\Enum\TeamType;
use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
#[ORM\Table(
    name: 'team',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_team_sport_name',
            columns: ['sport_id', 'name']
        )
    ]
)]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Sport $sport = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\Column(
        type: Types::STRING,
        length: 30,
        enumType: TeamType::class,
        options: ['default' => 'training_group']
    )]
    private TeamType $type = TeamType::TRAINING_GROUP;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * @var Collection<int, TeamMember>
     */
    #[ORM\OneToMany(
        mappedBy: 'team',
        targetEntity: TeamMember::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $members;

    public function __construct()
    {
        $this->members = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf(
            '%s – %s',
            $this->sport?->getName() ?? '',
            $this->name ?? ''
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSport(): ?Sport
    {
        return $this->sport;
    }

    public function setSport(?Sport $sport): static
    {
        $this->sport = $sport;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getType(): TeamType
    {
        return $this->type;
    }

    public function setType(TeamType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * @return Collection<int, TeamMember>
     */
    public function getMembers(): Collection
    {
        return $this->members;
    }

    public function addMember(TeamMember $member): static
    {
        if (!$this->members->contains($member)) {
            $this->members->add($member);
            $member->setTeam($this);
        }

        return $this;
    }

    public function removeMember(TeamMember $member): static
    {
        if ($this->members->removeElement($member)) {
            if ($member->getTeam() === $this) {
                $member->setTeam(null);
            }
        }

        return $this;
    }
}