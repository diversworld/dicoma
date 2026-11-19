<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_membership_billing_run', columns: ['club_id', 'year'])]
#[ORM\Index(name: 'IDX_4DA78D2961190A32', columns: ['club_id'])]
class MembershipBillingRun
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column]
    private int $year;
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dueAt;
    #[ORM\Column(length: 20)]
    private string $status = 'preview';
    #[ORM\Column]
    private int $issuedCount = 0;
    #[ORM\Column]
    private int $totalCents = 0;
    #[ORM\Column(length: 180)]
    private string $createdBy;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Club $club, int $year, \DateTimeImmutable $dueAt, string $actor)
    {
        $this->club = $club; $this->year = $year; $this->dueAt = $dueAt;
        $this->createdBy = $actor; $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getYear(): int { return $this->year; }
    public function getDueAt(): \DateTimeImmutable { return $this->dueAt; }
    public function getStatus(): string { return $this->status; }
    public function getIssuedCount(): int { return $this->issuedCount; }
    public function getTotalCents(): int { return $this->totalCents; }
    public function complete(int $count, int $total): void
    {
        if ($this->status !== 'preview') { throw new \DomainException('Beitragslauf wurde bereits verarbeitet.'); }
        $this->issuedCount = $count; $this->totalCents = $total; $this->status = 'completed';
    }
}
