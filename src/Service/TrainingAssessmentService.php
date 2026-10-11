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
    public function save(Schedule $schedule, array $participants, array $changes, array $existing, array $bookings): int
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $count = 0;
            foreach ($participants as $participant) {
                $id = $participant->getId();
                if ($id === null || !isset($changes[$id])) { continue; }
                $change = $changes[$id];
                $attendance = $change['attendance'];
                $status = $change['result'];
                $booking = $bookings[$id] ?? null;
                $result = $existing[$id] ?? null;
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
