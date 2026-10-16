<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_tank_check_status_history')]
#[ORM\Index(name: 'idx_tuv_history_booking', columns: ['booking_id'])]
class MemberTankCheckStatusHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: MemberTankCheckBooking::class)]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private MemberTankCheckBooking $booking;

    #[ORM\Column(name: 'old_status', length: 20)]
    private string $oldStatus;

    #[ORM\Column(name: 'new_status', length: 20)]
    private string $newStatus;

    #[ORM\Column(name: 'actor_identifier', length: 180)]
    private string $actorIdentifier;

    #[ORM\Column(name: 'changed_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $changedAt;

    public function __construct(MemberTankCheckBooking $booking, string $oldStatus, string $newStatus, string $actorIdentifier)
    {
        $this->booking = $booking;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->actorIdentifier = mb_substr($actorIdentifier, 0, 180);
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getBooking(): MemberTankCheckBooking { return $this->booking; }
    public function getOldStatus(): string { return $this->oldStatus; }
    public function getNewStatus(): string { return $this->newStatus; }
    public function getActorIdentifier(): string { return $this->actorIdentifier; }
    public function getChangedAt(): \DateTimeImmutable { return $this->changedAt; }
}
