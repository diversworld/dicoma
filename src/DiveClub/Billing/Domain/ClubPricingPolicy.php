<?php

declare(strict_types=1);

namespace App\DiveClub\Billing\Domain;

use InvalidArgumentException;

/**
 * Club billing policy: the club does not show VAT on its invoices.
 * All monetary values are integer minor units (e.g. cents), never floats.
 * This class does not make a legal determination about the club's VAT status.
 */
final readonly class ClubPricingPolicy
{
    public function priceLine(int $unitAmountMinor, int $quantity): int
    {
        if ($unitAmountMinor < 0) {
            throw new InvalidArgumentException('Unit amount cannot be negative.');
        }
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least one.');
        }

        if ($unitAmountMinor !== 0 && $quantity > intdiv(PHP_INT_MAX, $unitAmountMinor)) {
            throw new InvalidArgumentException('Line amount exceeds the supported integer range.');
        }

        return $unitAmountMinor * $quantity;
    }

    /** @param list<int> $lineAmountsMinor */
    public function total(array $lineAmountsMinor): int
    {
        $total = 0;
        foreach ($lineAmountsMinor as $amount) {
            if ($amount < 0 || $total > PHP_INT_MAX - $amount) {
                throw new InvalidArgumentException('Invalid or overflowing line amount.');
            }
            $total += $amount;
        }

        return $total;
    }

    public function vatAmountMinor(int $netOrGrossAmountMinor): int
    {
        if ($netOrGrossAmountMinor < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        return 0;
    }
}
