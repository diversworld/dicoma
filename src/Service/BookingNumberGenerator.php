<?php

namespace App\Service;

use App\Repository\BookingRepository;

class BookingNumberGenerator
{
    private ?string $prefix = null;

    private ?int $sequence = null;

    public function __construct(
        private readonly BookingRepository $bookingRepository
    ) {
    }

    public function next(): string
    {
        $currentPrefix = (new \DateTimeImmutable())
            ->format('Ym');

        /*
         * Beim ersten Aufruf innerhalb des Requests bestimmen
         * wir die nächste freie laufende Nummer aus der DB.
         */
        if (
            $this->prefix !== $currentPrefix
            || $this->sequence === null
        ) {
            $this->prefix = $currentPrefix;
            $this->sequence = $this->loadInitialSequence(
                $currentPrefix
            );
        }

        $number = sprintf(
            '%s%04d',
            $this->prefix,
            $this->sequence
        );

        ++$this->sequence;

        return $number;
    }

    private function loadInitialSequence(
        string $prefix
    ): int {
        $lastBooking = $this
            ->bookingRepository
            ->findOneBy(
                [],
                ['id' => 'DESC']
            );

        $lastNumber = $lastBooking
            ?->getBookingnumber();

        if (
            $lastNumber === null
            || !str_starts_with(
                $lastNumber,
                $prefix
            )
        ) {
            return 1;
        }

        return ((int) substr(
            $lastNumber,
            6
        )) + 1;
    }
}