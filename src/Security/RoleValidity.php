<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

final class RoleValidity
{
    /** @param list<string> $roles
     *  @return array<string, array{startsAt: ?string, endsAt: ?string}>
     */
    public static function formData(User $user, array $roles): array
    {
        $stored = $user->getRoleValidity();
        $data = [];
        foreach ($roles as $role) {
            $data[$role] = $stored[$role] ?? ['startsAt' => null, 'endsAt' => null];
        }
        return $data;
    }

    /**
     * @param list<string> $managedRoles
     * @param list<string> $selectedRoles
     * @param array<string, array{startsAt: ?string, endsAt: ?string}> $windows
     */
    public static function replace(User $user, array $managedRoles, array $selectedRoles, array $windows): void
    {
        $validity = $user->getRoleValidity();
        foreach ($managedRoles as $role) {
            unset($validity[$role]);
            $start = $windows[$role]['startsAt'] === '' ? null : $windows[$role]['startsAt'];
            $end = $windows[$role]['endsAt'] === '' ? null : $windows[$role]['endsAt'];
            if (in_array($role, $selectedRoles, true)
                && ($start !== null || $end !== null)) {
                $validity[$role] = ['startsAt' => $start, 'endsAt' => $end];
            }
        }
        $user->setRoleValidity($validity);
    }
}
