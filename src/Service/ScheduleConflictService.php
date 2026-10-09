<?php

namespace App\Service;

use App\Entity\Schedule;
use App\Repository\ScheduleRepository;

final class ScheduleConflictService
{
    public function __construct(
        private readonly ScheduleRepository $scheduleRepository
    ) {
    }

    /**
     * @param iterable<Schedule> $schedules
     *
     * @return array<int, array{
     *     first: Schedule,
     *     second: Schedule
     * }>
     */
    public function findConflicts(
        iterable $schedules
    ): array {
        $currentSchedules = [];

        foreach ($schedules as $schedule) {
            $currentSchedules[] = $schedule;
        }

        $candidates = $this->scheduleRepository
            ->createQueryBuilder('s')
            ->andWhere('s.instructor IS NOT NULL')
            ->andWhere('s.startDate IS NOT NULL')
            ->andWhere('s.startTime IS NOT NULL')
            ->getQuery()
            ->getResult();

        $allSchedules = $currentSchedules;

        foreach ($candidates as $candidate) {
            $alreadyIncluded = false;

            foreach ($currentSchedules as $current) {
                if (
                    $current === $candidate
                    || (
                        $current->getId() !== null
                        && $current->getId() === $candidate->getId()
                    )
                ) {
                    $alreadyIncluded = true;
                    break;
                }
            }

            if (!$alreadyIncluded) {
                $allSchedules[] = $candidate;
            }
        }

        $conflicts = [];

        for ($i = 0; $i < count($allSchedules); ++$i) {
            for ($j = $i + 1; $j < count($allSchedules); ++$j) {
                $first = $allSchedules[$i];
                $second = $allSchedules[$j];

                if (
                    $first->getInstructor() === null
                    || $second->getInstructor() === null
                    || $first->getInstructor()->getId()
                        !== $second->getInstructor()->getId()
                ) {
                    continue;
                }

                $firstStart = $this->getStart($first);
                $secondStart = $this->getStart($second);

                $firstEnd = $this->getEnd($first);
                $secondEnd = $this->getEnd($second);

                if (
                    $firstStart === null
                    || $secondStart === null
                    || $firstEnd === null
                    || $secondEnd === null
                ) {
                    continue;
                }

                if (
                    $firstStart < $secondEnd
                    && $secondStart < $firstEnd
                ) {
                    // Nur Konflikte mit mindestens einem
                    // Termin der aktuellen Planung melden.
                    if (
                        in_array($first, $currentSchedules, true)
                        || in_array($second, $currentSchedules, true)
                    ) {
                        $conflicts[] = [
                            'first' => $first,
                            'second' => $second,
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }

    private function getStart(
        Schedule $schedule
    ): ?\DateTimeImmutable {
        if (
            $schedule->getStartDate() === null
            || $schedule->getStartTime() === null
        ) {
            return null;
        }

        return new \DateTimeImmutable(
            $schedule->getStartDate()->format('Y-m-d')
            . ' '
            . $schedule->getStartTime()->format('H:i:s')
        );
    }

    private function getEnd(
        Schedule $schedule
    ): ?\DateTimeImmutable {
        $start = $this->getStart($schedule);
        $duration = $schedule->getDuration();

        if (
            $start === null
            || $duration === null
            || $duration <= 0
        ) {
            return null;
        }

        return $start->modify(
            sprintf('+%d minutes', $duration)
        );
    }
}
