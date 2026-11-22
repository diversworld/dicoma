<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Index(name: 'idx_membership_history_member_at', columns: ['member_id', 'occurred_at'])]
class MembershipHistory
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Member $member;
    #[ORM\Column(length: 180)]
    private string $actor;
    #[ORM\Column(length: 80)]
    private string $action;
    #[ORM\Column(type: 'json')]
    private array $changes = [];
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;
    public function __construct(Member $member, string $actor, string $action, array $changes)
    {
        $this->member = $member;
        $this->actor = $actor;
        $this->action = $action;
        $this->changes = $changes;
        $this->occurredAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getMember(): Member { return $this->member; }
    public function getActor(): string { return $this->actor; }
    public function getAction(): string { return $this->action; }
    public function getChanges(): array { return $this->changes; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
