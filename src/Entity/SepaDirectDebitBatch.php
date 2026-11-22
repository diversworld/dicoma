<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_sepa_batch_key', columns: ['club_id', 'batch_key'])]
class SepaDirectDebitBatch
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_EXPORTED = 'exported';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Club $club;
    #[ORM\Column(length: 100)]
    private string $batchKey;
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $collectionDate;
    #[ORM\Column]
    private int $totalCents = 0;
    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_DRAFT;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $approvedBy = null;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $exportedAt = null;
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $sha256 = null;
    #[ORM\OneToMany(mappedBy: 'batch', targetEntity: SepaDirectDebitItem::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(Club $club, User $createdBy, string $batchKey, \DateTimeImmutable $collectionDate)
    {
        if (trim($batchKey) === '' || $collectionDate < new \DateTimeImmutable('today')) {
            throw new \DomainException('Ungültiger Lastschriftlauf.');
        }
        $this->club = $club;
        $this->createdBy = $createdBy;
        $this->batchKey = trim($batchKey);
        $this->collectionDate = $collectionDate;
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getBatchKey(): string { return $this->batchKey; }
    public function getCollectionDate(): \DateTimeImmutable { return $this->collectionDate; }
    public function getTotalCents(): int { return $this->totalCents; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function getApprovedBy(): ?User { return $this->approvedBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getApprovedAt(): ?\DateTimeImmutable { return $this->approvedAt; }
    public function getExportedAt(): ?\DateTimeImmutable { return $this->exportedAt; }
    public function getSha256(): ?string { return $this->sha256; }
    public function getItems(): Collection { return $this->items; }
    public function addItem(SepaDirectDebitItem $item): void
    {
        if ($this->status !== self::STATUS_DRAFT || $this->items->contains($item)) { throw new \DomainException('Lastschriftlauf ist bereits freigegeben oder Position vorhanden.'); }
        $item->setBatch($this);
        $this->items->add($item);
        $this->totalCents += $item->getAmountCents();
    }
    public function approve(User $approver): void
    {
        if ($this->status !== self::STATUS_DRAFT || $this->items->isEmpty()) { throw new \DomainException('Der Lastschriftlauf kann nicht freigegeben werden.'); }
        if ($this->createdBy->getId() === $approver->getId()) { throw new \DomainException('Vier-Augen-Prinzip: Ersteller und Prüfer müssen verschieden sein.'); }
        $this->approvedBy = $approver;
        $this->approvedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_APPROVED;
    }
    public function markExported(string $sha256): void
    {
        if ($this->status !== self::STATUS_APPROVED) { throw new \DomainException('Nur freigegebene Lastschriftläufe dürfen exportiert werden.'); }
        $this->sha256 = $sha256;
        $this->exportedAt = new \DateTimeImmutable();
        $this->status = self::STATUS_EXPORTED;
    }
    public function cancel(): void
    {
        if ($this->status === self::STATUS_EXPORTED) { throw new \DomainException('Exportierte Lastschriftläufe können nicht storniert werden.'); }
        $this->status = self::STATUS_CANCELLED;
    }
}
