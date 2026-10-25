<?php

declare(strict_types=1);

namespace App\Entity;

use App\Validator\AvailableEquipmentReservation;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_reservation')]
#[ORM\Index(name: 'idx_equipment_reservation_period', columns: ['equipment_item_id', 'starts_at', 'ends_at'])]
#[AvailableEquipmentReservation]
#[Assert\Callback('validateConsistency')]
class EquipmentReservation
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: EquipmentItem::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?EquipmentItem $equipmentItem = null;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Member $member = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(length: 20, options: ['default' => 'reserved'])]
    private string $status = 'reserved';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $returnedAt = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $conditionAtIssue = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $conditionAtReturn = null;

	#[ORM\Column(name: 'returned_notes',type: 'text',nullable: true	)]
	private ?string $returnedNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function validateConsistency(ExecutionContextInterface $context): void
    {
        if ($this->startsAt !== null && $this->endsAt !== null && $this->startsAt >= $this->endsAt) {
            $context->buildViolation('Das Ende muss nach dem Beginn liegen.')->atPath('endsAt')->addViolation();
        }
        if ($this->equipmentItem === null || $this->member === null) { return; }
        $itemClub = $this->equipmentItem->getClub()?->getId();
        $memberClub = $this->member->getClub()?->getId();
        if ($itemClub === null || $memberClub === null || $itemClub !== $memberClub) {
            $context->buildViolation('Mitglied und Ausrüstung müssen demselben Verein angehören.')->atPath('member')->addViolation();
        }
        if (!$this->equipmentItem->isLendable() || $this->equipmentItem->getConditionStatus() !== 'ready') {
            $context->buildViolation('Diese Ausrüstung ist nicht verleihbar.')->atPath('equipmentItem')->addViolation();
        }
        if ($this->quantity !== 1) {
            $context->buildViolation('Pro Reservierung wird genau ein Inventargegenstand ausgewählt.')->atPath('quantity')->addViolation();
        }
    }

    public function __toString(): string { return ($this->equipmentItem?->getName() ?? 'Ausrüstung').' – '.($this->member?->__toString() ?? 'Mitglied'); }
    public function getId(): ?int { return $this->id; }
    public function getEquipmentItem(): ?EquipmentItem { return $this->equipmentItem; }
    public function setEquipmentItem(?EquipmentItem $value): static { $this->equipmentItem = $value; return $this; }
    public function getMember(): ?Member { return $this->member; }
    public function setMember(?Member $value): static { $this->member = $value; return $this; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): static { $this->startsAt = $value; return $this; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $value): static { $this->endsAt = $value; return $this; }
    public function getQuantity(): int { return $this->quantity; }
    public function setQuantity(int $value): static { $this->quantity = $value; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $value): static { $this->status = $value; return $this; }
    public function getLoanActions(): self { return $this; }
    public function getIssuedAt(): ?\DateTimeImmutable { return $this->issuedAt; }
    public function setIssuedAt(?\DateTimeImmutable $value): static { $this->issuedAt = $value; return $this; }
    public function getReturnedAt(): ?\DateTimeImmutable { return $this->returnedAt; }
    public function setReturnedAt(?\DateTimeImmutable $value): static { $this->returnedAt = $value; return $this; }
    public function getConditionAtIssue(): ?string { return $this->conditionAtIssue; }
    public function setConditionAtIssue(?string $value): static { $this->conditionAtIssue = $value; return $this; }
    public function getConditionAtReturn(): ?string { return $this->conditionAtReturn; }
    public function setConditionAtReturn(?string $value): static { $this->conditionAtReturn = $value; return $this; }
    public function getReturnNotes(): ?string { return $this->returnedNotes; }
    public function setReturnNotes(?string $value): static { $this->returnedNotes = $value; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $value): static { $this->notes = $value; return $this; }
}
