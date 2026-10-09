<?php

namespace App\Entity;

use App\Repository\TankCheckDetailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TankCheckDetailRepository::class)]
class TankCheckDetail
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

<<<<<<< HEAD
    /*
     * Aktuell gibt es auf TankCheck keine Gegen-Collection für
     * TankCheckDetail. Deshalb bewusst kein inversedBy.
     */
    #[ORM\ManyToOne(targetEntity: TankCheck::class)]
=======
    #[ORM\ManyToOne(inversedBy: 'article')]
>>>>>>> origin/main
    private ?TankCheck $tankCheck = null;

    #[ORM\ManyToOne(targetEntity: TankCheckArticle::class)]
    private ?TankCheckArticle $article = null;

<<<<<<< HEAD
    #[ORM\ManyToOne(inversedBy: 'checkDetails')]
    private ?Tank $tank = null;

    /*
     * Doctrine DECIMAL wird absichtlich als string behandelt.
     * Dadurch entstehen keine Rundungsfehler durch IEEE-Floats.
     */
    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 10,
        scale: 2,
        nullable: true
    )]
    private ?string $amount = null;
=======
    #[ORM\ManyToOne(inversedBy: 'checkDetail')]
    private ?Tank $tank = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?float $amount = null;
>>>>>>> origin/main

    public function getId(): ?int
    {
        return $this->id;
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

<<<<<<< HEAD
    public function getArticle(): ?TankCheckArticle
    {
        return $this->article;
    }

    public function setArticle(?TankCheckArticle $article): static
    {
        $this->article = $article;

        return $this;
    }

=======
>>>>>>> origin/main
    public function getTank(): ?Tank
    {
        return $this->tank;
    }

    public function setTank(?Tank $tank): static
    {
        $this->tank = $tank;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

<<<<<<< HEAD
    public function setAmount(?string $amount): static
=======
    public function setAmount(string $amount): static
>>>>>>> origin/main
    {
        $this->amount = $amount;

        return $this;
    }
<<<<<<< HEAD
}
=======

    public function getArticle(): ?TankCheckArticle
    {
        return $this->article;
    }

    public function setArticle(?TankCheckArticle $article): static
    {
        $this->article = $article;

        return $this;
    }
}
>>>>>>> origin/main
