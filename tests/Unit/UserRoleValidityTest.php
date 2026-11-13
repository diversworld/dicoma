<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserRoleValidityTest extends TestCase
{
    public function testRolePeriodsAreIndependentAndIncludeBothBoundaryDays(): void
    {
        $today = new \DateTimeImmutable('today');
        $user = (new User())->setRoles([User::ROLE_INSTRUCTOR, User::ROLE_ADMIN, User::ROLE_BOARD]);
        $user->setRoleValidity([
            User::ROLE_INSTRUCTOR => ['startsAt' => $today->format('Y-m-d'), 'endsAt' => $today->format('Y-m-d')],
            User::ROLE_ADMIN => ['startsAt' => $today->modify('+1 day')->format('Y-m-d'), 'endsAt' => null],
            User::ROLE_BOARD => ['startsAt' => null, 'endsAt' => $today->modify('-1 day')->format('Y-m-d')],
        ]);
        self::assertContains(User::ROLE_INSTRUCTOR, $user->getRoles());
        self::assertNotContains(User::ROLE_ADMIN, $user->getRoles());
        self::assertNotContains(User::ROLE_BOARD, $user->getRoles());
        self::assertContains(User::ROLE_USER, $user->getRoles());
        self::assertCount(3, $user->getStoredRoles());

        $user->setRoles([User::ROLE_INSTRUCTOR, User::ROLE_BOARD]);
        self::assertArrayNotHasKey(User::ROLE_ADMIN, $user->getRoleValidity());
        $user->removeRole(User::ROLE_BOARD);
        $user->addRole(User::ROLE_BOARD);
        self::assertContains(User::ROLE_BOARD, $user->getRoles());
    }

    public function testInvalidRolePeriodsCannotBePersisted(): void
    {
        $user = (new User())->setRoles([User::ROLE_ADMIN]);
        $this->expectException(\InvalidArgumentException::class);
        $user->setRoleValidity([User::ROLE_ADMIN => ['startsAt' => '2027-02-31', 'endsAt' => null]]);
    }

    #[DataProvider('functionRoles')]
    public function testEachGlobalFunctionCanBeScheduled(string $role): void
    {
        $today = new \DateTimeImmutable('today');
        $user = (new User())->setRoles([$role]);
        self::assertContains($role, $user->getRoles());
        $user->setRoleValidity([$role => ['startsAt' => $today->modify('+1 day')->format('Y-m-d'), 'endsAt' => null]]);
        self::assertNotContains($role, $user->getRoles());
        $user->setRoleValidity([$role => ['startsAt' => null, 'endsAt' => $today->format('Y-m-d')]]);
        self::assertContains($role, $user->getRoles());
        $user->setRoleValidity([$role => ['startsAt' => null, 'endsAt' => $today->modify('-1 day')->format('Y-m-d')]]);
        self::assertNotContains($role, $user->getRoles());
        self::assertContains($role, $user->getStoredRoles());
    }

    public static function functionRoles(): iterable
    {
        foreach (User::getAvailableRoles() as $label => $role) {
            if (!in_array($role, [User::ROLE_USER, User::ROLE_MEMBER], true)) {
                yield $label => [$role];
            }
        }
    }
}
