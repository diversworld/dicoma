<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\SoftwareBackup;
use App\Service\SoftwareUpdater;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SoftwareUpdaterTest extends TestCase
{
    #[DataProvider('repositoryUrls')]
    public function testRepositoryUrlValidation(string $origin, bool $expected): void
    {
        self::assertSame($expected, SoftwareUpdater::repositoryMatches($origin, 'diversworld/dicoma'));
    }

    public static function repositoryUrls(): iterable
    {
        yield ['https://github.com/diversworld/dicoma.git', true];
        yield ['git@github.com:diversworld/dicoma.git', true];
        yield ['ssh://git@github.com/diversworld/dicoma', true];
        yield ['https://github.com/attacker/dicoma.git', false];
        yield ['https://example.com/diversworld/dicoma.git', false];
    }

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
