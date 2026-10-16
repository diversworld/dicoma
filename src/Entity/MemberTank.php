<?php

declare(strict_types=1);
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'member_tank')]
#[ORM\Index(name: 'idx_member_tank_member', columns: ['member_id'])]
class MemberTank
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
	
    #[ORM\ManyToOne(targetEntity: Member::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Member $member = null;
    
	#[ORM\Column(length: 100)]
    private string $label = '';
    
	#[ORM\Column(length: 120)]
    private string $serialNumber = '';
    
	#[ORM\Column(length: 120, nullable: true)]
    private ?string $manufacturer = null;
    
	#[ORM\Column(length: 40, nullable: true)]
    private ?string $volume = null;
    
	#[ORM\Column(
		name: 'water_capacity_liters',
		type: 'decimal',
		precision: 5,
		scale: 2,
		nullable: true
	)]
	private ?string $waterCapacityLiters = null;
	    
	#[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $nextInspectionAt = null;

    public function getWaterCapacityLiters(): ?string
	{
		return $this->waterCapacityLiters;
	}

	public function setWaterCapacityLiters(?string $waterCapacityLiters): static
	{
		$this->waterCapacityLiters = $waterCapacityLiters;

		return $this;
	}
	
	public function getId(): ?int { return $this->id; }
    public function getMember(): ?Member { return $this->member; }
    public function setMember(Member $member): self { $this->member = $member; return $this; }
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $v): self { $this->label = $v; return $this; }
    public function getSerialNumber(): string { return $this->serialNumber; }
    public function setSerialNumber(string $v): self { $this->serialNumber = $v; return $this; }
    public function getManufacturer(): ?string { return $this->manufacturer; }
    public function setManufacturer(?string $v): self { $this->manufacturer = $v; return $this; }
    public function getVolume(): ?string { return $this->volume; }
    public function setVolume(?string $v): self { $this->volume = $v; return $this; }
    public function getNextInspectionAt(): ?\DateTimeImmutable { return $this->nextInspectionAt; }
    public function setNextInspectionAt(?\DateTimeImmutable $v): self { $this->nextInspectionAt = $v; return $this; }
}
