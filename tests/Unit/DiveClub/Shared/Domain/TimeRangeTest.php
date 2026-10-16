<?php

declare(strict_types=1);

namespace App\Tests\Unit\DiveClub\Shared\Domain;

use App\DiveClub\Shared\Domain\TimeRange;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeRangeTest extends TestCase
{
    public function testAdjacentIntervalsDoNotOverlap(): void
    {
        $first = new TimeRange(new DateTimeImmutable('2026-10-15 09:00:00'), new DateTimeImmutable('2026-10-15 10:00:00'));
        $second = new TimeRange(new DateTimeImmutable('2026-10-15 10:00:00'), new DateTimeImmutable('2026-10-15 11:00:00'));

        self::assertFalse($first->overlaps($second));
        self::assertFalse($second->overlaps($first));
    }

    public function testOverlappingIntervalsOverlap(): void
    {
        $first = new TimeRange(new DateTimeImmutable('2026-10-15 09:00:00'), new DateTimeImmutable('2026-10-15 10:30:00'));
        $second = new TimeRange(new DateTimeImmutable('2026-10-15 10:00:00'), new DateTimeImmutable('2026-10-15 11:00:00'));

        self::assertTrue($first->overlaps($second));
    }

    public function testStartIsIncludedAndEndIsExcluded(): void
    {
        $range = new TimeRange(new DateTimeImmutable('2026-10-15 09:00:00'), new DateTimeImmutable('2026-10-15 10:00:00'));

        self::assertTrue($range->contains(new DateTimeImmutable('2026-10-15 09:00:00')));
        self::assertFalse($range->contains(new DateTimeImmutable('2026-10-15 10:00:00')));
    }

    public function testRejectsEmptyOrReversedInterval(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TimeRange(new DateTimeImmutable('2026-10-15 10:00:00'), new DateTimeImmutable('2026-10-15 10:00:00'));
    }

    public function testBufferExpandsTheRange(): void
    {
        $range = new TimeRange(new DateTimeImmutable('2026-10-15 09:00:00'), new DateTimeImmutable('2026-10-15 10:00:00'));
        $buffered = $range->withBuffer(15, 30);

        self::assertSame('2026-10-15 08:45:00', $buffered->start->format('Y-m-d H:i:s'));
        self::assertSame('2026-10-15 10:30:00', $buffered->end->format('Y-m-d H:i:s'));
    }

    public function testRejectsNegativeBuffer(): void
    {
        $range = new TimeRange(new DateTimeImmutable('2026-10-15 09:00:00'), new DateTimeImmutable('2026-10-15 10:00:00'));

        $this->expectException(InvalidArgumentException::class);
        $range->withBuffer(-1, 0);
    }
}
