<?php

namespace App\Entity;

use App\Repository\TankCheckArticleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TankCheckArticleRepository::class)]
<<<<<<< HEAD
#[ORM\HasLifecycleCallbacks]
=======
#[ORM\HasLifecycleCallbacks()]
>>>>>>> origin/main
class TankCheckArticle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

<<<<<<< HEAD
    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2
    )]
    private ?string $priceNetto = null;

    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        nullable: true
    )]
    private ?string $priceBrutto = null;
=======
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?float $priceNetto = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?float $priceBrutto = null;
>>>>>>> origin/main

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

<<<<<<< HEAD
    #[ORM\ManyToOne(
        targetEntity: TankCheck::class,
        inversedBy: 'articles'
    )]
=======
    #[ORM\ManyToOne(targetEntity: TankCheck::class, inversedBy: 'articles')]
>>>>>>> origin/main
    #[ORM\JoinColumn(nullable: false)]
    private ?TankCheck $tankCheck = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isDefault = false;

    #[ORM\Column(nullable: true)]
    private ?bool $standard = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

<<<<<<< HEAD
=======
    // Getter und Setter

>>>>>>> origin/main
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

<<<<<<< HEAD
    public function getPriceNetto(): ?string
=======
    public function getPriceNetto(): ?float
>>>>>>> origin/main
    {
        return $this->priceNetto;
    }

<<<<<<< HEAD
    public function setPriceNetto(string $priceNetto): static
=======
    public function setPriceNetto(float $priceNetto): static
>>>>>>> origin/main
    {
        $this->priceNetto = $priceNetto;

        return $this;
    }

<<<<<<< HEAD
    public function getPriceBrutto(): ?string
=======
    public function getPriceBrutto(): ?float
>>>>>>> origin/main
    {
        return $this->priceBrutto;
    }

<<<<<<< HEAD
    public function setPriceBrutto(?string $priceBrutto): static
=======
    public function setPriceBrutto(?float $priceBrutto): static
>>>>>>> origin/main
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

    public function getTankCheck(): ?TankCheck
    {
        return $this->tankCheck;
    }

    public function setTankCheck(?TankCheck $tankCheck): static
    {
        $this->tankCheck = $tankCheck;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

<<<<<<< HEAD
    public function setDefault(bool $isDefault): static
    {
        return $this->setIsDefault($isDefault);
=======
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculatePriceBrutto()
    {
        $this->priceBrutto = $this->priceNetto * 1.19;
    }

    /**
     * Convert the entity to its string representation.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->title ?? 'n/a';
>>>>>>> origin/main
    }

    public function isStandard(): ?bool
    {
        return $this->standard;
    }

    public function setStandard(?bool $standard): static
    {
        $this->standard = $standard;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): static
    {
        $this->size = $size;

        return $this;
    }

<<<<<<< HEAD
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculatePriceBrutto(): void
    {
        if ($this->priceNetto === null) {
            $this->priceBrutto = null;

            return;
        }

        /*
         * Auf zwei Nachkommastellen formatieren.
         *
         * Die endgültige Geldberechnung können wir später noch
         * zentralisieren. Für die bestehende Funktionalität
         * behalten wir zunächst die bisherige 19-%-Berechnung.
         */
        $this->priceBrutto = number_format(
            ((float) $this->priceNetto) * 1.19,
            2,
            '.',
            ''
        );
    }

    public function __toString(): string
    {
        return $this->title ?? 'n/a';
=======
    public function setDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
>>>>>>> origin/main
    }
}