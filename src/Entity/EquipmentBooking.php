<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_booking')]
#[ORM\Index(name: 'IDX_EQ_BOOK_MEMBER', columns: ['member_id'])]
#[Assert\Callback('validateBooking')]
class EquipmentBooking
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Member $member = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'reserved'])]
    private string $status = 'reserved';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, EquipmentBookingItem> */
    #[ORM\OneToMany(mappedBy: 'booking', targetEntity: EquipmentBookingItem::class, cascade: ['persist'], orphanRemoval: true)]
    #[Assert\Valid]
    #[Assert\Count(min: 1, minMessage: 'Mindestens einen Ausrüstungsartikel auswählen.')]
    private Collection $items;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $agreedRentalFeeCents = null;

    public function getAgreedRentalFeeCents(): ?int { return $this->agreedRentalFeeCents; }
    public function setAgreedRentalFeeCents(?int $cents): static
    {
        if ($cents !== null && $cents < 0) { throw new \InvalidArgumentException('Invalid amount.'); }
        $this->agreedRentalFeeCents = $cents;
        return $this;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function validateBooking(ExecutionContextInterface $context): void
    {
        if ($this->startsAt && $this->endsAt && $this->startsAt >= $this->endsAt) {
            $context->buildViolation('Das Ende muss nach dem Beginn liegen.')->atPath('endsAt')->addViolation();
        }
        $seen = [];
        foreach ($this->items as $position => $item) {
            $id = $item->getEquipmentItem()?->getId() ?? $item->getRegulator()?->getId() ?? $item->getTank()?->getId();
            $key = $item->getAssetType().':'.$id;
            if ($id !== null && isset($seen[$key])) {
                $context->buildViolation('Ein Gegenstand darf pro Buchung nur einmal vorkommen.')->atPath('items['.$position.']')->addViolation();
            }
            if ($id !== null) { $seen[$key] = true; }
        }
    }

    public function __toString(): string { return 'Buchung #'.($this->id ?? 'neu'); }
    public function getId(): ?int { return $this->id; }
    public function getMember(): ?Member { return $this->member; }
    public function setMember(?Member $value): static { $this->member = $value; return $this; }
    public function getStartsAt(): ?\DateTimeImmutable { return $this->startsAt; }
    public function setStartsAt(?\DateTimeImmutable $value): static { $this->startsAt = $value; return $this; }
    public function getEndsAt(): ?\DateTimeImmutable { return $this->endsAt; }
    public function setEndsAt(?\DateTimeImmutable $value): static { $this->endsAt = $value; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $value): static { $this->status = $value; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $value): static { $this->notes = $value; return $this; }
    /** @return Collection<int, EquipmentBookingItem> */
    public function getItems(): Collection { return $this->items; }
    public function addItem(EquipmentBookingItem $item): static
    {
        if (!$this->items->contains($item)) { $this->items->add($item); $item->setBooking($this); }
        return $this;
    }
    public function removeItem(EquipmentBookingItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getBooking() === $this) { $item->setBooking(null); }
        return $this;
    }
}
