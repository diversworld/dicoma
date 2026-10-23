<?php

declare(strict_types=1);

namespace App\Tests\Membership;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class ClubMembershipTest extends TestCase
{
    public function testMemberCanBeAssignedToExactlyOneClub(): void
    {
        $clubA = (new Club())->setName('Tauchverein Nord');
        $clubB = (new Club())->setName('Tauchverein Süd');

        $member = new Member();

        $member->setClub($clubA);

        self::assertSame($clubA, $member->getClub());

        $member->setClub($clubB);

        self::assertSame($clubB, $member->getClub());
        self::assertNotSame($clubA, $member->getClub());
    }

    public function testMemberCanBeLinkedToUser(): void
    {
        $member = new Member();
        $user = new User();

        $member->setUser($user);

        self::assertSame($user, $member->getUser());
    }
}