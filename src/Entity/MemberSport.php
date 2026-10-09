<?php

namespace App\Entity;

use App\Enum\MemberSportStatus;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\MemberSportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[Assert\Callback('validateClubAssignment')]
#[ORM\Entity(repositoryClass: MemberSportRepository::class)]
#[ORM\Table(
    name: 'member_sport',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_member_sport',
            columns: ['member_id', 'sport_id']
        )
    ]
)]
class MemberSport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Member::class,
        inversedBy: 'sports'
    )]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\ManyToOne(
        targetEntity: Sport::class,
        inversedBy: 'memberships'
    )]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Sport $sport = null;

    #[ORM\Column(
        type: Types::STRING,
        length: 20,
        enumType: MemberSportStatus::class,
        options: ['default' => 'active']
    )]
    private MemberSportStatus $status = MemberSportStatus::ACTIVE;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $leftAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSport(): ?Sport
    {
        return $this->sport;
    }

    public function setSport(?Sport $sport): static
    {
        $this->sport = $sport;

        return $this;
    }

    public function getStatus(): MemberSportStatus
    {
        return $this->status;
    }

    public function setStatus(MemberSportStatus $status): static
    {
        $this->status = $status;

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

    public function __toString(): string
    {
        return sprintf(
            '%s – %s',
            $this->member?->getFullName() ?? '',
            $this->sport?->getName() ?? ''
        );
    }
	
	public function validateClubAssignment(
		ExecutionContextInterface $context
	): void {
		if ($this->member === null || $this->sport === null) {
			return;
		}

		$memberClub = $this->member->getClub();
		$sportClub = $this->sport->getClub();

		if ($memberClub === null || $sportClub === null) {
			return;
		}

		if ($memberClub->getId() !== $sportClub->getId()) {
			$context
				->buildViolation(
					'Mitglied und Sportart müssen demselben Verein angehören.'
				)
				->atPath('sport')
				->addViolation();
		}
	}
}