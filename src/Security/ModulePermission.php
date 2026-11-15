<?php

declare(strict_types=1);

namespace App\Security;

use App\Enum\PermissionAction;
use App\Enum\PermissionModule;

final class ModulePermission
{
    public static function key(PermissionModule $module, PermissionAction $action): string
    {
        return $module->value.':'.$action->value;
    }

    /** @return array<string, array<string, string>> */
    public static function choices(bool $super): array
    {
        $choices = [];
        foreach (PermissionModule::cases() as $module) {
            if (!$super && !$module->isDelegable()) {
                continue;
            }
            foreach (PermissionAction::cases() as $action) {
                $choices[$module->label()][$action->label()] = self::key($module, $action);
            }
        }
        return $choices;
    }

    /** @param list<string> $permissions */
    public static function validate(array $permissions): void
    {
        if (!array_is_list($permissions)) {
            throw new \InvalidArgumentException('Ungültige Modulberechtigungen.');
        }
        $seen = [];
        foreach ($permissions as $permission) {
            if (!is_string($permission)) {
                throw new \InvalidArgumentException('Ungültige Modulberechtigung.');
            }
            $parts = explode(':', $permission);
            if (count($parts) !== 2 || PermissionModule::tryFrom($parts[0]) === null
                || PermissionAction::tryFrom($parts[1]) === null || isset($seen[$permission])) {
                throw new \InvalidArgumentException('Ungültige oder doppelte Modulberechtigung.');
            }
            $seen[$permission] = true;
        }
    }

    /** @param list<string> $permissions */
    public static function isDelegable(array $permissions): bool
    {
        self::validate($permissions);
        foreach ($permissions as $permission) {
            [$module] = explode(':', $permission);
            if (!PermissionModule::from($module)->isDelegable()) {
                return false;
            }
        }
        return true;
    }

    public static function describe(string $permission): string
    {
        self::validate([$permission]);
        [$module, $action] = explode(':', $permission);
        return PermissionModule::from($module)->label().': '.PermissionAction::from($action)->label();
    }
}
