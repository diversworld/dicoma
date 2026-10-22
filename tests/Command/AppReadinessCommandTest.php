<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\AppReadinessCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class AppReadinessCommandTest extends TestCase
{
    public function testDatabaseConnectionFailureReturnsFailureWithoutLeakingDetails(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('connect')->willThrowException(new \RuntimeException('secret password'));
        $tester = new CommandTester(new AppReadinessCommand($connection));
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringNotContainsString('secret password', $tester->getDisplay());
    }
}
