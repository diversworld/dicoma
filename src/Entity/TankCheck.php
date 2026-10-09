<?php

namespace App\Entity;

<<<<<<< HEAD
=======
use AllowDynamicProperties;
>>>>>>> origin/main
use App\Repository\TankCheckRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

<<<<<<< HEAD
=======
#[AllowDynamicProperties]
>>>>>>> origin/main
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

<<<<<<< HEAD
    /**
     * @var Collection<int, Tank>
     */
    #[ORM\ManyToMany(targetEntity: Tank::class, inversedBy: 'tankChecks')]
    private Collection $tanks;

    #[ORM\ManyToOne(inversedBy: 'tankChecks')]
    private ?Vendor $vendor = null;

    /**
     * @var Collection<int, TankCheckArticle>
     */
    #[ORM\OneToMany(
        mappedBy: 'tankCheck',
        targetEntity: TankCheckArticle::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
=======
    #[ORM\ManyToMany(targetEntity: Tank::class, inversedBy: 'tankCheck')]
    private Collection $tank;

    #[ORM\ManyToOne(inversedBy: 'tankCheck')]
    private ?Vendor $vendor = null;

    #[ORM\OneToMany(mappedBy: 'tankCheck', targetEntity: TankCheckArticle::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
>>>>>>> origin/main
    private Collection $articles;

    public function __construct()
    {
<<<<<<< HEAD
        $this->tanks = new ArrayCollection();
        $this->articles = new ArrayCollection();
        $this->checkDate = new \DateTime();
=======
        $this->tank = new ArrayCollection();
        $this->checkDate = new \DateTime();
        $this->articles = new ArrayCollection();
>>>>>>> origin/main
    }

    public function __toString(): string
    {
<<<<<<< HEAD
        return $this->checkDate?->format('d.m.Y') ?? '';
=======
        return $this->getCheckDate()->format('d.m.Y');
>>>>>>> origin/main
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCheckDate(): ?\DateTimeInterface
    {
        return $this->checkDate;
    }

<<<<<<< HEAD
    public function setCheckDate(?\DateTimeInterface $checkDate): static
=======
    public function setCheckDate(\DateTimeInterface $checkDate): static
>>>>>>> origin/main
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
<<<<<<< HEAD
    public function getTanks(): Collection
    {
        return $this->tanks;
=======
    public function getTank(): Collection
    {
        return $this->tank;
>>>>>>> origin/main
    }

    public function addTank(Tank $tank): static
    {
<<<<<<< HEAD
        if (!$this->tanks->contains($tank)) {
            $this->tanks->add($tank);
=======
        if (!$this->tank->contains($tank)) {
            $this->tank->add($tank);
>>>>>>> origin/main
        }

        return $this;
    }

    public function removeTank(Tank $tank): static
    {
<<<<<<< HEAD
        $this->tanks->removeElement($tank);
=======
        $this->tank->removeElement($tank);
>>>>>>> origin/main

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
<<<<<<< HEAD
=======
            // set the owning side to null (unless already changed)
>>>>>>> origin/main
            if ($article->getTankCheck() === $this) {
                $article->setTankCheck(null);
            }
        }

        return $this;
    }
}