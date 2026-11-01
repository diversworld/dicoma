<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Service\AdminEquipmentScope;

trait EquipmentManagerClubTrait
{
    public function __construct(private readonly AdminEquipmentScope $equipmentScope) {}

    private function equipmentClub(): Club
    {
        return $this->equipmentScope->club();
    }

    private function equipmentClubId(): int
    {
        return $this->equipmentClub()->getId() ?? throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
    }
}
