<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\FileAction;
use App\Entity\MediaFolder;
use App\Entity\MediaFolderPermission;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Enum\MembershipStatus;
use App\Security\FolderPermissions;
use App\Service\FunctionSuccession;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FunctionFolderPermissionsTest extends KernelTestCase
{
    public function testPoliciesInheritPerFunctionAndCombineAcrossAssignments(): void
    {
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        self::bootKernel(['environment' => 'test']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $club = (new Club())->setName('Testverein');
        $otherClub = (new Club())->setName('Anderer Verein');
        $person = (new Person())->setFirstname('Test')->setLastname('Person');
        $member = (new Member())->setClub($club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser('test')->setEmail('test@example.invalid')->setPassword('unused')->setMember($member);
        $first = new ClubFunction($club, 'Redaktion');
        $first->setRootPermission(FileAction::READ, true);
        $first->setRootPermission(FileAction::UPLOAD, true);
        $second = new ClubFunction($club, 'Archiv');
        $second->setValidity(new \DateTimeImmutable('2026-03-01'), new \DateTimeImmutable('2026-03-31'));
        $a = new ClubFunctionAssignment($first, $user);
        $b = new ClubFunctionAssignment($second, $user);
        $b->setValidity(new \DateTimeImmutable('2026-03-10'), new \DateTimeImmutable('2026-04-10'));
        $parent = new MediaFolder($club, 'A');
        $child = new MediaFolder($club, 'B', $parent);
        $foreign = new MediaFolder($otherClub, 'Fremd');
        $deny = new MediaFolderPermission($first, $parent);
        $deny->setPermission(FileAction::READ, false);
        $allow = new MediaFolderPermission($second, $child);
        $allow->setPermission(FileAction::READ, true);
        foreach ([$club, $otherClub, $user, $first, $second, $a, $b, $parent, $child, $foreign, $deny, $allow] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $em->clear();

        $user = $em->getRepository(User::class)->findOneBy(['user' => 'test']);
        $club = $em->getRepository(Club::class)->findOneBy(['name' => 'Testverein']);
        $parent = $em->getRepository(MediaFolder::class)->findOneBy(['name' => 'A']);
        $child = $em->getRepository(MediaFolder::class)->findOneBy(['name' => 'B']);
        $foreign = $em->getRepository(MediaFolder::class)->findOneBy(['name' => 'Fremd']);
        $access = new FolderPermissions($em);
        $before = new \DateTimeImmutable('2026-03-09');
        $firstDay = new \DateTimeImmutable('2026-03-10');
        $lastDay = new \DateTimeImmutable('2026-03-31');
        $after = new \DateTimeImmutable('2026-04-01');
        self::assertTrue($access->can($user, $club, null, FileAction::READ, $before));
        self::assertFalse($access->can($user, $club, $child, FileAction::READ, $before));
        self::assertTrue($access->can($user, $club, $child, FileAction::READ, $firstDay));
        self::assertTrue($access->can($user, $club, $child, FileAction::READ, $lastDay));
        self::assertFalse($access->can($user, $club, $child, FileAction::READ, $after));
        self::assertFalse($access->can($user, $club, $parent, FileAction::READ, $firstDay));
        self::assertTrue($access->can($user, $club, $child, FileAction::UPLOAD, $firstDay));
        self::assertFalse($access->can($user, $club, $child, FileAction::EDIT, $firstDay));
        self::assertFalse($access->can($user, $club, $foreign, FileAction::READ, $firstDay));
        $user->getMember()->setMembershipStatus(MembershipStatus::FORMER);
        self::assertFalse($access->can($user, $club, $child, FileAction::READ, $firstDay));
    }

    public function testInvalidDatesAndUnknownRolesAreRejected(): void
    {
        $function = new ClubFunction(new Club(), 'Training');
        try {
            $function->setValidity(new \DateTimeImmutable('2026-05-01'), new \DateTimeImmutable('2026-04-30'));
            self::fail('Inverted validity must be rejected.');
        } catch (\InvalidArgumentException) {
            self::assertNull($function->getStartsAt());
        }
        $this->expectException(\InvalidArgumentException::class);
        $function->setPermissions(['ROLE_UNKNOWN']);
    }

    public function testDisabledSuccessionDoesNotQueryTheNewTables(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('getConnection');
        $em->expects(self::never())->method('createQueryBuilder');
        $service = new FunctionSuccession($em, false);
        $user = new User();
        $member = (new Member())->setUser($user);
        self::assertSame([], $service->functions($user));
        self::assertSame([], $service->missingSuccessors($user));
        $service->revoke($member, 'test');
    }

    public function testSuccessorMustHoldTheSameFunctionAndBeCurrentlyEligible(): void
    {
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        self::bootKernel(['environment' => 'test']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('Testverein');
        $firstPerson = (new Person())->setFirstname('Erste')->setLastname('Person');
        $secondPerson = (new Person())->setFirstname('Zweite')->setLastname('Person');
        $holder = (new User())->setUser('holder')->setEmail('holder@example.invalid')->setPassword('unused')
            ->setMember((new Member())->setClub($club)->setPerson($firstPerson));
        $candidate = (new User())->setUser('candidate')->setEmail('candidate@example.invalid')->setPassword('unused')
            ->setMember((new Member())->setClub($club)->setPerson($secondPerson));
        $required = new ClubFunction($club, 'Erforderlich');
        $required->setSuccessorRequired(true);
        $required->setPermissions([User::ROLE_INSTRUCTOR]);
        $other = new ClubFunction($club, 'Andere');
        $other->setPermissions([User::ROLE_INSTRUCTOR]);
        $optional = new ClubFunction($club, 'Optional');
        $own = new ClubFunctionAssignment($required, $holder);
        $different = new ClubFunctionAssignment($other, $candidate);
        $optionalAssignment = new ClubFunctionAssignment($optional, $holder);
        foreach ([$club, $holder, $candidate, $required, $other, $optional, $own, $different, $optionalAssignment] as $entity) {
            $em->persist($entity);
        }
        $em->flush();
        $service = new FunctionSuccession($em, true);
        $day = new \DateTimeImmutable('2026-03-10');
        $em->getConnection()->beginTransaction();
        self::assertSame([$required], $service->missingSuccessors($holder, $day));
        $successor = new ClubFunctionAssignment($required, $candidate);
        $successor->setValidity(new \DateTimeImmutable('2026-03-11'), null);
        $em->persist($successor);
        $em->flush();
        self::assertSame([$required], $service->missingSuccessors($holder, $day));
        self::assertSame([], $service->missingSuccessors($holder, new \DateTimeImmutable('2026-03-11')));
        $candidate->getMember()->setMembershipStatus(MembershipStatus::FORMER);
        $em->flush();
        self::assertSame([$required], $service->missingSuccessors($holder, new \DateTimeImmutable('2026-03-11')));
        $candidate->getMember()->setMembershipStatus(MembershipStatus::ACTIVE);
        $candidate->getMember()->setArchivedAt(new \DateTimeImmutable('2026-03-01'));
        $em->flush();
        self::assertSame([$required], $service->missingSuccessors($holder, new \DateTimeImmutable('2026-03-11')));
        $candidate->getMember()->setArchivedAt(null);
        $successor->setValidity(null, new \DateTimeImmutable('2026-03-10'));
        $em->flush();
        self::assertSame([$required], $service->missingSuccessors($holder, new \DateTimeImmutable('2026-03-11')));
        $em->getConnection()->commit();
    }
}
