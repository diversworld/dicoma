<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tank_condition_history')]
#[ORM\Index(name: 'IDX_TANK_CONDITION_TANK_TIME', columns: ['tank_id', 'occurred_at'])]
class TankConditionHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Tank $tank;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(length: 32)]
    private string $eventType;

    #[ORM\Column(length: 20)]
    private string $conditionStatus;

    #[ORM\Column(type: 'boolean')]
    private bool $lendable;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $bookingId;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $bookingItemId;

    public function __construct(Tank $tank, string $eventType, string $conditionStatus, bool $lendable, ?string $notes = null, ?int $bookingId = null, ?int $bookingItemId = null)
    {
        $this->tank = $tank;
        $this->occurredAt = new \DateTimeImmutable();
        $this->eventType = $eventType;
        $this->conditionStatus = $conditionStatus;
        $this->lendable = $lendable;
        $this->notes = $notes;
        $this->bookingId = $bookingId;
        $this->bookingItemId = $bookingItemId;
    }

    public function getId(): ?int { return $this->id; }
    public function getTank(): Tank { return $this->tank; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function getEventType(): string { return $this->eventType; }
    public function getConditionStatus(): string { return $this->conditionStatus; }
    public function isLendable(): bool { return $this->lendable; }
    public function getNotes(): ?string { return $this->notes; }
    public function getBookingId(): ?int { return $this->bookingId; }
    public function getBookingItemId(): ?int { return $this->bookingItemId; }
}
