<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\PrepareSingleClubCommand;
use App\Command\CheckSingleClubCommand;
use App\Entity\Club;
use App\Service\PublicWebsite;
use App\Service\SingleClubReassignment;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SingleClubReassignmentTest extends TestCase
{
    private Connection $connection;
    private SingleClubReassignment $reassignment;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE club (id INTEGER PRIMARY KEY, active INTEGER NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE member (id INTEGER PRIMARY KEY, club_id INTEGER, FOREIGN KEY (club_id) REFERENCES club(id))');
        $this->connection->executeStatement('CREATE TABLE tournament (id INTEGER PRIMARY KEY, club_id INTEGER NOT NULL, version INTEGER NOT NULL, FOREIGN KEY (club_id) REFERENCES club(id))');
        $this->connection->executeStatement('CREATE TABLE tournament_entry (id INTEGER PRIMARY KEY, tournament_id INTEGER NOT NULL, FOREIGN KEY (tournament_id) REFERENCES tournament(id))');
        $this->connection->executeStatement('INSERT INTO club (id, active) VALUES (1, 1), (2, 1)');
        $this->connection->executeStatement('INSERT INTO tournament (id, club_id, version) VALUES (7, 2, 3)');
        $this->connection->executeStatement('INSERT INTO tournament_entry (id, tournament_id) VALUES (1, 7), (2, 7)');
        $this->reassignment = new SingleClubReassignment($this->connection);
    }

    public function testDryRunDoesNotChangeTheTournament(): void
    {
        self::assertSame([], $this->reassignment->run(1, 2, 7, false));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT club_id FROM tournament WHERE id = 7'));
        self::assertNotEmpty($this->reassignment->blockers(1));
    }

    public function testApplyPreservesTournamentAndEntriesAndIncrementsVersion(): void
    {
        self::assertSame([], $this->reassignment->run(1, 2, 7, true));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT club_id FROM tournament WHERE id = 7'));
        self::assertSame(4, (int) $this->connection->fetchOne('SELECT version FROM tournament WHERE id = 7'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM tournament_entry WHERE tournament_id = 7'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM club'));
        self::assertSame([], $this->reassignment->blockers(1));
    }

    public function testOtherOrUnassignedDataBlocksTheTransfer(): void
    {
        $this->connection->executeStatement('INSERT INTO member (id, club_id) VALUES (1, 2), (2, NULL)');
        $blockers = $this->reassignment->run(1, 2, 7, true);
        self::assertCount(2, $blockers);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT club_id FROM tournament WHERE id = 7'));
    }

    public function testUnconstrainedClubColumnAndNamedForeignKeyBlockTransfer(): void
    {
        $this->connection->executeStatement('CREATE TABLE legacy_record (id INTEGER PRIMARY KEY, club_id INTEGER)');
        $this->connection->executeStatement('CREATE TABLE custom_record (id INTEGER PRIMARY KEY, host_id INTEGER, FOREIGN KEY (host_id) REFERENCES club(id))');
        $this->connection->executeStatement('INSERT INTO legacy_record (id, club_id) VALUES (1, 2)');
        $this->connection->executeStatement('INSERT INTO custom_record (id, host_id) VALUES (1, 2)');
        $blockers = $this->reassignment->run(1, 2, 7, true);
        self::assertCount(2, $blockers);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT club_id FROM tournament WHERE id = 7'));
    }

    public function testInactiveTargetFailsWithoutChangingTheTournament(): void
    {
        $this->connection->executeStatement('UPDATE club SET active = 0 WHERE id = 1');
        try {
            $this->reassignment->run(1, 2, 7, true);
            self::fail('An inactive target must be rejected.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('Aktiver Zielverein', $exception->getMessage());
        }
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT club_id FROM tournament WHERE id = 7'));
        self::assertSame(3, (int) $this->connection->fetchOne('SELECT version FROM tournament WHERE id = 7'));
    }

    public function testApplyRequiresAnExplicitMaintenanceConfirmation(): void
    {
        $website = $this->website();
        $tester = new CommandTester(new PrepareSingleClubCommand($website, $this->reassignment, new NullLogger()));
        self::assertSame(Command::INVALID, $tester->execute(['--source-club' => '2', '--tournament' => '7', '--apply' => true]));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT club_id FROM tournament WHERE id = 7'));
    }

    public function testCheckAndExplicitlyConfirmedApply(): void
    {
        $check = new CommandTester(new CheckSingleClubCommand($this->website(), $this->reassignment));
        self::assertSame(Command::FAILURE, $check->execute([]));
        $apply = new CommandTester(new PrepareSingleClubCommand($this->website(), $this->reassignment, new NullLogger()));
        self::assertSame(Command::SUCCESS, $apply->execute([
            '--source-club' => '2', '--tournament' => '7', '--apply' => true, '--confirm-maintenance' => true,
        ]));
        self::assertSame(Command::SUCCESS, $check->execute([]));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT club_id FROM tournament WHERE id = 7'));
    }

    private function website(): PublicWebsite
    {
        $club = new Club();
        $id = new \ReflectionProperty(Club::class, 'id');
        $id->setValue($club, 1);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('find')->willReturn($club);
        return new PublicWebsite($em, $this->createStub(Security::class), 1);
    }
}
