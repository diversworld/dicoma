<?php

namespace App\Entity;

use App\Repository\CourseTrainingUnitRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CourseTrainingUnitRepository::class)]
#[ORM\Table(
    name: 'course_training_unit',
    uniqueConstraints: [
        new ORM\UniqueConstraint(
            name: 'uniq_course_training_unit',
            columns: ['course_id', 'training_unit_type_id']
        )
    ]
)]
class CourseTrainingUnit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(
        targetEntity: Courses::class,
        inversedBy: 'trainingUnits'
    )]
    #[ORM\JoinColumn(
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private ?Courses $course = null;

    #[ORM\ManyToOne(
        targetEntity: TrainingUnitType::class
    )]
    #[ORM\JoinColumn(
        nullable: false,
        onDelete: 'CASCADE'
    )]
    private ?TrainingUnitType $trainingUnitType = null;

	#[ORM\Column(options: ['default' => 1])]
	#[Assert\Positive(
		message: 'Die Anzahl der Termine muss mindestens 1 betragen.'
	)]
	private int $requiredSessions = 1;

    /*
     * Muss diese Einheit für den erfolgreichen
     * Kursabschluss erfüllt sein?
     */
    #[ORM\Column(options: ['default' => true])]
    private bool $required = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    public function __toString(): string
    {
        return sprintf(
            '%s (%d ×)',
            $this->trainingUnitType?->getName() ?? '',
            $this->requiredSessions
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

    public function getTrainingUnitType(): ?TrainingUnitType
    {
        return $this->trainingUnitType;
    }

    public function setTrainingUnitType(
        ?TrainingUnitType $trainingUnitType
    ): static {
        $this->trainingUnitType = $trainingUnitType;

        return $this;
    }

    public function getRequiredSessions(): int
    {
        return $this->requiredSessions;
    }

	public function setRequiredSessions(
		int $requiredSessions
	): static {
		$this->requiredSessions = max(
			1,
			$requiredSessions
		);

		return $this;
	}

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;

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
}