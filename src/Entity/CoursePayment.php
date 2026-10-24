<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_course_payment_reference', columns: ['participant_id', 'reference'])]
class CoursePayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private CourseParticipant $participant;

    #[ORM\Column]
    private int $amountCents;

    #[ORM\Column(length: 20)]
    private string $method;

    #[ORM\Column(length: 120)]
    private string $reference;

    #[ORM\Column(length: 180)]
    private string $recordedBy;

    #[ORM\Column]
    private \DateTimeImmutable $recordedAt;

    public function __construct(CourseParticipant $participant, int $amountCents, string $method, string $reference, string $recordedBy)
    {
        if ($amountCents === 0 || !in_array($method, ['transfer', 'cash'], true) || trim($reference) === '' || strlen($reference) > 120) {
            throw new \DomainException('Zahlungsbetrag, Zahlungsart oder Belegreferenz ist ungültig.');
        }
        $this->participant = $participant;
        $this->amountCents = $amountCents;
        $this->method = $method;
        $this->reference = trim($reference);
        $this->recordedBy = $recordedBy;
        $this->recordedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getParticipant(): CourseParticipant { return $this->participant; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getMethod(): string { return $this->method; }
    public function getReference(): string { return $this->reference; }
    public function getRecordedBy(): string { return $this->recordedBy; }
    public function getRecordedAt(): \DateTimeImmutable { return $this->recordedAt; }
}
