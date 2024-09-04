<?php

namespace App\Entity;

use AllowDynamicProperties;
use App\Repository\TankCheckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[AllowDynamicProperties]
#[ORM\Entity(repositoryClass: TankCheckRepository::class)]
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

    #[ORM\ManyToOne(inversedBy: 'tankChecks')]
    private ?Vendor $vendor = null;

    #[ORM\OneToMany(mappedBy: 'tankCheck', targetEntity: TankCheckArticle::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $articles;

    public function __construct()
    {
        $this->tank = new ArrayCollection();
        $this->checkDate = new \DateTime();
        $this->articles = new ArrayCollection();
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

    public function setCheckDate(\DateTimeInterface $checkDate): static
    {
        $this->checkDate = $checkDate;

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

    public function getVendor(): ?Vendor
    {
        return $this->vendor;
    }

    public function setVendor(?Vendor $vendor): static
    {
        $this->vendor = $vendor;

        return $this;
    }

    /**
     * @return Collection<int, TankCheckArticle>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(TankCheckArticle $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
            $article->setTankCheck($this);
        }

        return $this;
    }

    public function removeArticle(TankCheckArticle $article): static
    {
        if ($this->articles->removeElement($article)) {
            // set the owning side to null (unless already changed)
            if ($article->getTankCheck() === $this) {
                $article->setTankCheck(null);
            }
        }

        return $this;
    }
}