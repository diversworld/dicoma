<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\CheckFunctionMigrationCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckFunctionMigrationCommandTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE `user` (id INTEGER PRIMARY KEY, roles TEXT NOT NULL, role_validity TEXT DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE member (id INTEGER PRIMARY KEY, user_id INTEGER, club_id INTEGER, membership_status TEXT, left_at TEXT)');
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }

    public function testValidDataAreCountedWithoutChangingThem(): void
    {
        $this->connection->insert('user', ['id' => 1, 'roles' => '["ROLE_USER","ROLE_INSTRUCTOR","ROLE_SUPER_ADMIN"]', 'role_validity' => '{"ROLE_INSTRUCTOR":{"startsAt":"2025-01-01","endsAt":"2025-12-31"}}']);
        $this->connection->insert('user', ['id' => 2, 'roles' => '["ROLE_BOARD"]', 'role_validity' => null]);
        $this->connection->insert('user', ['id' => 3, 'roles' => '[]', 'role_validity' => '[]']);
        $this->connection->insert('member', ['id' => 1, 'user_id' => 1, 'club_id' => 1, 'membership_status' => 'active', 'left_at' => null]);
        $this->connection->insert('member', ['id' => 2, 'user_id' => 2, 'club_id' => null, 'membership_status' => 'former', 'left_at' => '2025-01-01']);
        $before = $this->connection->fetchAllAssociative('SELECT * FROM `user` ORDER BY id');
        $tester = new CommandTester(new CheckFunctionMigrationCommand($this->connection));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Konten: 3', $tester->getDisplay());
        self::assertStringContainsString('Konten ohne Mitgliedschaft: 1', $tester->getDisplay());
        self::assertStringContainsString('Konten mit nicht aktiver Mitgliedschaft: 1', $tester->getDisplay());
        self::assertStringContainsString('Konten ohne Vereinszuordnung: 1', $tester->getDisplay());
        self::assertStringContainsString('Konten mit Superadmin-Notfallrolle: 1', $tester->getDisplay());
        self::assertStringContainsString('Gespeicherte Funktionsrollenzuordnungen: 2', $tester->getDisplay());
        self::assertStringContainsString('Gespeicherte Gültigkeitszeiträume: 1', $tester->getDisplay());
        self::assertStringContainsString('Doppelte JSON-Objektschlüssel und parallele Änderungen werden nicht geprüft.', $tester->getDisplay());
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM `user` ORDER BY id'));
    }

    public function testInvalidLegacyDataBlockMigrationWithoutExposingRoles(): void
    {
        $rows = [
            [1, '{', null],
            [2, '{}', null],
            [3, '["ROLE_UNKNOWN","ROLE_INSTRUCTOR","ROLE_INSTRUCTOR",42]', null],
            [4, '["ROLE_INSTRUCTOR"]', '{"ROLE_INSTRUCTOR":{"startsAt":"2025-02-30","endsAt":null}}'],
            [5, '[]', '{"ROLE_INSTRUCTOR":{"startsAt":null,"endsAt":null}}'],
            [6, '["ROLE_INSTRUCTOR"]', '{"ROLE_INSTRUCTOR":{"startsAt":null,"endsAt":null,"other":"x"}}'],
            [7, '["ROLE_INSTRUCTOR"]', '[1]'],
            [8, '["ROLE_INSTRUCTOR"]', '{'],
            [9, '[]', null],
        ];
        foreach ($rows as [$id, $roles, $validity]) {
            $this->connection->insert('user', ['id' => $id, 'roles' => $roles, 'role_validity' => $validity]);
        }
        $this->connection->insert('member', ['id' => 1, 'user_id' => 9, 'club_id' => 1, 'membership_status' => 'active', 'left_at' => null]);
        $this->connection->insert('member', ['id' => 2, 'user_id' => 9, 'club_id' => 1, 'membership_status' => 'active', 'left_at' => null]);
        $before = $this->connection->fetchAllAssociative('SELECT * FROM `user` ORDER BY id');
        $tester = new CommandTester(new CheckFunctionMigrationCommand($this->connection));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        for ($id = 1; $id <= 9; ++$id) {
            self::assertStringContainsString('Konto-ID '.$id.':', $tester->getDisplay());
        }
        self::assertStringNotContainsString('ROLE_UNKNOWN', $tester->getDisplay());
        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT * FROM `user` ORDER BY id'));
    }
}
