<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tuv_payment_receipt')]
#[ORM\UniqueConstraint(name: 'uniq_tuv_payment_booking', columns: ['booking_id'])]
class TuvPaymentReceipt
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: MemberTankCheckBooking::class)]
    #[ORM\JoinColumn(name: 'booking_id', nullable: false, onDelete: 'RESTRICT')]
    private MemberTankCheckBooking $booking;
    #[ORM\Column(type: 'integer')] private int $amountCents;
    #[ORM\Column(length: 20)] private string $method;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $collectedAt;
    #[ORM\Column(length: 180)] private string $collectedBy;
    #[ORM\Column(length: 500, nullable: true)] private ?string $note;
    public function __construct(MemberTankCheckBooking $booking, int $amountCents, string $method, string $actor, ?string $note)
    {
        if ($amountCents <= 0 || $amountCents > 999999999) { throw new \InvalidArgumentException('Ungültiger Betrag.'); }
        if (!in_array($method, ['cash', 'bank'], true)) { throw new \InvalidArgumentException('Ungültige Zahlungsart.'); }
        $this->booking = $booking;
        $this->amountCents = $amountCents;
        $this->method = $method;
        $this->collectedAt = new \DateTimeImmutable();
        $this->collectedBy = mb_substr($actor, 0, 180);
        $this->note = $note !== null ? mb_substr(trim($note), 0, 500) : null;
    }
    public function getId(): ?int { return $this->id; }
    public function getBooking(): MemberTankCheckBooking { return $this->booking; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getMethod(): string { return $this->method; }
    public function getCollectedAt(): \DateTimeImmutable { return $this->collectedAt; }
    public function getCollectedBy(): string { return $this->collectedBy; }
    public function getNote(): ?string { return $this->note; }
}
