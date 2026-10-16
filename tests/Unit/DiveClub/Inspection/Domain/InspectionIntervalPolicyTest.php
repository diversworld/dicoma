<?php

declare(strict_types=1);

namespace App\Tests\Unit\DiveClub\Inspection\Domain;

use App\DiveClub\Inspection\Domain\InspectionIntervalPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InspectionIntervalPolicyTest extends TestCase
{
    public function testAddsThirtyCalendarMonths(): void
    {
        $due = (new InspectionIntervalPolicy())->nextDueDate(new DateTimeImmutable('2026-10-15'));
        self::assertSame('2029-04-15', $due->format('Y-m-d'));
    }

    public function testClampsToLastDayOfTargetMonth(): void
    {
        $due = (new InspectionIntervalPolicy())->nextDueDate(new DateTimeImmutable('2026-01-31'));
        self::assertSame('2028-07-31', $due->format('Y-m-d'));
    }

    public function testUsesDatePartAndNormalizesTime(): void
    {
        $due = (new InspectionIntervalPolicy())->nextDueDate(new DateTimeImmutable('2026-10-15 18:42:31'));
        self::assertSame('2029-04-15 00:00:00', $due->format('Y-m-d H:i:s'));
    }
}
