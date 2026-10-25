<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_asset_record')]
class EquipmentAssetRecord
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: EquipmentItem::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentItem $equipmentItem = null;

    #[ORM\OneToOne(targetEntity: Regulator::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Regulator $regulator = null;

    #[ORM\OneToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?Tank $tank = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['club', 'private', 'borrowed'])]
    private string $ownership = 'club';

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $ownerName = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Length(max: 180)]
    private ?string $location = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $purchasePriceCents = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 120)]
    private ?int $inspectionIntervalMonths = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextInspectionAt = null;

    #[ORM\Column]
    private bool $approvalRequired = false;

    #[ORM\Column(type: 'json')]
    private array $history = [];

    public function __construct(EquipmentItem|Regulator|Tank $asset)
    {
        if ($asset instanceof EquipmentItem) { $this->equipmentItem = $asset; $this->purchasedAt = $asset->getPurchasedAt(); }
        elseif ($asset instanceof Regulator) { $this->regulator = $asset; }
        else { $this->tank = $asset; $this->purchasedAt = $asset->getBuyDate() ? \DateTimeImmutable::createFromInterface($asset->getBuyDate()) : null; }
    }

    #[Assert\Callback]
    public function validateOwner(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->ownership !== 'club' && trim($this->ownerName ?? '') === '') {
            $context->buildViolation('Eigentümer ist bei Privateigentum oder Leihgaben erforderlich.')->atPath('ownerName')->addViolation();
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getAsset(): EquipmentItem|Regulator|Tank { return $this->equipmentItem ?? $this->regulator ?? $this->tank; }
    public function getOwnership(): string { return $this->ownership; }
    public function setOwnership(string $value): static { $this->ownership = $value; return $this; }
    public function getOwnerName(): ?string { return $this->ownerName; }
    public function setOwnerName(?string $value): static { $this->ownerName = $value; return $this; }
    public function getLocation(): ?string { return $this->location; }
    public function setLocation(?string $value): static { $this->location = $value; return $this; }
    public function getPurchasedAt(): ?\DateTimeImmutable { return $this->purchasedAt; }
    public function setPurchasedAt(?\DateTimeImmutable $value): static { $this->purchasedAt = $value; return $this; }
    public function getPurchasePriceCents(): ?int { return $this->purchasePriceCents; }
    public function setPurchasePriceCents(?int $value): static { $this->purchasePriceCents = $value; return $this; }
    public function getInspectionIntervalMonths(): ?int { return $this->inspectionIntervalMonths; }
    public function setInspectionIntervalMonths(?int $value): static { $this->inspectionIntervalMonths = $value; return $this; }
    public function getNextInspectionAt(): ?\DateTimeImmutable { return $this->nextInspectionAt; }
    public function setNextInspectionAt(?\DateTimeImmutable $value): static { $this->nextInspectionAt = $value; return $this; }
    public function isApprovalRequired(): bool { return $this->approvalRequired; }
    public function setApprovalRequired(bool $value): static { $this->approvalRequired = $value; return $this; }
    public function getHistory(): array { return $this->history; }
    public function record(string $event, string $actor, string $description): void
    {
        $this->history[] = ['event' => $event, 'actor' => $actor, 'description' => $description, 'at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
    }
}
