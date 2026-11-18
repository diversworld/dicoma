<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\MigrateFunctionRolesCommand;
use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\FileAction;
use App\Entity\MediaFolder;
use App\Entity\MediaFolderPermission;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class FunctionRoleMigrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        $enabled = str_starts_with($this->name(), 'testDryRun') ? '0' : '1';
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1', 'CLUB_FUNCTION_MEMBERSHIP_ENABLED' => $enabled] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }
        self::bootKernel(['environment' => 'test']);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Migrationsverein');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testRolesAndWindowsAreTransferredWithoutLosingEffectiveAccess(): void
    {
        $user = $this->account('editor', [User::ROLE_INSTRUCTOR, User::ROLE_PRESS_OFFICER]);
        $user->setRoleValidity([
            User::ROLE_INSTRUCTOR => ['startsAt' => '2020-01-01', 'endsAt' => '2999-12-31'],
            User::ROLE_PRESS_OFFICER => ['startsAt' => '2999-01-01', 'endsAt' => null],
        ]);
        $emergency = $this->account('emergency', [User::ROLE_SUPER_ADMIN]);
        $folder = new MediaFolder($this->club, 'Intern');
        $this->em->persist($folder);
        $this->em->flush();
        $userId = $user->getId();
        $emergencyId = $emergency->getId();
        self::assertNotContains(User::ROLE_INSTRUCTOR, $user->getRoles());
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true]), $tester->getDisplay());
        $this->em->clear();
        $user = $this->em->find(User::class, $userId);
        self::assertSame([], $user->getStoredRoles());
        self::assertEqualsCanonicalizing([User::ROLE_INSTRUCTOR, User::ROLE_MEMBER, User::ROLE_USER], $user->getRoles());
        $function = $this->em->getRepository(ClubFunction::class)->findOneBy(['name' => 'Pressewart']);
        $rule = $this->em->getRepository(MediaFolderPermission::class)->findOneBy(['clubFunction' => $function, 'folder' => $folder->getId()]);
        self::assertNotNull($rule);
        self::assertTrue($rule->permission(FileAction::READ));
        self::assertTrue($rule->permission(FileAction::DELETE));
        self::assertNull($function->rootPermission(FileAction::READ));
        self::assertSame([
            'website:read', 'website:create', 'website:edit', 'website:delete',
            'files:read', 'files:create', 'files:edit', 'files:delete',
        ], $function->getModulePermissions());
        $assignment = $this->em->getRepository(ClubFunctionAssignment::class)->findOneBy(['user' => $user, 'clubFunction' => $function]);
        self::assertSame('2999-01-01', $assignment->getStartsAt()->format('Y-m-d'));
        $emergency = $this->em->find(User::class, $emergencyId);
        self::assertSame([User::ROLE_SUPER_ADMIN], $emergency->getStoredRoles());
        $fileFunction = $this->em->getRepository(ClubFunction::class)->findOneBy(['name' => 'Dateiverwaltung (Superadministrator)']);
        self::assertNotNull($this->em->getRepository(ClubFunctionAssignment::class)->findOneBy(['user' => $emergency, 'clubFunction' => $fileFunction]));
        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true]));
        self::assertSame(3, $this->em->getRepository(ClubFunctionAssignment::class)->count([]));
    }

    public function testAmbiguousWindowsAndUnlinkedFunctionsAbortWithoutChanges(): void
    {
        $user = $this->account('bad-window', [User::ROLE_INSTRUCTOR]);
        $unlinked = (new User())->setUser('unlinked')->setEmail('unlinked@example.invalid')->setPassword('unused')->setRoles([User::ROLE_TREASURER]);
        $this->em->persist($unlinked);
        $this->em->flush();
        $id = $user->getId();
        $this->em->getConnection()->executeStatement('UPDATE `user` SET role_validity = ? WHERE id = ?', [
            '{"ROLE_INSTRUCTOR":{"startsAt":null,"startsAt":null,"endsAt":null}}', $id,
        ]);
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
        self::assertStringContainsString('Doppelte', $tester->getDisplay());
        self::assertSame(0, $this->em->getRepository(ClubFunction::class)->count([]));
        $this->em->getConnection()->executeStatement('UPDATE `user` SET role_validity = NULL WHERE id = ?', [$id]);
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
        self::assertStringContainsString('keine aktive Mitgliedschaft', $tester->getDisplay());
        self::assertSame(0, $this->em->getRepository(ClubFunction::class)->count([]));
        self::assertSame('["ROLE_INSTRUCTOR"]', $this->em->getConnection()->fetchOne('SELECT roles FROM `user` WHERE id = ?', [$id]));
    }

    public function testConflictingExistingFunctionRollsBackEarlierWrites(): void
    {
        $user = $this->account('collision', [User::ROLE_INSTRUCTOR, User::ROLE_PRESS_OFFICER]);
        $id = $user->getId();
        $existing = new ClubFunction($this->club, 'Pressewart');
        $this->em->persist($existing);
        $this->em->flush();
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
        self::assertStringContainsString('widerspricht', $tester->getDisplay());
        self::assertNull($this->em->getRepository(ClubFunction::class)->findOneBy(['name' => 'Ausbilder / Trainer']));
        self::assertSame(0, $this->em->getRepository(ClubFunctionAssignment::class)->count([]));
        self::assertSame('["ROLE_INSTRUCTOR","ROLE_PRESS_OFFICER"]', $this->em->getConnection()->fetchOne('SELECT roles FROM `user` WHERE id = ?', [$id]));
    }

    public function testDryRunWithDisabledModelRollsBackRolesFunctionsAndFolderRules(): void
    {
        $user = $this->account('dry-run', [User::ROLE_INSTRUCTOR, User::ROLE_PRESS_OFFICER]);
        $user->setRoleValidity([User::ROLE_INSTRUCTOR => ['startsAt' => '2020-01-01', 'endsAt' => null]]);
        $this->em->persist(new MediaFolder($this->club, 'Ablage'));
        $this->em->flush();
        $userId = $user->getId();
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true, '--dry-run' => true]));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]), $tester->getDisplay());
        self::assertStringContainsString('2 direkte Funktionsrollenzuordnungen geprüft', $tester->getDisplay());
        $user = $this->em->find(User::class, $userId);
        self::assertSame([User::ROLE_INSTRUCTOR, User::ROLE_PRESS_OFFICER], $user->getStoredRoles());
        self::assertSame(['startsAt' => '2020-01-01', 'endsAt' => null], $user->getRoleValidity()[User::ROLE_INSTRUCTOR]);
        self::assertContains(User::ROLE_INSTRUCTOR, $user->getRoles());
        self::assertSame(0, $this->em->getRepository(ClubFunction::class)->count([]));
        self::assertSame(0, $this->em->getRepository(ClubFunctionAssignment::class)->count([]));
        self::assertSame(0, $this->em->getRepository(MediaFolderPermission::class)->count([]));
    }

    public function testDryRunConflictRollsBackAllChanges(): void
    {
        $user = $this->account('dry-run-conflict', [User::ROLE_INSTRUCTOR, User::ROLE_PRESS_OFFICER]);
        $id = $user->getId();
        $existing = new ClubFunction($this->club, 'Pressewart');
        $this->em->persist($existing);
        $this->em->flush();
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));
        self::assertSame(Command::FAILURE, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('widerspricht', $tester->getDisplay());
        self::assertSame(1, $this->em->getRepository(ClubFunction::class)->count([]));
        self::assertNull($this->em->getRepository(ClubFunction::class)->findOneBy(['name' => 'Ausbilder / Trainer']));
        self::assertSame(0, $this->em->getRepository(ClubFunctionAssignment::class)->count([]));
        self::assertSame([User::ROLE_INSTRUCTOR, User::ROLE_PRESS_OFFICER], $this->em->find(User::class, $id)->getStoredRoles());
    }

    public function testMigrationRejectsAnExistingTransaction(): void
    {
        $user = $this->account('nested', [User::ROLE_INSTRUCTOR]);
        $id = $user->getId();
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));
            self::assertSame(Command::FAILURE, $tester->execute(['--dry-run' => true]));
            self::assertStringContainsString('eigene, nicht bereits aktive Transaktion', $tester->getDisplay());
            self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true]));
            self::assertStringContainsString('eigene, nicht bereits aktive Transaktion', $tester->getDisplay());
            self::assertSame('["ROLE_INSTRUCTOR"]', $connection->fetchOne('SELECT roles FROM `user` WHERE id = ?', [$id]));
            self::assertSame(0, $this->em->getRepository(ClubFunction::class)->count([]));
        } finally {
            $connection->rollBack();
        }
    }

    public function testDryRunTrainingMergePreservesExistingDefinitionAndRoles(): void
    {
        $user = $this->account('training-dry-run', [User::ROLE_TRAINING_MANAGER]);
        $function = new ClubFunction($this->club, 'Ausbildungsleiter');
        $function->setModulePermissions(['training:read', 'training:create', 'training:edit', 'training:delete']);
        $this->em->persist($function);
        $this->em->flush();
        $userId = $user->getId();
        $functionId = $function->getId();
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));

        self::assertSame(Command::FAILURE, $tester->execute(['--dry-run' => true]));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true, '--merge-training-manager' => true]), $tester->getDisplay());
        $function = $this->em->find(ClubFunction::class, $functionId);
        self::assertSame([], $function->getPermissions());
        self::assertFalse($function->isSuccessorRequired());
        self::assertSame(0, $this->em->getRepository(ClubFunctionAssignment::class)->count([]));
        self::assertSame([User::ROLE_TRAINING_MANAGER], $this->em->find(User::class, $userId)->getStoredRoles());
    }

    public function testTrainingMergeTransfersMultipleHoldersAndPreservesModulePermissions(): void
    {
        $first = $this->account('training-one', [User::ROLE_TRAINING_MANAGER]);
        $first->setRoleValidity([User::ROLE_TRAINING_MANAGER => ['startsAt' => '2020-01-01', 'endsAt' => '2999-12-31']]);
        $second = $this->account('training-two', [User::ROLE_TRAINING_MANAGER]);
        $function = new ClubFunction($this->club, 'Ausbildungsleiter');
        $function->setModulePermissions(['training:read', 'training:create', 'training:edit', 'training:delete']);
        $this->em->persist($function);
        $this->em->flush();
        $functionId = $function->getId();
        $firstId = $first->getId();
        $secondId = $second->getId();
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));

        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--merge-training-manager' => true]), $tester->getDisplay());
        $this->em->clear();
        $function = $this->em->find(ClubFunction::class, $functionId);
        self::assertSame([User::ROLE_TRAINING_MANAGER], $function->getPermissions());
        self::assertSame(['training:read', 'training:create', 'training:edit', 'training:delete'], $function->getModulePermissions());
        self::assertTrue($function->isSuccessorRequired());
        self::assertSame(2, $this->em->getRepository(ClubFunctionAssignment::class)->count(['clubFunction' => $function]));
        $assignment = $this->em->getRepository(ClubFunctionAssignment::class)->findOneBy(['clubFunction' => $function, 'user' => $firstId]);
        self::assertSame('2020-01-01', $assignment->getStartsAt()->format('Y-m-d'));
        self::assertSame('2999-12-31', $assignment->getEndsAt()->format('Y-m-d'));
        self::assertSame([], $this->em->find(User::class, $firstId)->getStoredRoles());
        self::assertSame([], $this->em->find(User::class, $secondId)->getStoredRoles());
        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true, '--merge-training-manager' => true]));
    }

    public function testTrainingMergeRejectsExistingHoldersOrChangedPermissions(): void
    {
        $this->account('old-training', [User::ROLE_TRAINING_MANAGER]);
        $other = $this->account('existing-holder', []);
        $function = new ClubFunction($this->club, 'Ausbildungsleiter');
        $function->setModulePermissions(['training:read', 'training:create', 'training:edit', 'training:delete']);
        $assignment = new ClubFunctionAssignment($function, $other);
        $this->em->persist($function);
        $this->em->persist($assignment);
        $this->em->flush();
        $functionId = $function->getId();
        $assignmentId = $assignment->getId();
        $tester = new CommandTester(self::getContainer()->get(MigrateFunctionRolesCommand::class));
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true, '--merge-training-manager' => true]));
        self::assertStringContainsString('widerspricht der bestätigten Übernahme', $tester->getDisplay());
        $assignment = $this->em->find(ClubFunctionAssignment::class, $assignmentId);
        $this->em->remove($assignment);
        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['training:read']);
        $this->em->flush();
        self::assertSame(Command::FAILURE, $tester->execute(['--confirm' => true, '--merge-training-manager' => true]));
        self::assertStringContainsString('widerspricht der bestätigten Übernahme', $tester->getDisplay());
        self::assertSame([], $this->em->find(ClubFunction::class, $functionId)->getPermissions());
    }

    private function account(string $name, array $roles): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Test');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setMember($member)->setRoles($roles);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
}
