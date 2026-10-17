<?php
declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_tank_check_result')]
class MemberTankCheckResult
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: MemberTankCheckBooking::class)]
    #[ORM\JoinColumn(name: 'booking_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private MemberTankCheckBooking $booking;

    #[ORM\Column(length: 10)]
    private string $result;

    #[ORM\Column(name: 'inspected_at', type: 'date_immutable')]
    private \DateTimeImmutable $inspectedAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(name: 'updated_by', length: 180)]
    private string $updatedBy;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(MemberTankCheckBooking $booking)
    {
        $this->booking = $booking;
        $this->inspectedAt = new \DateTimeImmutable('today');
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedBy = '';
        $this->result = 'passed';
    }

    public function getId(): ?int { return $this->id; }
    public function getBooking(): MemberTankCheckBooking { return $this->booking; }
    public function getResult(): string { return $this->result; }
    public function getInspectedAt(): \DateTimeImmutable { return $this->inspectedAt; }
    public function getNotes(): ?string { return $this->notes; }
    public function getUpdatedBy(): string { return $this->updatedBy; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function record(string $result, \DateTimeImmutable $date, ?string $notes, string $actor): void
    {
        if (!in_array($result, ['passed', 'failed'], true)) {
            throw new \InvalidArgumentException('Ungültiges Prüfergebnis.');
        }
        $this->result = $result;
        $this->inspectedAt = $date;
        $this->notes = $notes;
        $this->updatedBy = mb_substr($actor, 0, 180);
        $this->updatedAt = new \DateTimeImmutable();
    }
}
