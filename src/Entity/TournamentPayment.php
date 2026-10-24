<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_tournament_payment_reference', columns: ['entry_id', 'reference'])]
class TournamentPayment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private TournamentEntry $entry;
    #[ORM\Column]
    private int $amountCents;
    #[ORM\Column(length: 120)]
    private string $reference;
    #[ORM\Column(length: 180)]
    private string $recordedBy;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $recordedAt;

    public function __construct(TournamentEntry $entry, int $amount, string $reference, string $actor)
    {
        $this->entry = $entry; $this->amountCents = $amount; $this->reference = $reference;
        $this->recordedBy = $actor; $this->recordedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getEntry(): TournamentEntry { return $this->entry; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getReference(): string { return $this->reference; }
    public function getRecordedBy(): string { return $this->recordedBy; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
}
