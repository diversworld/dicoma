<?php

declare(strict_types=1);

namespace App\DiveClub\Shared\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable half-open interval [start, end).
 *
 * Adjacent intervals do not overlap: [09:00, 10:00) and [10:00, 11:00)
 * may be booked consecutively without an artificial one-second gap.
 */
final readonly class TimeRange
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException('The interval end must be later than its start.');
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }

    public function contains(DateTimeImmutable $instant): bool
    {
        return $this->start <= $instant && $instant < $this->end;
    }

    public function withBuffer(int $beforeMinutes, int $afterMinutes): self
    {
        if ($beforeMinutes < 0 || $afterMinutes < 0) {
            throw new InvalidArgumentException('Buffer durations cannot be negative.');
        }

        return new self(
            $this->start->modify(sprintf('-%d minutes', $beforeMinutes)),
            $this->end->modify(sprintf('+%d minutes', $afterMinutes)),
        );
    }
}
