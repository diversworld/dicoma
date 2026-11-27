<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Club;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use App\Service\AdminEquipmentScope;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

trait EquipmentManagerClubTrait
{
    public function __construct(
        private readonly AdminEquipmentScope $equipmentScope,
        private readonly Security $security,
        private readonly ModuleAccess $moduleAccess,
        private readonly RequestStack $requestStack,
    ) {}

    private function equipmentClub(): Club
    {
        $club = $this->equipmentScope->club();
        $user = $this->security->getUser();
        $path = $this->requestStack->getCurrentRequest()?->getPathInfo() ?? '';
        $module = str_contains($path, 'cashbook')
            || str_contains($path, 'cash-closing')
            || str_contains($path, 'cash-payout')
            || str_contains($path, 'cash-correction')
            || str_contains($path, 'daily-cash')
            || str_contains($path, 'fill-billing')
                ? PermissionModule::EQUIPMENT_CASH
                : PermissionModule::EQUIPMENT;
        $action = $this->requestStack->getCurrentRequest()?->isMethodSafe()
            ? PermissionAction::READ
            : PermissionAction::EDIT;
        if (!$user instanceof User || !$this->moduleAccess->can($user, $club, $module, $action)) {
            throw $this->createAccessDeniedException();
        }
        return $club;
    }

    private function equipmentClubId(): int
    {
        return $this->equipmentClub()->getId() ?? throw $this->createAccessDeniedException('Kein Verein zugeordnet.');
    }
}
