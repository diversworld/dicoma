<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_duty_exchange')]
class BarDutyExchange
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private BarDutyAssignment $source;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Member $target;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')] private ?BarDutyAssignment $counterpart;
    #[ORM\Column(length: 20)] private string $status = 'pending';
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $decidedAt = null;
    #[ORM\Column(length: 180, nullable: true)] private ?string $decidedBy = null;
    public function __construct(BarDutyAssignment $source, Member $target, ?BarDutyAssignment $counterpart)
    {
        $this->source = $source; $this->target = $target; $this->counterpart = $counterpart; $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getSource(): BarDutyAssignment { return $this->source; }
    public function getTarget(): Member { return $this->target; }
    public function getCounterpart(): ?BarDutyAssignment { return $this->counterpart; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getDecidedAt(): ?\DateTimeImmutable { return $this->decidedAt; }
    public function getDecidedBy(): ?string { return $this->decidedBy; }
    public function decide(string $status, string $actor): void
    {
        if ($this->status !== 'pending' || !in_array($status, ['accepted', 'declined', 'withdrawn'], true)) { throw new \DomainException('Tauschanfrage ist nicht mehr offen.'); }
        $this->status = $status; $this->decidedAt = new \DateTimeImmutable(); $this->decidedBy = $actor;
    }
}
