<?php
namespace App\Entity;

use App\Enum\TrainingUnitResultStatus;
use App\Repository\TrainingUnitResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[UniqueEntity(fields: ['courseParticipant', 'schedule'],message: 'Für diesen Teilnehmer und Termin existiert bereits ein Ausbildungsnachweis.',errorPath: 'schedule')]
#[ORM\Entity(repositoryClass: TrainingUnitResultRepository::class)]
#[ORM\Table(name: 'training_unit_result', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_training_unit_result_participant_schedule', columns: ['course_participant_id', 'schedule_id'])
])]
#[Assert\Callback('validateAssignment')]
class TrainingUnitResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CourseParticipant $courseParticipant = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Schedule $schedule = null;

    #[ORM\Column(type: Types::STRING, length: 30, enumType: TrainingUnitResultStatus::class, options: ['default' => 'open'])]
    private TrainingUnitResultStatus $status = TrainingUnitResultStatus::OPEN;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $assessedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Member $assessor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __toString(): string
    {
        return sprintf('%s – %s', (string) ($this->courseParticipant ?? ''), (string) ($this->schedule ?? ''));
    }

    public function getId(): ?int { return $this->id; }
    public function getCourseParticipant(): ?CourseParticipant { return $this->courseParticipant; }
    public function setCourseParticipant(?CourseParticipant $value): static { $this->courseParticipant = $value; return $this; }
    public function getSchedule(): ?Schedule { return $this->schedule; }
    public function setSchedule(?Schedule $value): static { $this->schedule = $value; return $this; }
    public function getStatus(): TrainingUnitResultStatus { return $this->status; }
    public function setStatus(TrainingUnitResultStatus $value): static { $this->status = $value; return $this; }
    public function getStatusLabel(): string { return $this->status->label(); }
    public function getAssessedAt(): ?\DateTimeInterface { return $this->assessedAt; }
    public function setAssessedAt(?\DateTimeInterface $value): static { $this->assessedAt = $value; return $this; }
    public function getAssessor(): ?Member { return $this->assessor; }
    public function setAssessor(?Member $value): static { $this->assessor = $value; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $value): static { $this->notes = $value; return $this; }

    public function validateAssignment(ExecutionContextInterface $context): void
    {
        if ($this->courseParticipant === null || $this->schedule === null) {
            return;
        }
        $course = $this->courseParticipant->getCourse();
        if ($course === null || $this->schedule->getCourses()?->getId() !== $course->getId()) {
            $context->buildViolation('Der Termin muss zum Kurs des Teilnehmers gehören.')
                ->atPath('schedule')->addViolation();
        }
        if ($this->assessor !== null && $course->getClub() !== null
            && $this->assessor->getClub()?->getId() !== $course->getClub()->getId()) {
            $context->buildViolation('Der Prüfer muss dem Kursverein angehören.')
                ->atPath('assessor')->addViolation();
        }
    }
}
