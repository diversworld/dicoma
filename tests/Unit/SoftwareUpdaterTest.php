<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\SoftwareBackup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SoftwareUpdaterTest extends TestCase
{
    #[DataProvider('backupPaths')]
    public function testBackupExclusions(string $path, bool $expected): void
    {
        self::assertSame($expected, SoftwareBackup::isExcluded($path));
    }

    public static function backupPaths(): iterable
    {
        yield ['.env.local', false];
        yield ['private/contracts/a.pdf', false];
        yield ['.git/config', true];
        yield ['var/backups/old.zip', true];
        yield ['var/cache/prod/pools', true];
        yield ['vendor/autoload.php', true];
    }
}
