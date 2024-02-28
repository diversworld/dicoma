<?php

namespace App\Entity;

use App\Repository\TankCheckDetailRepository;
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

    #[ORM\ManyToOne(inversedBy: 'checkDetail')]
    private ?Tank $tank = null;

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
}
