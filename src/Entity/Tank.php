<?php

namespace App\Entity;

<<<<<<< HEAD
use App\Repository\TankRepository;
=======
use AllowDynamicProperties;
use App\Repository\TankRepository;
use DateTime;
>>>>>>> origin/main
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

<<<<<<< HEAD
#[ORM\Entity(repositoryClass: TankRepository::class)]
=======
#[AllowDynamicProperties] #[ORM\Entity(repositoryClass: TankRepository::class)]
>>>>>>> origin/main
class Tank
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $inventory = null;

    #[ORM\Column]
    private ?int $size = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $buyDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $lastCheckDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $nextCheckDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(length: 20)]
    private ?string $serialnumber = null;

    #[ORM\Column(nullable: true)]
    private ?bool $oxigenClean = null;

<<<<<<< HEAD
    /**
     * @var Collection<int, TankCheck>
     */
    #[ORM\ManyToMany(targetEntity: TankCheck::class, mappedBy: 'tanks')]
    private Collection $tankChecks;

    /**
     * @var Collection<int, TankCheckDetail>
     */
    #[ORM\OneToMany(
        mappedBy: 'tank',
        targetEntity: TankCheckDetail::class
    )]
    private Collection $checkDetails;
=======
    #[ORM\ManyToMany(targetEntity: TankCheck::class, mappedBy: 'tank')]
    private Collection $tankChecks;

    #[ORM\OneToMany(mappedBy: 'tank', targetEntity: TankCheckDetail::class)]
    private Collection $checkDetail;
>>>>>>> origin/main

    public function __construct()
    {
        $this->tankChecks = new ArrayCollection();
<<<<<<< HEAD
        $this->checkDetails = new ArrayCollection();
=======
        $this->tankCheckDetails = new ArrayCollection();
        $this->checkDetail = new ArrayCollection();
>>>>>>> origin/main
    }

    public function __toString(): string
    {
<<<<<<< HEAD
        return $this->inventory ?? '';
=======
        return $this->inventory;
>>>>>>> origin/main
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInventory(): ?string
    {
        return $this->inventory;
    }

    public function setInventory(string $inventory): static
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getBuyDate(): ?\DateTimeInterface
    {
        return $this->buyDate;
    }

    public function setBuyDate(?\DateTimeInterface $buyDate): static
    {
        $this->buyDate = $buyDate;

        return $this;
    }

<<<<<<< HEAD
    public function getLastCheckDate(): ?\DateTimeInterface
    {
        return $this->lastCheckDate;
    }

    public function setLastCheckDate(\DateTimeInterface $lastCheckDate): static
    {
        $this->lastCheckDate = $lastCheckDate;

        return $this;
    }

    public function getNextCheckDate(): ?\DateTimeInterface
    {
        return $this->nextCheckDate;
    }

    public function setNextCheckDate(?\DateTimeInterface $nextCheckDate): static
    {
        $this->nextCheckDate = $nextCheckDate;

        return $this;
    }

=======
>>>>>>> origin/main
    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getSerialnumber(): ?string
    {
        return $this->serialnumber;
    }

    public function setSerialnumber(string $serialnumber): static
    {
        $this->serialnumber = $serialnumber;

        return $this;
    }

    public function isOxigenClean(): ?bool
    {
        return $this->oxigenClean;
    }

    public function setOxigenClean(?bool $oxigenClean): static
    {
        $this->oxigenClean = $oxigenClean;

        return $this;
    }

<<<<<<< HEAD
=======
    public function getLastCheckDate(): ?\DateTimeInterface
    {
        return $this->lastCheckDate;
    }

    public function setLastCheckDate(\DateTimeInterface $lastCheckDate): static
    {
        $this->lastCheckDate = $lastCheckDate;

        return $this;
    }

    public function getNextCheckDate(): ?\DateTimeInterface
    {
        return $this->nextCheckDate;
    }

    public function setNextCheckDate(?\DateTimeInterface $nextCheckDate): static
    {
        $this->nextCheckDate = $nextCheckDate;

        return $this;
    }

>>>>>>> origin/main
    /**
     * @return Collection<int, TankCheck>
     */
    public function getTankChecks(): Collection
    {
        return $this->tankChecks;
    }

    public function addTankCheck(TankCheck $tankCheck): static
    {
        if (!$this->tankChecks->contains($tankCheck)) {
            $this->tankChecks->add($tankCheck);
            $tankCheck->addTank($this);
        }

        return $this;
    }

    public function removeTankCheck(TankCheck $tankCheck): static
    {
        if ($this->tankChecks->removeElement($tankCheck)) {
            $tankCheck->removeTank($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, TankCheckDetail>
     */
<<<<<<< HEAD
    public function getCheckDetails(): Collection
    {
        return $this->checkDetails;
=======
    public function getTankCheckDetails(): Collection
    {
        return $this->tankCheckDetails;
    }

    public function addTankCheckDetail(TankCheckDetail $tankCheckDetail): static
    {
        if (!$this->tankCheckDetails->contains($tankCheckDetail)) {
            $this->tankCheckDetails->add($tankCheckDetail);
            $tankCheckDetail->setTank($this);
        }

        return $this;
    }

    public function removeTankCheckDetail(TankCheckDetail $tankCheckDetail): static
    {
        if ($this->tankCheckDetails->removeElement($tankCheckDetail)) {
            // set the owning side to null (unless already changed)
            if ($tankCheckDetail->getTank() === $this) {
                $tankCheckDetail->setTank(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TankCheckDetail>
     */
    public function getCheckDetail(): Collection
    {
        return $this->checkDetail;
>>>>>>> origin/main
    }

    public function addCheckDetail(TankCheckDetail $checkDetail): static
    {
<<<<<<< HEAD
        if (!$this->checkDetails->contains($checkDetail)) {
            $this->checkDetails->add($checkDetail);
=======
        if (!$this->checkDetail->contains($checkDetail)) {
            $this->checkDetail->add($checkDetail);
>>>>>>> origin/main
            $checkDetail->setTank($this);
        }

        return $this;
    }

    public function removeCheckDetail(TankCheckDetail $checkDetail): static
    {
<<<<<<< HEAD
        if ($this->checkDetails->removeElement($checkDetail)) {
=======
        if ($this->checkDetail->removeElement($checkDetail)) {
            // set the owning side to null (unless already changed)
>>>>>>> origin/main
            if ($checkDetail->getTank() === $this) {
                $checkDetail->setTank(null);
            }
        }

        return $this;
    }
<<<<<<< HEAD
}
=======
}
>>>>>>> origin/main
