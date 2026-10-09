<?php

namespace App\Entity;

use App\Repository\CoursesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\CourseStatus;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use App\Enum\CourseParticipantStatus;

#[Assert\Callback('validateQualification')]

#[ORM\Entity(repositoryClass: CoursesRepository::class)]
class Courses
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
	

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $requirements = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToMany(mappedBy: 'courses', targetEntity: Schedule::class)]
    private Collection $schedule;

    #[ORM\Column(length: 30)]
    private ?string $category = null;
	
	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: true)]
	private ?Club $club = null;
	
	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: true)]
	private ?Qualification $qualification = null;
	
	#[ORM\Column(type: Types::STRING,length: 20,enumType: CourseStatus::class,options: ['default' => 'draft'])]
	private CourseStatus $status = CourseStatus::DRAFT;

	#[ORM\Column(nullable: true)]
	private ?int $maxParticipants = null;

	#[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
	private ?\DateTimeInterface $registrationDeadline = null;

	/**
	 * @var Collection<int, CourseParticipant>
	 */
	#[ORM\OneToMany(
		mappedBy: 'course',
		targetEntity: CourseParticipant::class,
		cascade: ['persist'],
		orphanRemoval: true
	)]
	private Collection $participants;
	
	/**
	 * @var Collection<int, CourseTrainingUnit>
	 */
	#[ORM\OneToMany(
		mappedBy: 'course',
		targetEntity: CourseTrainingUnit::class,
		cascade: ['persist'],
		orphanRemoval: true
	)]
	private Collection $trainingUnits;
	
	public function __construct(
	) {
		$this->schedule = new ArrayCollection();
		$this->participants = new ArrayCollection();
		$this->trainingUnits = new ArrayCollection();
	}
	

    public function __toString(): string
    {
        return $this->title;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

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

    public function getRequirements(): ?string
    {
        return $this->requirements;
    }

    public function setRequirements(?string $requirements): static
    {
        $this->requirements = $requirements;

        return $this;
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function getSchedule(): Collection
    {
        return $this->schedule;
    }

    public function addSchedule(Schedule $schedule): static
    {
        if (!$this->schedule->contains($schedule)) {
            $this->schedule->add($schedule);
            $schedule->setCourses($this);
        }

        return $this;
    }

	public function canBeCompleted(): bool
	{
		if ($this->participants->isEmpty()) {
			return false;
		}

		foreach ($this->participants as $participant) {
			if (!in_array(
				$participant->getStatus(),
				[
					CourseParticipantStatus::PASSED,
					CourseParticipantStatus::FAILED,
					CourseParticipantStatus::CANCELLED,
				],
				true
			)) {
				return false;
			}
		}

		return true;
	}
	

    public function removeSchedule(Schedule $schedule): static
    {
        if ($this->schedule->removeElement($schedule)) {
            // set the owning side to null (unless already changed)
            if ($schedule->getCourses() === $this) {
                $schedule->setCourses(null);
            }
        }

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        error_log("Setting image: ". print_r($image, true)); // Write the value of image to the log

        $this->image = $image;

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
	
	public function getQualification(): ?Qualification
	{
		return $this->qualification;
	}

	public function setQualification(
		?Qualification $qualification
	): static {
		$this->qualification = $qualification;

		return $this;
	}
	
	public function getStatus(): CourseStatus
	{
		return $this->status;
	}

	public function setStatus(CourseStatus $status): static
	{
		$this->status = $status;

		return $this;
	}

	public function getMaxParticipants(): ?int
	{
		return $this->maxParticipants;
	}

	public function setMaxParticipants(?int $maxParticipants): static
	{
		$this->maxParticipants = $maxParticipants;

		return $this;
	}

	public function getRegistrationDeadline(): ?\DateTimeInterface
	{
		return $this->registrationDeadline;
	}

	public function setRegistrationDeadline(
		?\DateTimeInterface $registrationDeadline
	): static {
		$this->registrationDeadline = $registrationDeadline;

		return $this;
	}

	public function hasFreePlaces(): bool
	{
		if ($this->maxParticipants === null) {
			return true;
		}

		return $this->getParticipantCount() < $this->maxParticipants;
	}

	public function getFreePlaces(): ?int
	{
		if ($this->maxParticipants === null) {
			return null;
		}

		return max(
			0,
			$this->maxParticipants - $this->getParticipantCount()
		);
	}

	public function isRegistrationOpen(): bool
	{
		if ($this->status !== CourseStatus::OPEN) {
			return false;
		}

		if (!$this->hasFreePlaces()) {
			return false;
		}

		if (
			$this->registrationDeadline !== null
			&& $this->registrationDeadline < new \DateTimeImmutable('today')
		) {
			return false;
		}

		return true;
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
	
	public function configureActions(Actions $actions): Actions
	{
		$resetUrl = $this->adminUrlGenerator
			->setController(self::class)
			->setAction(Crud::PAGE_INDEX)
			->unsetAll()
			->generateUrl();

		$reset = Action::new(
			'resetValidityFilter',
			'Filter zurücksetzen',
			'fa fa-filter-circle-xmark'
		)
			->linkToUrl($resetUrl)
			->addCssClass('btn btn-secondary');

		return $actions
			->add(
				Crud::PAGE_INDEX,
				$reset
			);
	}

	public function validateQualification(
		ExecutionContextInterface $context
	): void {
		if (
			$this->club === null
			|| $this->qualification === null
		) {
			return;
		}

		$qualificationClub = $this->qualification->getClub();

		/*
		 * Globale Qualifikationen dürfen von jedem Verein
		 * verwendet werden.
		 */
		if ($qualificationClub === null) {
			return;
		}

		if ($qualificationClub !== $this->club) {
			$context
				->buildViolation(
					'Die Zielqualifikation gehört zu einem anderen Verein.'
				)
				->atPath('qualification')
				->addViolation();
		}
	}
	
	public function getParticipantCount(): int
	{
		return $this->participants->count();
	}
	
	/**
	 * @return Collection<int, CourseParticipant>
	 */
	public function getParticipants(): Collection
	{
		return $this->participants;
	}

	public function addParticipant(
		CourseParticipant $participant
	): static {
		if (!$this->participants->contains($participant)) {
			$this->participants->add($participant);
			$participant->setCourse($this);
		}

		return $this;
	}

	public function removeParticipant(
		CourseParticipant $participant
	): static {
		if ($participant->hasPassed()) {
			throw new \LogicException(
				'Ein bestandener Kursteilnehmer kann nicht aus dem Kurs entfernt werden.'
			);
		}

		if ($this->participants->removeElement($participant)) {
			if ($participant->getCourse() === $this) {
				$participant->setCourse(null);
			}
		}

		return $this;
	}
	
	/**
	 * @return Collection<int, CourseTrainingUnit>
	 */
	public function getTrainingUnits(): Collection
	{
		return $this->trainingUnits;
	}

	public function addTrainingUnit(
		CourseTrainingUnit $trainingUnit
	): static {
		if (!$this->trainingUnits->contains($trainingUnit)) {
			$this->trainingUnits->add($trainingUnit);
			$trainingUnit->setCourse($this);
		}

		return $this;
	}

	public function removeTrainingUnit(
		CourseTrainingUnit $trainingUnit
	): static {
		if ($this->trainingUnits->removeElement($trainingUnit)) {
			if ($trainingUnit->getCourse() === $this) {
				$trainingUnit->setCourse(null);
			}
		}

		return $this;
	}

}
