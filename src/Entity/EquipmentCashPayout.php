<?php
declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_cash_payout')]
#[ORM\Index(name: 'idx_cash_payout_club_date', columns: ['club_id', 'paid_at'])]
class EquipmentCashPayout
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private Club $club;
    #[ORM\Column(length: 30)] private string $category;
    #[ORM\Column(length: 180)] private string $recipient;
    #[ORM\Column(type: 'integer')] private int $amountCents;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $paidAt;
    #[ORM\Column(length: 180)] private string $paidBy;
    #[ORM\Column(length: 500)] private string $purpose;
    #[ORM\Column(length: 120, nullable: true)] private ?string $documentReference;
    public function __construct(Club $club, string $category, string $recipient, int $amountCents, string $paidBy, string $purpose, ?string $documentReference)
    {
        if (!in_array($category, ['certification', 'training_material', 'equipment_material'], true) || $amountCents <= 0 || $amountCents > 999999999 || trim($recipient) === '' || trim($purpose) === '') { throw new \InvalidArgumentException('Ungültige Auszahlung.'); }
        $this->club = $club; $this->category = $category; $this->recipient = mb_substr(trim($recipient), 0, 180);
        $this->amountCents = $amountCents; $this->paidAt = new \DateTimeImmutable();
        $this->paidBy = mb_substr($paidBy, 0, 180); $this->purpose = mb_substr(trim($purpose), 0, 500);
        $this->documentReference = $documentReference === null || trim($documentReference) === '' ? null : mb_substr(trim($documentReference), 0, 120);
    }
    public function getId(): ?int { return $this->id; }
    public function getClub(): Club { return $this->club; }
    public function getCategory(): string { return $this->category; }
    public function getRecipient(): string { return $this->recipient; }
    public function getAmountCents(): int { return $this->amountCents; }
    public function getPaidAt(): \DateTimeImmutable { return $this->paidAt; }
    public function getPaidBy(): string { return $this->paidBy; }
    public function getPurpose(): string { return $this->purpose; }
    public function getDocumentReference(): ?string { return $this->documentReference; }
}
