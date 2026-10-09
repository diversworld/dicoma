<?php

namespace App\Entity;

use App\Enum\CourseParticipantStatus;
use App\Repository\CourseParticipantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CourseParticipantRepository::class)]
#[ORM\Table(
    name: 'course_participant',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_course_participant',
            columns: ['course_id', 'member_id']
        )
    ]
)]
#[Assert\Callback('validateAssignment')]
class CourseParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Courses::class,
        inversedBy: 'participants'
    )]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Courses $course = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;

    #[ORM\Column(
        type: Types::STRING,
        length: 20,
        enumType: CourseParticipantStatus::class,
        options: ['default' => 'registered']
    )]
    private CourseParticipantStatus $status =
        CourseParticipantStatus::REGISTERED;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->registeredAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf(
            '%s – %s',
            $this->member?->getFullName() ?? '',
            $this->course?->__toString() ?? ''
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCourse(): ?Courses
    {
        return $this->course;
    }

    public function setCourse(?Courses $course): static
    {
        $this->course = $course;

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

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function setRegisteredAt(
        \DateTimeImmutable $registeredAt
    ): static {
        $this->registeredAt = $registeredAt;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(
        ?\DateTimeInterface $startedAt
    ): static {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(
        ?\DateTimeInterface $completedAt
    ): static {
        $this->completedAt = $completedAt;

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
	
	public function getStatus(): CourseParticipantStatus
	{
		return $this->status;
	}
	public function getStatusLabel(): string
	{
		return $this->status->label();
	}

	public function setStatus(
		CourseParticipantStatus $status
	): static {
		$this->status = $status;

		if (
			$status === CourseParticipantStatus::ACTIVE
			&& $this->startedAt === null
		) {
			$this->startedAt = new \DateTimeImmutable('today');
		}

		if (
			in_array(
				$status,
				[
					CourseParticipantStatus::REGISTERED,
					CourseParticipantStatus::ACTIVE,
				],
				true
			)
		) {
			$this->completedAt = null;
		}

		if (
			in_array(
				$status,
				[
					CourseParticipantStatus::FAILED,
					CourseParticipantStatus::CANCELLED,
				],
				true
			)
			&& $this->completedAt === null
		) {
			$this->completedAt = new \DateTimeImmutable('today');
		}

		return $this;
	}
	
    public function hasPassed(): bool
    {
        return $this->status === CourseParticipantStatus::PASSED;
    }

    public function validateAssignment(
        ExecutionContextInterface $context
    ): void {
        if ($this->course === null || $this->member === null) {
            return;
        }

        $courseClub = $this->course->getClub();
        $memberClub = $this->member->getClub();

        if (
            $courseClub !== null
            && $memberClub !== null
            && $courseClub !== $memberClub
        ) {
            $context
                ->buildViolation(
                    'Kurs und Teilnehmer müssen demselben Verein angehören.'
                )
                ->atPath('member')
                ->addViolation();
        }
    }
}