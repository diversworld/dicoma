<?php

declare(strict_types=1);

namespace App\Tests\Membership;

use App\Identity\Domain\User;
use App\Membership\Domain\Club;
use App\Membership\Domain\ClubMembership;
use PHPUnit\Framework\TestCase;

final class ClubMembershipTest extends TestCase
{
    public function testMembershipHasExactlyOneClubAndRole(): void
    {
        $club = new Club('Tauchverein Nord', 'nord');
        $user = new User('test@example.org', 'already-hashed-password');
        $membership = new ClubMembership($club, $user, 'rental_manager');
        self::assertSame($club, $membership->getClub());
        self::assertSame($user, $membership->getUser());
        self::assertSame('rental_manager', $membership->getRole());
    }

    public function testRejectsUnknownRole(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ClubMembership(new Club('Nord', 'nord'), new User('a@example.org', 'hashed'), 'superadmin');
    }
}
