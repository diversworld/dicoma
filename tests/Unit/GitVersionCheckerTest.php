<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\GitVersionChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GitVersionCheckerTest extends TestCase
{
    #[DataProvider('versions')]
    public function testTagNormalization(string $tag, ?string $expected): void
    {
        self::assertSame($expected, GitVersionChecker::normalize($tag));
    }

    public static function versions(): iterable
    {
        yield ['0.3.0', '0.3.0'];
        yield ['v1.2.3', '1.2.3'];
        yield ['0.4', '0.4.0'];
        yield ['v2.0.0-rc.1', null];
        yield ['release-2.0.0', null];
        yield ['01.2.3', null];
    }
}
