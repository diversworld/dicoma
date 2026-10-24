<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\CheckTestDatabaseCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckTestDatabaseCommandTest extends TestCase
{
    /** @return iterable<string, array{string, ?string}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'production environment' => ['prod', 'db_test'];
        yield 'missing expected name' => ['test', null];
        yield 'production name' => ['test', 'h20292_dicoma'];
        yield 'ordinary database' => ['test', 'db'];
        yield 'injected option' => ['test', 'db_test; SELECT 1'];
    }

    #[DataProvider('invalidConfigurations')]
    public function testInvalidConfigurationDoesNotConnect(string $environment, ?string $expected): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('fetchOne');
        $connection->expects(self::never())->method('getDatabasePlatform');
        $tester = new CommandTester(new CheckTestDatabaseCommand($connection, $environment));
        self::assertSame(Command::FAILURE, $tester->execute($expected === null ? [] : ['--expected' => $expected]));
    }

    public function testConfiguredNameIsComparedWithTheRealConnection(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MariaDb1027Platform());
        $connection->method('fetchOne')->willReturn('db');
        $tester = new CommandTester(new CheckTestDatabaseCommand($connection, 'test'));
        self::assertSame(Command::FAILURE, $tester->execute(['--expected' => 'db_test']));
    }

    public function testMatchingTestDatabasePasses(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MariaDb1027Platform());
        $connection->method('fetchOne')->willReturn('db_test');
        $tester = new CommandTester(new CheckTestDatabaseCommand($connection, 'test'));
        self::assertSame(Command::SUCCESS, $tester->execute(['--expected' => 'db_test']));
    }

    public function testConnectionErrorDoesNotLeakDriverDetails(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MariaDb1027Platform());
        $connection->method('fetchOne')->willThrowException(new Exception('secret database password'));
        $tester = new CommandTester(new CheckTestDatabaseCommand($connection, 'test'));
        self::assertSame(Command::FAILURE, $tester->execute(['--expected' => 'db_test']));
        self::assertStringNotContainsString('secret database password', $tester->getDisplay());
    }
}
