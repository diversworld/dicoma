<?php

namespace App\Entity;

use App\Repository\TankCheckArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TankCheckArticleRepository::class)]
#[ORM\HasLifecycleCallbacks()]
class TankCheckArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?float $priceNetto = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?float $priceBrutto = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: TankCheck::class, inversedBy: 'tankChecks')]
    private ?TankCheck $tankChecks = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPriceNetto(): ?string
    {
        return $this->priceNetto;
    }

    public function setPriceNetto(string $priceNetto): static
    {
        $this->priceNetto = $priceNetto;

        return $this;
    }

    public function getPriceBrutto(): ?string
    {
        return $this->priceBrutto;
    }

   public function setPriceBrutto(?string $priceBrutto): static
    {
        $this->priceBrutto = $priceBrutto;

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

    public function getTankChecks(): ?TankCheck
    {
        return $this->tankChecks;
    }

    public function setTankChecks(?TankCheck $tankChecks): static
    {
        $this->tankChecks = $tankChecks;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculatePriceBrutto()
    {
        $this->priceBrutto = $this->priceNetto * 1.19;
    }
}
