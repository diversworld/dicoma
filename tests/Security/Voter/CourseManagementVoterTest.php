<?php

declare(strict_types=1);

namespace App\Tests\Security\Voter;

use App\Entity\Club;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\User;
use App\Security\Voter\CourseManagementVoter;
use App\Service\AdminClubContext;
use App\Service\PublicWebsite;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

final class CourseManagementVoterTest extends TestCase
{
    public function testAdminWithoutMemberCannotManageCourse(): void
    {
        $user = new User();
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user, new Courses(), true, false));
    }

    public function testAdminWithSameClubMayManageCourse(): void
    {
        $club = new Club();
        // Nicht persistierte Clubs haben keine ID: fail closed.
        $user = new User();
        $member = new Member();
        $member->setClub($club);
        $user->setMember($member);
        $course = new Courses();
        $course->setClub($club);
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote($user, $course, true, false));
    }

    public function testSuperAdminMayManageCourseWithoutClub(): void
    {
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->vote(new User(), new Courses(), true, true));
    }

    public function testNonAdminIsDenied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, $this->vote(new User(), new Courses(), false, false));
    }

    private function vote(User $user, Courses $course, bool $admin, bool $super): int
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (string $role): bool => match ($role) {
                'ROLE_SUPER_ADMIN' => $super,
                'ROLE_ADMIN' => $admin,
                default => false,
            }
        );
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $club = new Club();
        (new \ReflectionProperty(Club::class, 'id'))->setValue($club, 1);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($club);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $security->method('isGranted')->willReturnCallback(static fn (string $role): bool => $role === 'ROLE_SUPER_ADMIN' && $super);
        $context = new AdminClubContext($security, new RequestStack(), new SingleClubMode(new PublicWebsite($em, $security, 1), $em));
        return (new CourseManagementVoter($checker, $context))->vote($token, $course, [CourseManagementVoter::MANAGE]);
    }
}
