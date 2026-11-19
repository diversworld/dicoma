<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(name: 'idx_board_resolution_status_due', columns: ['status', 'due_at'])]
class BoardResolution
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'resolutions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private BoardMeeting $meeting;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $title;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $decision;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $responsible = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\OneToMany(mappedBy: 'resolution', targetEntity: BoardTask::class)]
    private Collection $tasks;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(BoardMeeting $meeting, string $title, string $decision)
    {
        $this->meeting = $meeting;
        $this->title = trim($title);
        $this->decision = trim($decision);
        if ($this->title === '' || $this->decision === '') {
            throw new \InvalidArgumentException('Beschlusstitel und Entscheidung sind erforderlich.');
        }
        $this->createdAt = new \DateTimeImmutable();
        $this->tasks = new ArrayCollection();
    }

    public function __toString(): string { return $this->title; }
    public function getId(): ?int { return $this->id; }
    public function getMeeting(): BoardMeeting { return $this->meeting; }
    public function setMeeting(BoardMeeting $meeting): static { $this->meeting = $meeting; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = trim($title); return $this; }
    public function getDecision(): string { return $this->decision; }
    public function setDecision(string $decision): static { $this->decision = trim($decision); return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!in_array($status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED, self::STATUS_CANCELLED], true)) {
            throw new \InvalidArgumentException('Unbekannter Beschlussstatus.');
        }
        $this->status = $status;
        return $this;
    }
    public function getResponsible(): ?User { return $this->responsible; }
    public function setResponsible(?User $responsible): static { $this->responsible = $responsible; return $this; }
    public function getDueAt(): ?\DateTimeImmutable { return $this->dueAt; }
    public function setDueAt(?\DateTimeImmutable $dueAt): static { $this->dueAt = $dueAt; return $this; }
    public function getTasks(): Collection { return $this->tasks; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    /** @return array<string, string> */
    public static function statusChoices(): array { return ['Offen' => self::STATUS_OPEN, 'In Bearbeitung' => self::STATUS_IN_PROGRESS, 'Erledigt' => self::STATUS_COMPLETED, 'Verworfen' => self::STATUS_CANCELLED]; }
}
