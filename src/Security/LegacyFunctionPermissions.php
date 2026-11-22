<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

final class LegacyFunctionPermissions
{
    public function __construct(private readonly RoleHierarchyInterface $hierarchy) {}

    /**
     * @param list<string> $roles
     * @return list<string>
     */
    public function forRoles(array $roles): array
    {
        $permissions = [];
        foreach ($this->hierarchy->getReachableRoleNames($roles) as $role) {
            foreach ($this->modulesForRole($role) as [$module, $action]) {
                $permissions[ModulePermission::key($module, $action)] = true;
            }
        }

        return array_keys($permissions);
    }

    /**
     * @return iterable<array{0: PermissionModule, 1: PermissionAction}>
     */
    private function modulesForRole(string $role): iterable
    {
        $all = PermissionAction::cases();
        $modules = match ($role) {
            User::ROLE_INSTRUCTOR => [PermissionModule::TRAINING->value => [PermissionAction::READ]],
            User::ROLE_TRAINING_MANAGER, User::ROLE_COURSE_ADMIN => [PermissionModule::TRAINING->value => $all],
            User::ROLE_EQUIPMENT_MANAGER => [PermissionModule::EQUIPMENT->value => $all],
            User::ROLE_EQUIPMENT_CASH => [PermissionModule::EQUIPMENT_CASH->value => $all],
            User::ROLE_COMPRESSOR_OPERATOR => [
                PermissionModule::EQUIPMENT->value => [
                    PermissionAction::READ,
                    PermissionAction::CREATE,
                    PermissionAction::EDIT,
                ],
            ],
            User::ROLE_BAR_MANAGER => [
                PermissionModule::BAR_DUTY->value => $all,
                PermissionModule::BAR_CASH->value => $all,
            ],
            User::ROLE_PRESS_OFFICER => [
                PermissionModule::WEBSITE->value => $all,
                PermissionModule::FILES->value => $all,
            ],
            User::ROLE_TREASURER => [PermissionModule::FINANCE->value => $all],
            User::ROLE_BOARD => [
                PermissionModule::MEMBERSHIP->value => $all,
                PermissionModule::GOVERNANCE->value => $all,
                PermissionModule::FINANCE->value => [PermissionAction::READ],
            ],
            User::ROLE_SECRETARY => [
                PermissionModule::GOVERNANCE->value => $all,
                PermissionModule::FILES->value => [PermissionAction::READ, PermissionAction::CREATE, PermissionAction::EDIT],
            ],
            User::ROLE_ADMIN => [
                PermissionModule::MEMBERSHIP->value => $all,
                PermissionModule::SPORT->value => $all,
                PermissionModule::SYSTEM->value => $all,
                PermissionModule::GOVERNANCE->value => $all,
                PermissionModule::FINANCE->value => $all,
                PermissionModule::ACCOUNTING->value => $all,
                PermissionModule::AUDIT->value => $all,
            ],
            default => [],
        };

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                yield [PermissionModule::from($module), $action];
            }
        }
    }
}
