<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_audit_club_occurred', columns: ['club_id', 'occurred_at'])]
#[ORM\Index(name: 'idx_audit_purge', columns: ['expires_at'])]
class AuditEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 180)]
    private string $actor;
    #[ORM\Column(length: 80)]
    private string $action;
    #[ORM\Column(length: 180)]
    private string $subjectType;
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $subjectId;
    #[ORM\Column(type: 'json')]
    private array $context = [];
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;
    public function __construct(Club $club, string $actor, string $action, string $subjectType, ?string $subjectId, array $context, \DateTimeImmutable $expiresAt)
    {
        $this->club = $club;
        $this->actor = mb_substr(trim($actor), 0, 180);
        $this->action = mb_substr(trim($action), 0, 80);
        $this->subjectType = mb_substr(trim($subjectType), 0, 180);
        $this->subjectId = $subjectId === null ? null : mb_substr($subjectId, 0, 100);
        $this->context = $context;
        $this->occurredAt = new \DateTimeImmutable();
        $this->expiresAt = $expiresAt;
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getActor(): string { return $this->actor; }
    public function getAction(): string { return $this->action; }
    public function getSubjectType(): string { return $this->subjectType; }
    public function getSubjectId(): ?string { return $this->subjectId; }
    public function getContext(): array { return $this->context; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }
}
