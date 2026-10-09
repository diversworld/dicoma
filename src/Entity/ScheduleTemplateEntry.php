<?php
namespace App\Entity;

use App\Repository\ScheduleTemplateEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScheduleTemplateEntryRepository::class)]
#[ORM\Table(name: 'schedule_template_entry', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_schedule_template_unit', columns: ['template_id', 'training_unit_type_id', 'sequence'])])]
class ScheduleTemplateEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ScheduleTemplate::class, inversedBy: 'entries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ScheduleTemplate $template = null;

    #[ORM\ManyToOne(targetEntity: TrainingUnitType::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?TrainingUnitType $trainingUnitType = null;

    #[ORM\Column(name: 'sequence', type: Types::INTEGER)]
    #[Assert\Positive]
    private int $sequence = 1;

    #[ORM\Column(name: 'day_offset', type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $dayOffset = 0;

    #[ORM\Column(name: 'start_time', type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(name: 'duration_minutes', type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $durationMinutes = null;

    public function __toString(): string { return sprintf('%s %d', $this->trainingUnitType?->getName() ?? 'Einheit', $this->sequence); }
    public function getId(): ?int { return $this->id; }
    public function getTemplate(): ?ScheduleTemplate { return $this->template; }
    public function setTemplate(?ScheduleTemplate $template): static { $this->template = $template; return $this; }
    public function getTrainingUnitType(): ?TrainingUnitType { return $this->trainingUnitType; }
    public function setTrainingUnitType(?TrainingUnitType $type): static { $this->trainingUnitType = $type; return $this; }
    public function getSequence(): int { return $this->sequence; }
    public function setSequence(int $sequence): static { $this->sequence = $sequence; return $this; }
    public function getDayOffset(): int { return $this->dayOffset; }
    public function setDayOffset(int $offset): static { $this->dayOffset = $offset; return $this; }
    public function getStartTime(): ?\DateTimeInterface { return $this->startTime; }
    public function setStartTime(?\DateTimeInterface $time): static { $this->startTime = $time; return $this; }
    public function getDurationMinutes(): ?int { return $this->durationMinutes; }
    public function setDurationMinutes(?int $minutes): static { $this->durationMinutes = $minutes; return $this; }
}
