<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_booking_item')]
#[ORM\Index( name: 'IDX_EQ_BI_BOOK', columns: ['booking_id'])]
#[ORM\Index(name: 'IDX_EQ_BI_ITEM', columns: ['equipment_item_id'])]
#[ORM\Index(name: 'IDX_EQ_BOOK_REG',columns: ['regulator_id'])]
#[ORM\Index(name: 'IDX_EQ_BOOK_TANK',columns: ['tank_id'])]
#[ORM\UniqueConstraint(name: 'uniq_booking_item',columns: ['booking_id', 'equipment_item_id'])]
#[ORM\UniqueConstraint(name: 'uniq_booking_regulator',columns: ['booking_id', 'regulator_id'])]
#[ORM\UniqueConstraint(name: 'uniq_booking_tank', columns: ['booking_id', 'tank_id'])]
class EquipmentBookingItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EquipmentBooking::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?EquipmentBooking $booking = null;

    #[ORM\ManyToOne(targetEntity: EquipmentItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentItem $equipmentItem = null;

    #[ORM\ManyToOne(targetEntity: Regulator::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Regulator $regulator = null;

    #[ORM\ManyToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Tank $tank = null;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(length: 20, options: ['default' => 'reserved'])]
    private string $loanStatus = 'reserved';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $returnedAt = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $returnCondition = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $returnNotes = null;

    public function getLoanStatus(): string { return $this->loanStatus; }
    public function markIssued(\DateTimeImmutable $at): void
    {
        if ($this->loanStatus !== 'reserved') { throw new \DomainException('Nur reservierte Positionen koennen ausgegeben werden.'); }
        $this->loanStatus = 'issued';
        $this->issuedAt = $at;
    }
    public function markReturned(\DateTimeImmutable $at, string $condition, ?string $notes): void
    {
        if ($this->loanStatus !== 'issued') { throw new \DomainException('Nur ausgegebene Positionen koennen zurueckgenommen werden.'); }
        if (!in_array($condition, ['ready', 'maintenance', 'damaged'], true)) { throw new \DomainException('Ungueltiger Rueckgabezustand.'); }
        $this->loanStatus = 'returned';
        $this->returnedAt = $at;
        $this->returnCondition = $condition;
        $this->returnNotes = $notes;
    }
    public function getIssuedAt(): ?\DateTimeImmutable { return $this->issuedAt; }
    public function getReturnedAt(): ?\DateTimeImmutable { return $this->returnedAt; }
    public function getReturnCondition(): ?string { return $this->returnCondition; }
    public function getReturnNotes(): ?string { return $this->returnNotes; }

    public function __toString(): string { return $this->getAssetLabel(); }
    public function getAssetLabel(): string { return $this->equipmentItem?->getName() ?? (string)($this->regulator ?? $this->tank?->getInventory() ?? 'Ausrüstung'); }
    public function getAssetInventory(): string { return $this->equipmentItem?->getInventoryNumber() ?? $this->regulator?->getInventoryNumber() ?? $this->tank?->getInventory() ?? ''; }
    public function getAssetType(): string { return $this->equipmentItem ? 'equipment' : ($this->regulator ? 'regulator' : ($this->tank ? 'tank' : 'unknown')); }
    #[Assert\Callback]
    public function validateSingleAsset(\Symfony\Component\Validator\Context\ExecutionContextInterface $ctx): void {
        $n=(int)($this->equipmentItem!==null)+(int)($this->regulator!==null)+(int)($this->tank!==null);
        if ($n!==1) {$ctx->buildViolation('Genau ein Inventargegenstand muss zugeordnet sein.')->addViolation();}
        if ($this->quantity!==1) {$ctx->buildViolation('Nur Einzelinventar erlaubt.')->atPath('quantity')->addViolation();}
    }
    public function getId(): ?int { return $this->id; }
    public function getBooking(): ?EquipmentBooking { return $this->booking; }
    public function setBooking(?EquipmentBooking $value): static { $this->booking = $value; return $this; }
    public function getEquipmentItem(): ?EquipmentItem { return $this->equipmentItem; }
    public function setEquipmentItem(?EquipmentItem $value): static { $this->equipmentItem = $value; return $this; }
    public function getRegulator(): ?Regulator {return $this->regulator;}
    public function setRegulator(?Regulator $v): static {$this->regulator=$v;return $this;}
    public function getTank(): ?Tank {return $this->tank;}
    public function setTank(?Tank $v): static {$this->tank=$v;return $this;}
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $value): static { $this->quantity = $value; return $this; }
}
