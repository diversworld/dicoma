<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'course_audit_event')]
#[ORM\Index(columns: ['course_id', 'occurred_at'], name: 'idx_course_audit_course_date')]
class CourseAuditEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'course_id')]
    private int $courseId;

    #[ORM\Column(name: 'participant_id', nullable: true)]
    private ?int $participantId;

    #[ORM\Column(name: 'actor_user_id', nullable: true)]
    private ?int $actorUserId;

    #[ORM\Column(length: 40)]
    private string $action;

    #[ORM\Column(name: 'old_status', length: 40, nullable: true)]
    private ?string $oldStatus;

    #[ORM\Column(name: 'new_status', length: 40)]
    private string $newStatus;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(int $courseId, ?int $participantId, ?int $actorUserId, string $action, ?string $oldStatus, string $newStatus, ?string $reason = null)
    {
        if ($courseId < 1 || $action === '' || $newStatus === '') {
            throw new \InvalidArgumentException('Ungültiges Audit-Ereignis.');
        }
        $this->courseId = $courseId;
        $this->participantId = $participantId;
        $this->actorUserId = $actorUserId;
        $this->action = $action;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->reason = $reason;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCourseId(): int { return $this->courseId; }
    public function getParticipantId(): ?int { return $this->participantId; }
    public function getActorUserId(): ?int { return $this->actorUserId; }
    public function getAction(): string { return $this->action; }
    public function getOldStatus(): ?string { return $this->oldStatus; }
    public function getNewStatus(): string { return $this->newStatus; }
    public function getReason(): ?string { return $this->reason; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
