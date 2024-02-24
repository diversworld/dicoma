<?php

namespace App\Entity;

use App\Repository\VendorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VendorRepository::class)]
class Vendor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private ?string $name = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $street = null;

    #[ORM\Column(nullable: true)]
    private ?int $postal = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 25)]
    private ?string $phone = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 250, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\OneToMany(mappedBy: 'vendor', targetEntity: TankCheck::class)]
    private Collection $tankChecks;

    public function __construct()
    {
        $this->tankChecks = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(?string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;

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
            $tankCheck->setVendor($this);
        }

        return $this;
    }

    public function removeTankCheck(TankCheck $tankCheck): static
    {
        if ($this->tankChecks->removeElement($tankCheck)) {
            // set the owning side to null (unless already changed)
            if ($tankCheck->getVendor() === $this) {
                $tankCheck->setVendor(null);
            }
        }

        return $this;
    }

    public function getPostal(): ?int
    {
        return $this->postal;
    }

    public function setPostal(?int $postal): static
    {
        $this->postal = $postal;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }
}
