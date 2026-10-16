<?php

declare(strict_types=1);

namespace App\Tests\Unit\DiveClub\Billing\Domain;

use App\DiveClub\Billing\Domain\ClubPricingPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClubPricingPolicyTest extends TestCase
{
    public function testCalculatesLinesUsingIntegerMinorUnits(): void
    {
        $policy = new ClubPricingPolicy();
        self::assertSame(3750, $policy->priceLine(1250, 3));
        self::assertSame(5000, $policy->total([3750, 1250]));
    }

    public function testDoesNotAddVatToClubInvoiceTotals(): void
    {
        self::assertSame(0, (new ClubPricingPolicy())->vatAmountMinor(12345));
    }

    public function testRejectsNegativeAmountsAndInvalidQuantities(): void
    {
        $policy = new ClubPricingPolicy();
        $this->expectException(InvalidArgumentException::class);
        $policy->priceLine(-1, 1);
    }
}
