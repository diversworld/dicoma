<?php

namespace App\Entity;

use AllowDynamicProperties;
use App\Repository\TankCheckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[AllowDynamicProperties] #[ORM\Entity(repositoryClass: TankCheckRepository::class)]
class TankCheck
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $checkDate = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $vendorName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $costInformation = null;

    #[ORM\ManyToMany(targetEntity: Tank::class, inversedBy: 'tankChecks')]
    private Collection $tank;

    public function __construct()
    {
        $this->tank = new ArrayCollection();
        $this->checkDate = new \DateTime();
    }

    public function __toString(): string
    {
        return $this->getCheckDate()->format('d.m.Y');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheckDate(): ?\DateTimeInterface
    {
        return $this->checkDate;
    }
    public function setCheckDate(\DateTimeInterface $CheckDate): static
    {
        $this->CheckDate = $CheckDate;

        return $this;
    }

    public function getVendorName(): ?string
    {
        return $this->vendorName;
    }

    public function setVendorName(?string $vendorName): static
    {
        $this->vendorName = $vendorName;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getCostInformation(): ?string
    {
        return $this->costInformation;
    }

    public function setCostInformation(?string $costInformation): static
    {
        $this->costInformation = $costInformation;

        return $this;
    }

    /**
     * @return Collection<int, Tank>
     */
    public function getTank(): Collection
    {
        return $this->tank;
    }

    public function addTank(Tank $tank): static
    {
        if (!$this->tank->contains($tank)) {
            $this->tank->add($tank);
        }

        return $this;
    }

    public function removeTank(Tank $tank): static
    {
        $this->tank->removeElement($tank);

        return $this;
    }
}
