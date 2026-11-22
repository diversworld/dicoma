<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_finance_source', columns: ['club_id', 'source_key'])]
class FinanceTransaction
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private FinanceAccount $debitAccount;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private FinanceAccount $creditAccount;
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $effectiveAt;
    #[ORM\Column]
    private int $amountCents;
    #[ORM\Column(length: 240)]
    private string $description;
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $reference = null;
    #[ORM\Column(length: 180)]
    private string $sourceKey;
    #[ORM\Column(length: 180)]
    private string $actor;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    #[ORM\OneToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?self $reversedBy = null;
    public function __construct(Club $club, FinanceAccount $debit, FinanceAccount $credit, int $amountCents, \DateTimeImmutable $effectiveAt, string $description, string $sourceKey, string $actor, ?string $reference = null)
    {
        if ($debit === $credit || $debit->getClub()->getId() !== $club->getId() || $credit->getClub()->getId() !== $club->getId()
            || $amountCents <= 0 || trim($description) === '' || trim($sourceKey) === '') {
            throw new \DomainException('Buchung muss ausgleichsfähig und vereinsbezogen sein.');
        }
        $this->club = $club; $this->debitAccount = $debit; $this->creditAccount = $credit;
        $this->amountCents = $amountCents; $this->effectiveAt = $effectiveAt; $this->description = trim($description);
        $this->sourceKey = trim($sourceKey); $this->actor = $actor; $this->reference = $reference === null ? null : trim($reference);
        $this->createdAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getDebitAccount(): FinanceAccount { return $this->debitAccount; }
    public function getCreditAccount(): FinanceAccount { return $this->creditAccount; }
    public function getEffectiveAt(): \DateTimeImmutable { return $this->effectiveAt; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getDescription(): string { return $this->description; }
    public function getReference(): ?string { return $this->reference; }
    public function getActor(): string { return $this->actor; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSourceKey(): string { return $this->sourceKey; }
    public function getReversedBy(): ?self { return $this->reversedBy; }
    public function isReversed(): bool { return $this->reversedBy !== null; }
    public function attachReversal(self $reversal): void
    {
        if ($this->reversedBy !== null || $reversal === $this) { throw new \DomainException('Buchung wurde bereits storniert.'); }
        $this->reversedBy = $reversal;
    }
}
