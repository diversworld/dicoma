<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CourseParticipant;
use App\Entity\TrainingUnitResult;
use App\Enum\TrainingUnitResultStatus;
use App\Repository\TrainingUnitResultRepository;

final class TrainingProgressService
{
    public function __construct(private readonly TrainingUnitResultRepository $results) {}

    public function calculate(CourseParticipant $participant): array
    {
        $course = $participant->getCourse();
        if ($course === null) {
            throw new \LogicException('Der Teilnehmer ist keinem Kurs zugeordnet.');
        }

        $requirements = [];
        foreach ($course->getTrainingUnits() as $requirement) {
            $type = $requirement->getTrainingUnitType();
            if ($type === null || $type->getId() === null) {
                continue;
            }
            $id = $type->getId();
            $requirements[$id] = [
                'name' => (string) $type,
                'required' => $requirement->isRequired(),
                'target' => $requirement->getRequiredSessions(),
                'passed' => 0,
                'open' => 0,
                'failed' => 0,
                'repeat' => 0,
                'sessions' => [],
            ];
        }

        $seen = [];
        foreach ($this->results->findBy(['courseParticipant' => $participant]) as $result) {
            if (!$result instanceof TrainingUnitResult) {
                continue;
            }
            $schedule = $result->getSchedule();
            $type = $schedule?->getTrainingUnitType();
            $id = $type?->getId();
            if ($id === null || !isset($requirements[$id]) || $schedule?->getCourses()?->getId() !== $course->getId()) {
                continue;
            }
            $scheduleId = $schedule->getId();
            if ($scheduleId === null || isset($seen[$scheduleId])) {
                continue;
            }
            $seen[$scheduleId] = true;
            $status = $result->getStatus();
            $key = match ($status) {
                TrainingUnitResultStatus::PASSED => 'passed',
                TrainingUnitResultStatus::FAILED => 'failed',
                TrainingUnitResultStatus::REPEAT_REQUIRED => 'repeat',
                default => 'open',
            };
            ++$requirements[$id][$key];
            $requirements[$id]['sessions'][] = [
                'title' => (string) $schedule,
                'date' => $schedule->getStartDate()?->format('d.m.Y'),
                'status' => $status->label(),
            ];
        }

        $target = 0;
        $passed = 0;
        $complete = $requirements !== [];
        foreach ($requirements as &$row) {
            $row['credited'] = min($row['passed'], $row['target']);
            $row['missing'] = max(0, $row['target'] - $row['credited']);
            $row['percent'] = $row['target'] > 0 ? (int) round($row['credited'] / $row['target'] * 100) : 100;
            if ($row['required']) {
                $target += $row['target'];
                $passed += $row['credited'];
                if ($row['missing'] > 0) {
                    $complete = false;
                }
            }
        }
        unset($row);

        return [
            'requirements' => array_values($requirements),
            'target' => $target,
            'passed' => $passed,
            'percent' => $target > 0 ? (int) round($passed / $target * 100) : 0,
            'complete' => $complete && $target > 0,
        ];
    }
}
