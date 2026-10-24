<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Club;
use App\Entity\MembershipPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MembershipPolicyTest extends TestCase
{
    public static function dates(): iterable
    {
        yield ['2026-09-29', '2026-12-31'];
        yield ['2026-09-30 23:59:59', '2026-12-31'];
        yield ['2026-10-01', '2027-12-31'];
        yield ['2026-12-31', '2027-12-31'];
        yield ['2027-01-01', '2027-12-31'];
    }

    #[DataProvider('dates')]
    public function testExactCutoffAndLateReceipt(string $received, string $expected): void
    {
        $policy = new MembershipPolicy(new Club());
        $policy->setCancellationCutoff('09-30');
        $policy->setMembershipYearEnd('12-31');
        self::assertSame($expected, $policy->cancellationDate(new \DateTimeImmutable($received))->format('Y-m-d'));
    }

    public function testNoImplicitCancellationRule(): void
    {
        $this->expectException(\DomainException::class);
        (new MembershipPolicy(new Club()))->cancellationDate(new \DateTimeImmutable('2026-09-30'));
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $policy = new MembershipPolicy(new Club());
        $policy->setCancellationCutoff('02-30');
        $policy->setMembershipYearEnd('12-31');
        $this->expectException(\DomainException::class);
        $policy->validateRules();
    }
}
