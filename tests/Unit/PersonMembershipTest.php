<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Enum\MembershipStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersonMembershipTest extends TestCase
{
    public function testGuestAccountDoesNotRequireMembership(): void
    {
        $person = (new Person())->setFirstname('Guest')->setLastname('Diver');
        $user = (new User())->setPerson($person)->setRoles([User::ROLE_MEMBER]);
        self::assertNull($user->getMember());
        self::assertSame($person, $user->getPerson());
        self::assertSame([User::ROLE_USER], $user->getRoles());
    }

    public function testMembershipsShareIdentityButNotClubData(): void
    {
        $person = (new Person())->setFirstname('Shared')->setLastname('Diver');
        $a = (new Member())->setPerson($person)->setClub(new Club())->setMemberNumber('A-1');
        $b = (new Member())->setPerson($person)->setClub(new Club())->setMemberNumber('B-2');
        $a->setLastname('Updated');
        self::assertSame('Updated', $b->getLastname());
        self::assertSame('B-2', $b->getMemberNumber());
        self::assertNotSame($a->getClub(), $b->getClub());
    }

    public function testBothAssociationDirectionsRemainConsistentWhenReassignedOrDetached(): void
    {
        $a = new Member();
        $b = new Member();
        $first = new User();
        $second = new User();
        $a->setUser($first);
        self::assertSame($a, $first->getMember());
        self::assertSame($a->getPerson(), $first->getPerson());
        $first->setMember($b);
        self::assertNull($a->getUser());
        self::assertSame($first, $b->getUser());
        $b->setUser($second);
        self::assertNull($first->getMember());
        self::assertSame($b, $second->getMember());
        $second->setMember(null);
        self::assertNull($b->getUser());
        self::assertSame($b->getPerson(), $second->getPerson());
    }

    public function testAccountCannotPointToAnotherPersonThanItsMembership(): void
    {
        $user = (new User())->setMember(new Member());
        $this->expectException(\InvalidArgumentException::class);
        $user->setPerson(new Person());
    }

    /** @return iterable<string, array{MembershipStatus, bool}> */
    public static function membershipStatuses(): iterable
    {
        foreach (MembershipStatus::cases() as $status) {
            yield $status->value => [$status, $status === MembershipStatus::ACTIVE];
        }
    }

    #[DataProvider('membershipStatuses')]
    public function testMemberRoleRequiresActiveClubMembership(MembershipStatus $status, bool $granted): void
    {
        $member = (new Member())->setClub(new Club())->setMembershipStatus($status);
        $user = (new User())->setRoles([User::ROLE_MEMBER])->setMember($member);
        self::assertSame($granted, in_array(User::ROLE_MEMBER, $user->getRoles(), true));
        $member->setLeftAt(new \DateTime('yesterday'));
        self::assertNotContains(User::ROLE_MEMBER, $user->getRoles());
        $member->setLeftAt(null)->setClub(null);
        self::assertNotContains(User::ROLE_MEMBER, $user->getRoles());
    }

    public function testExplicitFunctionalRolesAreNotRemoved(): void
    {
        $user = (new User())->setRoles([User::ROLE_INSTRUCTOR, User::ROLE_ADMIN, User::ROLE_MEMBER]);
        self::assertContains(User::ROLE_ADMIN, $user->getRoles());
        self::assertContains(User::ROLE_INSTRUCTOR, $user->getRoles());
        self::assertNotContains(User::ROLE_MEMBER, $user->getRoles());
        self::assertContains(User::ROLE_MEMBER, $user->getStoredRoles());
    }
}
