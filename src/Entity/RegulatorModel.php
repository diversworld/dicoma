<?php

declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
#[ORM\Table(name: 'regulator_model')]
#[ORM\UniqueConstraint(name: 'uniq_reg_model_club_manufacturer_stage_name', columns: ['club_id','manufacturer_id','stage','name'])]
#[Assert\Callback('validateOwnership')]
class RegulatorModel
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Club::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ?Club $club = null;
    #[ORM\ManyToOne(targetEntity: EquipmentManufacturer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')] private ?EquipmentManufacturer $manufacturer = null;
    #[ORM\Column(length: 10)]
    #[Assert\Choice(['first','second'])] private string $stage = 'first';
    #[ORM\Column(length: 120)]
    #[Assert\NotBlank] private ?string $name = null;
    #[ORM\Column] private bool $active = true;
    public function getId(): ?int {return $this->id;}
    public function getClub(): ?Club {return $this->club;}
    public function setClub(?Club $v): static {$this->club=$v;return $this;}
    public function getManufacturer(): ?EquipmentManufacturer {return $this->manufacturer;}
    public function setManufacturer(?EquipmentManufacturer $v): static {$this->manufacturer=$v;return $this;}
    public function getStage(): string {return $this->stage;}
    public function setStage(string $v): static {$this->stage=$v;return $this;}
    public function getName(): ?string {return $this->name;}
    public function setName(?string $v): static {$this->name=$v;return $this;}
    public function isActive(): bool {return $this->active;}
    public function setActive(bool $v): static {$this->active=$v;return $this;}
    public function __toString(): string {return ($this->manufacturer?->getName() ?? '').' - '.($this->name ?? '').' ('.($this->stage==='first'?'1.':'2.').' Stufe)';}
    public function validateOwnership(ExecutionContextInterface $ctx): void {
        if ($this->club && $this->manufacturer && $this->club->getId() !== $this->manufacturer->getClub()?->getId()) {
            $ctx->buildViolation('Hersteller und Modell muessen zum selben Verein gehoeren.')->atPath('manufacturer')->addViolation();
        }
    }
}
