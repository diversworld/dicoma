<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Club;
use App\Entity\ClubFunctionAssignment;
use App\Entity\FileAction;
use App\Entity\MediaFolder;
use App\Entity\MediaFolderPermission;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class FolderPermissions
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoleHierarchyInterface $hierarchy,
        private readonly ModuleAccess $moduleAccess,
    ) {}

    public function can(User $user, Club $club, ?MediaFolder $folder, FileAction $action, ?\DateTimeImmutable $date = null, ?PermissionAction $moduleAction = null): bool
    {
        if (!$this->em->contains($user)) {
            $user = $user->getId() === null ? null : $this->em->find(User::class, $user->getId());
            if ($user === null) {
                return false;
            }
        }
        if ($folder !== null && !self::sameClub($folder->getClub(), $club)) {
            return false;
        }
        $moduleAction ??= match ($action) {
            FileAction::READ => PermissionAction::READ,
            FileAction::UPLOAD => PermissionAction::CREATE,
            FileAction::EDIT => PermissionAction::EDIT,
            FileAction::DELETE => PermissionAction::DELETE,
        };
        if (!$this->moduleAccess->can($user, $club, PermissionModule::FILES, $moduleAction, $date)) {
            return false;
        }
        if ($folder === null) {
            $roles = $this->hierarchy->getReachableRoleNames($user->getRoles());
            if (!in_array(User::ROLE_ADMIN, $roles, true)) {
                return false;
            }
            if (in_array(User::ROLE_SUPER_ADMIN, $roles, true)) {
                return true;
            }
            $member = $user->getMember();
            return $member !== null && $member->isActiveMember() && $member->getArchivedAt() === null
                && $member->getClub() !== null && self::sameClub($member->getClub(), $club);
        }
        $seen = [];
        for ($current = $folder; $current !== null; $current = $current->getParent()) {
            $id = $current->getId();
            if ($id === null || isset($seen[$id]) || !self::sameClub($current->getClub(), $club)) {
                throw new \LogicException('Ungültige Ordnerhierarchie.');
            }
            $seen[$id] = true;
            $firstLevel = $current;
        }
        $date ??= new \DateTimeImmutable('today');
        $assignments = $this->em->getRepository(ClubFunctionAssignment::class)->findBy(['user' => $user]);
        foreach ($assignments as $assignment) {
            $function = $assignment->getClubFunction();
            if (!self::sameClub($function->getClub(), $club) || !$assignment->isEffectiveOn($date)) {
                continue;
            }
            $rule = $this->em->getRepository(MediaFolderPermission::class)->findOneBy([
                'clubFunction' => $function, 'folder' => $firstLevel,
            ]);
            if ($rule?->permission($action) === true) {
                return true;
            }
        }
        return false;
    }

    private static function sameClub(Club $first, Club $second): bool
    {
        return $first === $second || ($first->getId() !== null && $first->getId() === $second->getId());
    }
}
