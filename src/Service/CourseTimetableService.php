<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\Courses;
use App\Entity\Schedule;
final class CourseTimetableService
{
    /** @return list<Schedule> */
    public function appointments(Courses $course): array
    {
        $items = $course->getSchedule()->filter(
            static fn (Schedule $s): bool => $s->getStartDate() !== null
        )->toArray();
        usort($items, static function (Schedule $a, Schedule $b): int {
            $first = $a->getStartDate()->format('Ymd') . ($a->getStartTime()?->format('His') ?? '000000');
            $second = $b->getStartDate()->format('Ymd') . ($b->getStartTime()?->format('His') ?? '000000');
            return $first <=> $second;
        });
        return array_values($items);
    }
    public function csv(Courses $course): string
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Datum', 'Beginn', 'Ende', 'Einheit', 'Ort', 'Ausbilder'], ';');
        foreach ($this->appointments($course) as $s) {
            fputcsv($stream, [
                $s->getStartDate()?->format('d.m.Y') ?? '',
                $s->getStartTime()?->format('H:i') ?? '',
                $s->getEndTimeLabel(),
                $s->getTrainingUnitLabel(),
                $s->getLocation() ?? '',
                $s->getInstructor()?->getFullName() ?? '',
            ], ';');
        }
        rewind($stream);
        $value = stream_get_contents($stream);
        fclose($stream);
        return $value;
    }
}
