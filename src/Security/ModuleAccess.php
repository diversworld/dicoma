<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Club;
use App\Entity\ClubFunctionAssignment;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class ModuleAccess
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoleHierarchyInterface $hierarchy,
    ) {}

    public function can(User $user, Club $club, PermissionModule $module, PermissionAction $action, ?\DateTimeImmutable $date = null): bool
    {
        if (!$this->em->contains($user)) {
            $user = $user->getId() === null ? null : $this->em->find(User::class, $user->getId());
            if ($user === null) {
                return false;
            }
        }
        if (in_array(User::ROLE_SUPER_ADMIN, $this->hierarchy->getReachableRoleNames($user->getRoles()), true)) {
            return true;
        }
        $date ??= new \DateTimeImmutable('today');
        foreach ($this->em->getRepository(ClubFunctionAssignment::class)->findBy(['user' => $user]) as $assignment) {
            $function = $assignment->getClubFunction();
            $functionClub = $function->getClub();
            if (($functionClub === $club || ($functionClub->getId() !== null && $functionClub->getId() === $club->getId()))
                && $assignment->isEffectiveOn($date) && $function->hasModulePermission($module, $action)) {
                return true;
            }
        }
        return false;
    }
}
