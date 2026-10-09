<?php

namespace App\Entity;

use App\Repository\SportRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SportRepository::class)]
#[ORM\Table(
    name: 'sport',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_sport_club_name',
            columns: ['club_id', 'name']
        )
    ]
)]
class Sport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Club $club = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $shortName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    /**
     * @var Collection<int, MemberSport>
     */
    #[ORM\OneToMany(
        mappedBy: 'sport',
        targetEntity: MemberSport::class,
        cascade: ['persist'],
        orphanRemoval: true
    )]
    private Collection $memberships;

    public function __construct()
    {
        $this->memberships = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClub(): ?Club
    {
        return $this->club;
    }

    public function setClub(?Club $club): static
    {
        $this->club = $club;

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

    public function getShortName(): ?string
    {
        return $this->shortName;
    }

    public function setShortName(?string $shortName): static
    {
        $this->shortName = $shortName !== null
            ? trim($shortName)
            : null;

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
     * @return Collection<int, MemberSport>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(MemberSport $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setSport($this);
        }

        return $this;
    }

    public function removeMembership(MemberSport $membership): static
    {
        if ($this->memberships->removeElement($membership)) {
            if ($membership->getSport() === $this) {
                $membership->setSport(null);
            }
        }

        return $this;
    }
}