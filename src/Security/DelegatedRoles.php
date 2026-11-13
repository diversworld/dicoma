<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

final class DelegatedRoles
{
    public const ALLOWED = [
        User::ROLE_INSTRUCTOR,
        User::ROLE_TRAINING_MANAGER,
        User::ROLE_COURSE_ADMIN,
        User::ROLE_EQUIPMENT_MANAGER,
        User::ROLE_EQUIPMENT_CASH,
        User::ROLE_COMPRESSOR_OPERATOR,
        User::ROLE_BAR_MANAGER,
        User::ROLE_PRESS_OFFICER,
        User::ROLE_TREASURER,
    ];

    /** @return array<string, string> */
    public static function choices(): array
    {
        return array_filter(User::getAvailableRoles(), static fn (string $role): bool => in_array($role, self::ALLOWED, true));
    }

    public static function isDelegableTarget(User $user): bool
    {
        return array_diff($user->getStoredRoles(), [...self::ALLOWED, User::ROLE_USER, User::ROLE_MEMBER]) === [];
    }

    /** @param list<string> $roles */
    public static function replace(User $user, array $roles): void
    {
        if (!self::isDelegableTarget($user) || array_diff($roles, self::ALLOWED) !== []) {
            throw new \LogicException('Unzulässige delegierte Rollenänderung.');
        }
        $preserved = array_diff($user->getStoredRoles(), self::ALLOWED);
        $user->setRoles([...$preserved, ...$roles]);
    }

    public static function fingerprint(User $user): string
    {
        $roles = $user->getStoredRoles();
        sort($roles);
        $validity = $user->getRoleValidity();
        ksort($validity);
        return hash('sha256', json_encode([$roles, $validity], JSON_THROW_ON_ERROR));
    }
}
