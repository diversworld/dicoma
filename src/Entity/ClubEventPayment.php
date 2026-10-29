<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_event_payment_reference', columns: ['registration_id', 'reference'])]
class ClubEventPayment
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ClubEventRegistration $registration;
    #[ORM\Column] private int $amountCents;
    #[ORM\Column(length: 20)] private string $method;
    #[ORM\Column(length: 120)] private string $reference;
    #[ORM\Column(length: 180)] private string $recordedBy;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $recordedAt;

    public function __construct(ClubEventRegistration $registration, int $amountCents, string $method, string $reference, string $actor)
    {
        $reference = trim($reference);
        if ($amountCents === 0 || !in_array($method, ['cash', 'transfer'], true) || $reference === '' || mb_strlen($reference) > 120) {
            throw new \DomainException('Betrag, Zahlungsart oder Belegreferenz ist ungültig.');
        }
        $this->registration = $registration; $this->amountCents = $amountCents;
        $this->method = $method; $this->reference = $reference; $this->recordedBy = $actor; $this->recordedAt = new \DateTimeImmutable();
    }
    public function getId(): ?int { return $this->id; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getMethod(): string { return $this->method; }
    public function getReference(): string { return $this->reference; }
    public function getRecordedBy(): string { return $this->recordedBy; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
}
