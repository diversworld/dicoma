<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Index(name: 'idx_board_task_club_status_due', columns: ['club_id', 'status', 'due_at'])]
class BoardTask
{
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank, Assert\Length(max: 180)]
    private string $title;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    private string $priority = self::PRIORITY_NORMAL;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_OPEN;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $assignee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dueAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $remindAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $reminderSentAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?BoardResolution $resolution = null;

    public function __construct(Club $club, User $createdBy, string $title)
    {
        $this->club = $club;
        $this->createdBy = $createdBy;
        $this->setTitle($title);
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string { return $this->title; }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 180) {
            throw new \InvalidArgumentException('Ein aussagekräftiger Aufgabentitel ist erforderlich.');
        }
        $this->title = $title;
        return $this;
    }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description === null ? null : trim($description); return $this; }
    public function getPriority(): string { return $this->priority; }
    public function setPriority(string $priority): static
    {
        if (!in_array($priority, self::priorities(), true)) {
            throw new \InvalidArgumentException('Unbekannte Aufgabenpriorität.');
        }
        $this->priority = $priority;
        return $this;
    }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static
    {
        if (!in_array($status, self::statuses(), true)) {
            throw new \InvalidArgumentException('Unbekannter Aufgabenstatus.');
        }
        $this->status = $status;
        $this->completedAt = $status === self::STATUS_DONE ? $this->completedAt ?? new \DateTimeImmutable() : null;
        return $this;
    }
    public function getAssignee(): ?User { return $this->assignee; }
    public function setAssignee(?User $assignee): static { $this->assignee = $assignee; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getDueAt(): ?\DateTimeImmutable { return $this->dueAt; }
    public function setDueAt(?\DateTimeImmutable $dueAt): static
    {
        if ($dueAt !== null && $this->createdAt > $dueAt) {
            throw new \InvalidArgumentException('Die Frist darf nicht vor dem Erstellungszeitpunkt liegen.');
        }
        $this->dueAt = $dueAt;
        return $this;
    }
    public function getRemindAt(): ?\DateTimeImmutable { return $this->remindAt; }
    public function setRemindAt(?\DateTimeImmutable $remindAt): static
    {
        if ($remindAt !== null && $this->dueAt !== null && $remindAt > $this->dueAt) {
            throw new \InvalidArgumentException('Die Erinnerung muss vor oder am Fälligkeitstermin liegen.');
        }
        $this->remindAt = $remindAt;
        $this->reminderSentAt = null;
        return $this;
    }
    public function getReminderSentAt(): ?\DateTimeImmutable { return $this->reminderSentAt; }
    public function markReminderSent(): void { $this->reminderSentAt = new \DateTimeImmutable(); }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function getResolution(): ?BoardResolution { return $this->resolution; }
    public function setResolution(?BoardResolution $resolution): static { $this->resolution = $resolution; return $this; }
    public function isOverdue(\DateTimeImmutable $now = new \DateTimeImmutable()): bool
    {
        return $this->dueAt !== null && $this->dueAt < $now
            && !in_array($this->status, [self::STATUS_DONE, self::STATUS_CANCELLED], true);
    }

    /** @return list<string> */
    public static function priorities(): array { return [self::PRIORITY_LOW, self::PRIORITY_NORMAL, self::PRIORITY_HIGH, self::PRIORITY_URGENT]; }
    /** @return list<string> */
    public static function statuses(): array { return [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED]; }
    /** @return array<string, string> */
    public static function priorityChoices(): array { return ['Niedrig' => self::PRIORITY_LOW, 'Normal' => self::PRIORITY_NORMAL, 'Hoch' => self::PRIORITY_HIGH, 'Dringend' => self::PRIORITY_URGENT]; }
    /** @return array<string, string> */
    public static function statusChoices(): array { return ['Offen' => self::STATUS_OPEN, 'In Bearbeitung' => self::STATUS_IN_PROGRESS, 'Erledigt' => self::STATUS_DONE, 'Abgebrochen' => self::STATUS_CANCELLED]; }
}
