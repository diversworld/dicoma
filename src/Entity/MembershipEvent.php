<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class MembershipEvent
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Member $member;
    #[ORM\Column(length: 50)]
    private string $kind;
    #[ORM\Column(length: 180)]
    private string $actor;
    #[ORM\Column(length: 1000)]
    private string $description;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    public function __construct(Member $member, string $kind, string $actor, string $description)
    {
        $this->member = $member;
        $this->kind = $kind;
        $this->actor = $actor;
        $this->description = $description;
        $this->occurredAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getKind(): string { return $this->kind; }
    public function getActor(): string { return $this->actor; }
    public function getDescription(): string { return $this->description; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
}
