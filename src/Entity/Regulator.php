<?php

declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'regulator')]
#[ORM\UniqueConstraint(name: 'uniq_regulator_club_inventory', columns: ['club_id','inventory_number'])]
#[Assert\Callback('validateModels')]
class Regulator
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ?Club $club = null;
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank] private ?string $inventoryNumber = null;
    #[ORM\ManyToOne(targetEntity: EquipmentManufacturer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ?EquipmentManufacturer $manufacturer = null;
    #[ORM\ManyToOne(targetEntity: RegulatorModel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ?RegulatorModel $firstStageModel = null;
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank] private ?string $firstStageSerial = null;
    #[ORM\ManyToOne(targetEntity: RegulatorModel::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ?RegulatorModel $primarySecondStageModel = null;
    #[ORM\Column(length: 100, nullable: true)] private ?string $primarySecondStageSerial = null;
    #[ORM\ManyToOne(targetEntity: RegulatorModel::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'RESTRICT')] private ?RegulatorModel $secondarySecondStageModel = null;
    #[ORM\Column(length: 100, nullable: true)] private ?string $secondarySecondStageSerial = null;
    #[ORM\Column(length: 20, options: ['default'=>'ready'])] private string $conditionStatus = 'ready';
    #[ORM\Column(options: ['default'=>true])] private bool $lendable = true;
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default'=>'0.00'])] private string $rentalFee = '0.00';
    #[ORM\Column(type: 'date_immutable', nullable: true)] private ?\DateTimeImmutable $nextServiceAt = null;
    #[ORM\Column(type: 'text', nullable: true)] private ?string $notes = null;
    public function getId(): ?int {return $this->id;}
    public function getClub(): ?Club {return $this->club;}
    public function setClub(?Club $v): static {$this->club=$v;return $this;}
    public function getInventoryNumber(): ?string {return $this->inventoryNumber;}
    public function setInventoryNumber(?string $v): static {$this->inventoryNumber=$v;return $this;}
    public function getManufacturer(): ?EquipmentManufacturer {return $this->manufacturer;}
    public function setManufacturer(?EquipmentManufacturer $v): static {$this->manufacturer=$v;return $this;}
    public function getFirstStageModel(): ?RegulatorModel {return $this->firstStageModel;}
    public function setFirstStageModel(?RegulatorModel $v): static {$this->firstStageModel=$v;return $this;}
    public function getFirstStageSerial(): ?string {return $this->firstStageSerial;}
    public function setFirstStageSerial(?string $v): static {$this->firstStageSerial=$v;return $this;}
    public function getPrimarySecondStageModel(): ?RegulatorModel {return $this->primarySecondStageModel;}
    public function setPrimarySecondStageModel(?RegulatorModel $v): static {$this->primarySecondStageModel=$v;return $this;}
    public function getPrimarySecondStageSerial(): ?string {return $this->primarySecondStageSerial;}
    public function setPrimarySecondStageSerial(?string $v): static {$this->primarySecondStageSerial=$v;return $this;}
    public function getSecondarySecondStageModel(): ?RegulatorModel {return $this->secondarySecondStageModel;}
    public function setSecondarySecondStageModel(?RegulatorModel $v): static {$this->secondarySecondStageModel=$v;return $this;}
    public function getSecondarySecondStageSerial(): ?string {return $this->secondarySecondStageSerial;}
    public function setSecondarySecondStageSerial(?string $v): static {$this->secondarySecondStageSerial=$v;return $this;}
    public function getConditionStatus(): string {return $this->conditionStatus;}
    public function setConditionStatus(string $v): static {$this->conditionStatus=$v;return $this;}
    public function isLendable(): bool {return $this->lendable;}
    public function setLendable(bool $v): static {$this->lendable=$v;return $this;}
    public function getRentalFee(): string {return $this->rentalFee;}
    public function setRentalFee(string $v): static {$this->rentalFee=$v;return $this;}
    public function getNextServiceAt(): ?\DateTimeImmutable {return $this->nextServiceAt;}
    public function setNextServiceAt(?\DateTimeImmutable $v): static {$this->nextServiceAt=$v;return $this;}
    public function getNotes(): ?string {return $this->notes;}
    public function setNotes(?string $v): static {$this->notes=$v;return $this;}
    public function __toString(): string {return $this->inventoryNumber ?? 'Atemregler';}
    public function validateModels(ExecutionContextInterface $ctx): void {
        if (!$this->club || !$this->manufacturer) {return;}
        if ($this->manufacturer->getClub()?->getId() !== $this->club->getId()) {$ctx->buildViolation('Hersteller gehoert nicht zum Verein.')->atPath('manufacturer')->addViolation();}
        foreach (['firstStageModel'=>'first','primarySecondStageModel'=>'second','secondarySecondStageModel'=>'second'] as $property=>$stage) {
            $model=$this->$property;
            if ($model && ($model->getStage()!==$stage || $model->getManufacturer()?->getId()!==$this->manufacturer->getId() || $model->getClub()?->getId()!==$this->club->getId())) {
                $ctx->buildViolation('Modell passt nicht zu Hersteller, Verein oder Stufe.')->atPath($property)->addViolation();
            }
        }
    }
}
