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

    #[ORM\ManyToOne(inversedBy: 'article')]
    private ?TankCheck $tankCheck = null;

    #[ORM\ManyToOne(targetEntity: TankCheckArticle::class)]
    private ?TankCheckArticle $article = null;

    #[ORM\ManyToOne(inversedBy: 'checkDetail')]
    private ?Tank $tank = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?float $amount = null;

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

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

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
