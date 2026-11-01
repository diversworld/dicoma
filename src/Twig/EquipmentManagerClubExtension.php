<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Club;
use App\Service\AdminEquipmentScope;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EquipmentManagerClubExtension extends AbstractExtension
{
    public function __construct(private readonly AdminEquipmentScope $scope) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('equipment_manager_club', $this->club(...))];
    }

    public function club(): Club
    {
        return $this->scope->club();
    }
}
