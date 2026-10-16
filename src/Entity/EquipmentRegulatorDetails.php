<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_regulator_details')]
class EquipmentRegulatorDetails
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: EquipmentItem::class, inversedBy: 'regulatorDetails')]
    #[ORM\JoinColumn(name: 'equipment_item_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?EquipmentItem $equipmentItem = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $firstStageSerial = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $firstStageModel = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank]
    private ?string $primarySecondStageSerial = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $primarySecondStageModel = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $secondarySecondStageSerial = null;

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $secondarySecondStageModel = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextServiceAt = null;

    public function __toString(): string { return (string) ($this->equipmentItem ?? 'Atemregler'); }
    public function getId(): ?int { return $this->id; }
    public function getEquipmentItem(): ?EquipmentItem { return $this->equipmentItem; }
    public function setEquipmentItem(?EquipmentItem $value): static { $this->equipmentItem = $value; return $this; }
    public function getFirstStageSerial(): ?string { return $this->firstStageSerial; }
    public function setFirstStageSerial(?string $v): static { $this->firstStageSerial = $v; return $this; }
    public function getFirstStageModel(): ?string { return $this->firstStageModel; }
    public function setFirstStageModel(?string $v): static { $this->firstStageModel = $v; return $this; }
    public function getPrimarySecondStageSerial(): ?string { return $this->primarySecondStageSerial; }
    public function setPrimarySecondStageSerial(?string $v): static { $this->primarySecondStageSerial = $v; return $this; }
    public function getPrimarySecondStageModel(): ?string { return $this->primarySecondStageModel; }
    public function setPrimarySecondStageModel(?string $v): static { $this->primarySecondStageModel = $v; return $this; }
    public function getSecondarySecondStageSerial(): ?string { return $this->secondarySecondStageSerial; }
    public function setSecondarySecondStageSerial(?string $v): static { $this->secondarySecondStageSerial = $v; return $this; }
    public function getSecondarySecondStageModel(): ?string { return $this->secondarySecondStageModel; }
    public function setSecondarySecondStageModel(?string $v): static { $this->secondarySecondStageModel = $v; return $this; }
    public function getNextServiceAt(): ?\DateTimeImmutable { return $this->nextServiceAt; }
    public function setNextServiceAt(?\DateTimeImmutable $v): static { $this->nextServiceAt = $v; return $this; }
}
