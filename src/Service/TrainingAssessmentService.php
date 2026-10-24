<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Schedule;
use App\Entity\TrainingUnitResult;
use App\Enum\BookingAttendanceStatus;
use App\Enum\TrainingUnitResultStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class TrainingAssessmentService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly ValidatorInterface $validator) {}

    /** @param array<int, array{attendance: ?BookingAttendanceStatus, result: ?TrainingUnitResultStatus}> $changes */
    public function save(Schedule $schedule, array $participants, array $changes, array $existing, array $bookings, ?\App\Entity\Member $assessor = null): int
    {
        if (in_array($schedule->getCourses()?->getStatus(), [\App\Enum\CourseStatus::COMPLETED, \App\Enum\CourseStatus::CANCELLED], true)) {
            throw new \DomainException('Abgeschlossene Kurse dürfen nicht bewertet werden.');
        }
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $count = 0;
            foreach ($participants as $participant) {
                $id = $participant->getId();
                if ($id === null || !isset($changes[$id])) { continue; }
                if ($participant->getCourse() !== $schedule->getCourses()
                    || $participant->getArchivedAt() !== null
                    || !in_array($participant->getStatus(), [\App\Enum\CourseParticipantStatus::REGISTERED, \App\Enum\CourseParticipantStatus::ACTIVE], true)) {
                    throw new \DomainException('Dieser Teilnehmer darf für diesen Termin nicht bewertet werden.');
                }
                $change = $changes[$id];
                $attendance = $change['attendance'];
                $status = $change['result'];
                $booking = $bookings[$id] ?? null;
                $result = $existing[$id] ?? null;
                if ($result?->getStatus() === TrainingUnitResultStatus::PASSED && $attendance !== null && $attendance !== BookingAttendanceStatus::PRESENT) {
                    throw new \DomainException('Die Anwesenheit eines bestandenen Nachweises darf nicht zurückgenommen werden.');
                }
                if ($attendance !== null) {
                    if (!$booking instanceof Booking) {
                        throw new \DomainException('Für einen Teilnehmer fehlt die Terminbuchung. Bitte zuerst Teilnehmer in den Kurstermin übernehmen.');
                    }
                    $booking->setAttendanceStatus($attendance);
                }
                if ($status !== null) {
                    if ($result?->getStatus() === TrainingUnitResultStatus::PASSED) {
                        throw new \DomainException('Ein bereits bestandener Nachweis darf hier nicht geändert werden.');
                    }
                    $effectiveAttendance = $attendance ?? $booking?->getAttendanceStatus();
                    if ($status === TrainingUnitResultStatus::PASSED && $effectiveAttendance !== BookingAttendanceStatus::PRESENT) {
                        throw new \DomainException('Bestanden ist nur bei bestätigter Anwesenheit möglich.');
                    }
                    $result ??= (new TrainingUnitResult())->setCourseParticipant($participant)->setSchedule($schedule);
                    $result->setStatus($status);
                    $result->setAssessedAt($status === TrainingUnitResultStatus::OPEN ? null : new \DateTimeImmutable('today'));
                    $result->setAssessor($status === TrainingUnitResultStatus::OPEN ? null : $assessor);
                    $violations = $this->validator->validate($result);
                    if (count($violations) > 0) {
                        throw new \DomainException((string) $violations->get(0)->getMessage());
                    }
                    if ($result->getId() === null) { $this->em->persist($result); }
                }
                ++$count;
            }
            $this->em->flush();
            $connection->commit();
            return $count;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw $e;
        }
    }
}
