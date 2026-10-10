<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\Schedule;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Konvertiert geplante Schedule-Datensaetze in Vorlagenwerte.
 * Schreibt selbst keine Datenbankeintraege.
 */
final class CourseTemplateExportService
{
    /**
     * @param iterable<Schedule> $schedules
     * @return array{entries: list<array{type: \App\Entity\TrainingUnitType, sequence: int, dayOffset: int, startTime: \DateTimeInterface, durationMinutes: int, title: string, date: string}>, skipped: list<TranslatableMessage>, baseDate: ?string}
     */
    public function prepare(iterable $schedules, Club $club): array
    {
        $valid = [];
        $skipped = [];
        $seen = [];

        foreach ($schedules as $schedule) {
            $type = $schedule->getTrainingUnitType();
            $sequence = $schedule->getTrainingUnitSequence();
            $date = $schedule->getStartDate();
            $time = $schedule->getStartTime();
            $minutes = $schedule->getDuration();
            $title = $schedule->getTitle() ?? 'Unbenannter Termin';

            if ($type === null || $type->getId() === null || $sequence === null || $sequence < 1
                || $date === null || $time === null || $minutes === null || $minutes < 1) {
                $skipped[] = new TranslatableMessage('template.skipped_missing_data', ['%title%' => $title]);
                continue;
            }
            if ($type->getClub() !== null && $type->getClub()->getId() !== $club->getId()) {
                $skipped[] = new TranslatableMessage('template.skipped_wrong_club', ['%title%' => $title]);
                continue;
            }
            $key = $type->getId() . ':' . $sequence;
            if (isset($seen[$key])) {
                $skipped[] = new TranslatableMessage('template.skipped_duplicate', ['%title%' => $title]);
                continue;
            }
            $seen[$key] = true;
            $valid[] = [
                'type' => $type,
                'sequence' => $sequence,
                'startTime' => \DateTimeImmutable::createFromInterface($time),
                'durationMinutes' => $minutes,
                'dateObject' => \DateTimeImmutable::createFromFormat('!Y-m-d', $date->format('Y-m-d')),
                'title' => $title,
            ];
        }

        if ($valid === []) {
            return ['entries' => [], 'skipped' => $skipped, 'baseDate' => null];
        }
        usort($valid, static function (array $a, array $b): int {
            return ($a['dateObject'] <=> $b['dateObject'])
                ?: ($a['startTime']->format('H:i:s') <=> $b['startTime']->format('H:i:s'));
        });
        $base = $valid[0]['dateObject'];
        $entries = [];
        foreach ($valid as $item) {
            $entries[] = [
                'type' => $item['type'],
                'sequence' => $item['sequence'],
                'dayOffset' => (int) $base->diff($item['dateObject'])->format('%a'),
                'startTime' => $item['startTime'],
                'durationMinutes' => $item['durationMinutes'],
                'title' => $item['title'],
                'date' => $item['dateObject']->format('d.m.Y'),
            ];
        }
        return ['entries' => $entries, 'skipped' => $skipped, 'baseDate' => $base->format('d.m.Y')];
    }
}
