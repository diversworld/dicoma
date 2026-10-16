<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Validator\ValidEquipmentAttributes;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_item')]
#[ORM\UniqueConstraint(name: 'uniq_equipment_item_club_inventory', columns: ['club_id', 'inventory_number'])]
#[Assert\Callback('validateConsistency')]
#[ValidEquipmentAttributes]
class EquipmentItem
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Club $club = null;

    #[ORM\ManyToOne(targetEntity: EquipmentCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?EquipmentCategory $category = null;

    #[ORM\ManyToOne(targetEntity: EquipmentManufacturer::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentManufacturer $manufacturer = null;

    #[ORM\ManyToOne(targetEntity: EquipmentSubType::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentSubType $subType = null;

    #[ORM\ManyToOne(targetEntity: EquipmentSize::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentSize $size = null;

    #[ORM\ManyToOne(targetEntity: EquipmentModel::class)]
    #[ORM\JoinColumn(name: 'model_reference_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?EquipmentModel $modelReference = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $color = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    private string $rentalFee = '0.00';

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $purchasedAt = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    private ?string $inventoryNumber = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 30)]
    #[Assert\EqualTo(value: 'individual', message: 'Jeder Verleihartikel ist ein einzelner Inventargegenstand.')]
    private string $stockType = 'individual';

    #[ORM\Column]
    #[Assert\EqualTo(value: 1, message: 'Jeder Inventargegenstand hat genau ein Exemplar.') ]
    private int $totalQuantity = 1;

    #[ORM\Column(length: 30)]
    #[Assert\Choice(choices: ['ready', 'maintenance', 'damaged', 'retired'])]
    private string $conditionStatus = 'ready';

    #[ORM\Column]
    private bool $lendable = true;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $serialNumber = null;

    #[ORM\OneToOne(targetEntity: Tank::class)]
    #[ORM\JoinColumn(name: 'tank_id', referencedColumnName: 'id', nullable: true, onDelete: 'RESTRICT')]
    private ?Tank $tank = null;

    #[ORM\OneToOne(mappedBy: 'equipmentItem', targetEntity: EquipmentRegulatorDetails::class)]
    private ?EquipmentRegulatorDetails $regulatorDetails = null;

    #[ORM\Column(type: 'json')]
    private array $attributeValues = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function validateConsistency(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->category !== null && $this->club !== null) {
            $categoryClub = $this->category->getClub();
            if ($categoryClub !== $this->club && ($categoryClub?->getId() === null || $categoryClub->getId() !== $this->club->getId())) {
                $context->buildViolation('Die Kategorie gehört zu einem anderen Verein.')->atPath('category')->addViolation();
            }
        }
        foreach (['manufacturer' => $this->manufacturer, 'subType' => $this->subType, 'size' => $this->size] as $field => $reference) {
            if ($reference === null || $this->club === null) { continue; }
            $owner = $reference->getClub();
            if ($owner === null || ($owner !== $this->club && ($owner->getId() === null || $owner->getId() !== $this->club->getId()))) {
                $context->buildViolation('Der Stammdatensatz gehört zu einem anderen Verein.')->atPath($field)->addViolation();
            }
        }
        foreach (['subType' => $this->subType, 'size' => $this->size] as $field => $reference) {
            if ($reference === null || $this->category === null) { continue; }
            $refCategory = $reference->getCategory();
            if ($refCategory === null || ($refCategory !== $this->category && ($refCategory->getId() === null || $refCategory->getId() !== $this->category->getId()))) {
                $context->buildViolation('Der Stammdatensatz passt nicht zur gewählten Ausrüstungsart.')->atPath($field)->addViolation();
            }
        }
        if ($this->modelReference !== null) {
            $reference = $this->modelReference;
            if ($this->club === null || $this->category === null || $this->manufacturer === null
                || $reference->getClub()?->getId() !== $this->club->getId()
                || $reference->getCategory()?->getId() !== $this->category->getId()
                || $reference->getManufacturer()?->getId() !== $this->manufacturer->getId()
                || !$reference->isActive()) {
                $context->buildViolation('Das Modell passt nicht zu Verein, Ausrüstungsart oder Hersteller.')->atPath('modelReference')->addViolation();
            }
        }
        if ($this->size !== null && $this->size->getManufacturer() !== null) {
            if ($this->manufacturer === null || $this->size->getManufacturer()->getId() !== $this->manufacturer->getId()) {
                $context->buildViolation('Die Größe gehört zu einem anderen Hersteller.')->atPath('size')->addViolation();
            }
        }
        if ($this->stockType !== 'individual' || $this->totalQuantity !== 1) {
            $context->buildViolation('Einzelgegenstände müssen die Menge 1 besitzen.')->atPath('totalQuantity')->addViolation();
        }
    }

    public function getModelReference(): ?EquipmentModel { return $this->modelReference; }
    public function setModelReference(?EquipmentModel $value): static { $this->modelReference = $value; return $this; }
    public function getTank(): ?Tank { return $this->tank; }
    public function setTank(?Tank $tank): static { $this->tank = $tank; return $this; }
    public function getRegulatorDetails(): ?EquipmentRegulatorDetails { return $this->regulatorDetails; }
    public function setRegulatorDetails(?EquipmentRegulatorDetails $details): static { $this->regulatorDetails = $details; return $this; }

    public function __toString(): string { return ($this->inventoryNumber ?? '').' – '.($this->name ?? ''); }
    public function getId(): ?int { return $this->id; }
    public function getClub(): ?Club { return $this->club; }
    public function setClub(?Club $value): static { $this->club = $value; return $this; }
    public function getCategory(): ?EquipmentCategory { return $this->category; }
    public function setCategory(?EquipmentCategory $value): static { $this->category = $value; return $this; }
    public function getInventoryNumber(): ?string { return $this->inventoryNumber; }
    public function setInventoryNumber(?string $value): static { $this->inventoryNumber = $value; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(?string $value): static { $this->name = $value; return $this; }
    public function getStockType(): string { return $this->stockType; }
    public function setStockType(string $value): static { if ($value !== 'individual') { throw new \InvalidArgumentException('Mengenartikel sind nicht zulässig.'); } $this->stockType = $value; return $this; }
    public function getTotalQuantity(): int { return $this->totalQuantity; }
    public function setTotalQuantity(int $value): static { if ($value !== 1) { throw new \InvalidArgumentException('Jeder Inventargegenstand hat die Menge 1.'); } $this->totalQuantity = $value; return $this; }
    public function getConditionStatus(): string { return $this->conditionStatus; }
    public function setConditionStatus(string $value): static { $this->conditionStatus = $value; return $this; }
    public function isLendable(): bool { return $this->lendable; }
    public function setLendable(bool $value): static { $this->lendable = $value; return $this; }
    public function getSerialNumber(): ?string { return $this->serialNumber; }
    public function setSerialNumber(?string $value): static { $this->serialNumber = $value; return $this; }
    public function getAttributeValuesJson(): string
    {
        return json_encode($this->attributeValues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    public function setAttributeValuesJson(?string $value): static
    {
        $decoded = json_decode($value ?: '{}', true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded) && $decoded !== []) {
            throw new \InvalidArgumentException('Merkmalswerte müssen ein JSON-Objekt sein.');
        }
        $this->attributeValues = $decoded;
        return $this;
    }
    public function getAttributeValues(): array { return $this->attributeValues; }
    public function setAttributeValues(array $value): static { $this->attributeValues = $value; return $this; }
    public function getManufacturer(): ?EquipmentManufacturer { return $this->manufacturer; }
    public function setManufacturer(?EquipmentManufacturer $value): static { $this->manufacturer = $value; return $this; }
    public function getSubType(): ?EquipmentSubType { return $this->subType; }
    public function setSubType(?EquipmentSubType $value): static { $this->subType = $value; return $this; }
    public function getSize(): ?EquipmentSize { return $this->size; }
    public function setSize(?EquipmentSize $value): static { $this->size = $value; return $this; }
    /** Display model from managed reference; preserve legacy text as fallback. */
    public function getDisplayModel(): ?string
    {
        return $this->modelReference?->getName() ?: $this->model;
    }

    public function getModel(): ?string { return $this->model; }
    public function setModel(?string $value): static { $this->model = $value; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $value): static { $this->color = $value; return $this; }
    public function getRentalFee(): string { return $this->rentalFee; }
    public function setRentalFee(string $value): static { $this->rentalFee = $value; return $this; }
    public function getPurchasedAt(): ?\DateTimeImmutable { return $this->purchasedAt; }
    public function setPurchasedAt(?\DateTimeImmutable $value): static { $this->purchasedAt = $value; return $this; }
    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $value): static { $this->notes = $value; return $this; }
}
