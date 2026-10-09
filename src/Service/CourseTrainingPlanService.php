<?php

namespace App\Service;

use App\Entity\Courses;
use App\Entity\CourseTrainingUnit;

class CourseTrainingPlanService
{
    /**
     * Übernimmt fehlende Einträge aus der Zielqualifikation
     * in den konkreten Kurs.
     *
     * Bereits vorhandene Kurseinträge werden nicht verändert.
     *
     * @return int Anzahl neu übernommener Einträge
     */
    public function importFromQualification(
        Courses $course
    ): int {
        $qualification = $course->getQualification();

        if ($qualification === null) {
            throw new \LogicException(
                'Dem Kurs ist keine Zielqualifikation zugeordnet.'
            );
        }

        if ($qualification->getTrainingUnits()->isEmpty()) {
            throw new \LogicException(
                sprintf(
                    'Für die Qualifikation "%s" ist noch '
                    . 'kein Ausbildungsplan definiert.',
                    (string) $qualification
                )
            );
        }

        $created = 0;

        foreach (
            $qualification->getTrainingUnits()
            as $qualificationUnit
        ) {
            $type = $qualificationUnit
                ->getTrainingUnitType();

            if ($type === null) {
                continue;
            }

            /*
             * Prüfen, ob der konkrete Kurs diesen Typ
             * bereits besitzt.
             */
            $exists = false;

            foreach ($course->getTrainingUnits() as $courseUnit) {
                if (
                    $courseUnit->getTrainingUnitType()
                    === $type
                ) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                continue;
            }

            $courseUnit = new CourseTrainingUnit();

            $courseUnit
                ->setTrainingUnitType($type)
                ->setRequiredSessions(
                    $qualificationUnit
                        ->getRequiredSessions()
                )
                ->setRequired(
                    $qualificationUnit->isRequired()
                )
                ->setNotes(
                    $qualificationUnit->getNotes()
                );

            /*
             * addTrainingUnit() setzt gleichzeitig
             * CourseTrainingUnit::$course.
             */
            $course->addTrainingUnit(
                $courseUnit
            );

            ++$created;
        }

        return $created;
    }
}