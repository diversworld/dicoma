<?php

namespace App\Service;

use App\Entity\Courses;
use App\Entity\Schedule;
use App\Repository\ScheduleRepository;
use Doctrine\ORM\EntityManagerInterface;

class CourseScheduleGenerator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly ScheduleParticipantSyncService $participantSyncService
    ) {
    }

    /**
     * Erzeugt fehlende Termin-Rohlinge anhand des
     * Ausbildungsplans des Kurses.
     *
     * @return int Anzahl neu erzeugter Termine
     */
    public function generate(Courses $course): int
    {
        $created = 0;

        foreach ($course->getTrainingUnits() as $courseTrainingUnit) {
            $trainingUnitType =
                $courseTrainingUnit->getTrainingUnitType();

            if ($trainingUnitType === null) {
                continue;
            }

            $required =
                $courseTrainingUnit->getRequiredSessions();

            if ($required < 1) {
                continue;
            }

            $existing = $this
                ->scheduleRepository
                ->countForCourseAndTrainingUnitType(
                    $course,
                    $trainingUnitType
                );

            /*
             * Bereits genügend Termine vorhanden.
             */
            if ($existing >= $required) {
                continue;
            }

            /*
             * Nur die noch fehlenden Termine erzeugen.
             */
            for (
                $sequence = $existing + 1;
                $sequence <= $required;
                ++$sequence
            ) {
                $schedule = new Schedule();

                $schedule
                    ->setCourses($course)
                    ->setTrainingUnitType(
                        $trainingUnitType
                    )
                    ->setTrainingUnitSequence(
                        $sequence
                    )
                    ->setTitle(
                        $this->createTitle(
                            $trainingUnitType->getName()
                                ?? 'Ausbildungseinheit',
                            $sequence,
                            $required
                        )
                    );

                $this->entityManager->persist(
                    $schedule
                );

                ++$created;
            }
        }

        $this->entityManager->flush();

        // Newly generated schedule placeholders receive bookings as well.
        // sync() is idempotent and never changes existing attendance data.
        if ($created > 0) {
            foreach ($this->scheduleRepository->findBy(['courses' => $course]) as $schedule) {
                $this->participantSyncService->sync($schedule);
            }
        }

        return $created;
    }

    private function createTitle(
        string $name,
        int $sequence,
        int $required
    ): string {
        /*
         * Bei einer einmaligen Einheit ist eine Nummerierung
         * unnötig:
         *
         * "Theorieprüfung"
         *
         * statt
         *
         * "Theorieprüfung 1"
         */
        if ($required === 1) {
            return $name;
        }

        return sprintf(
            '%s %d',
            $name,
            $sequence
        );
    }
}