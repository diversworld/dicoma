<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'bar_cash_entry')]
class BarCashEntry
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private BarCashSession $session;
    #[ORM\OneToOne(targetEntity: self::class), ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')] private ?self $reversalOf;
    #[ORM\Column] private int $amountCents;
    #[ORM\Column(length: 500)] private string $reason;
    #[ORM\Column(length: 120)] private string $reference;
    #[ORM\Column(length: 180)] private string $createdBy;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    public function __construct(BarCashSession $session, int $signedCents, string $reason, string $reference, string $actor, ?self $reversalOf = null)
    {
        if ($signedCents === 0 || abs($signedCents) > 999999999 || mb_strlen(trim($reason)) < 10 || mb_strlen($reason) > 500 || mb_strlen($reference) > 120) { throw new \DomainException('Betrag, Belegreferenz und Grund (10–500 Zeichen) prüfen.'); }
        $this->session = $session; $this->amountCents = $signedCents; $this->reason = trim($reason);
        $this->reference = trim($reference); $this->createdBy = $actor; $this->reversalOf = $reversalOf; $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getSession(): BarCashSession { return $this->session; }
    public function getReversalOf(): ?self { return $this->reversalOf; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getReason(): string { return $this->reason; }
    public function getReference(): string { return $this->reference; }
    public function getCreatedBy(): string { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
