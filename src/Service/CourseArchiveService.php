<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\TrainingUnitResult;
use App\Enum\CourseStatus;
use Doctrine\ORM\EntityManagerInterface;

final class CourseArchiveService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TrainingProgressService $progress,
        private readonly CourseAuditLogger $audit,
    ) {}

    public function snapshot(CourseParticipant $participant): array
    {
        $course = $participant->getCourse();
        if ($course === null) { throw new \LogicException('Kurs fehlt.'); }
        $evidence = [];
        foreach ($this->em->getRepository(TrainingUnitResult::class)->findBy(['courseParticipant' => $participant]) as $result) {
            $evidence[] = [
                'session' => (string) $result->getSchedule(),
                'date' => $result->getSchedule()?->getStartDate()?->format('Y-m-d'),
                'status' => $result->getStatus()->value,
                'assessedAt' => $result->getAssessedAt()?->format('Y-m-d'),
                'assessor' => $result->getAssessor()?->getFullName(),
                'notes' => $result->getNotes(),
            ];
        }
        $attendance = [];
        foreach ($this->em->getRepository(Booking::class)->findBy(['schedule' => $course->getSchedule()->toArray()]) as $booking) {
            if ($booking->getCourseParticipant() !== $participant
                && ($participant->getMember() === null || $booking->getMember() !== $participant->getMember())) { continue; }
            $attendance[] = [
                'session' => (string) $booking->getSchedule(), 'status' => $booking->getAttendanceStatus()->value,
                'recordedAt' => $booking->getAttendanceRecordedAt()?->format(DATE_ATOM),
                'notes' => $booking->getAttendanceNotes(), 'makeupFor' => $booking->getMakeupFor()?->getId(),
            ];
        }
        $goals = [];
        foreach ($course->getTrainingUnits() as $unit) {
            $goals[] = ['unit' => (string) $unit->getTrainingUnitType(), 'goals' => $unit->getNotes()];
        }
        return [
            'participant' => $participant->getFullName(), 'personId' => $participant->getPerson()?->getId(),
            'course' => $course->getTitle(), 'club' => (string) $course->getClub(),
            'qualification' => (string) $course->getQualification(), 'issuer' => $course->getQualification()?->getIssuer(),
            'completedAt' => $participant->getCompletedAt()?->format('Y-m-d'), 'status' => $participant->getStatus()->value,
            'requirements' => $this->progress->calculate($participant)['requirements'],
            'goals' => $goals, 'evidence' => $evidence, 'attendance' => $attendance,
        ];
    }

    public function close(Courses $course): void
    {
        $this->em->getConnection()->transactional(function () use ($course): void {
            $this->em->lock($course, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($course);
            foreach ($course->getParticipants() as $participant) { $this->em->refresh($participant); }
            if (!$course->canBeCompleted()) { throw new \DomainException('Alle Teilnehmer einschließlich Warteliste benötigen einen abschließenden Status.'); }
            foreach ($course->getParticipants() as $participant) {
                if ($participant->getArchivedAt() === null) { $participant->archiveTraining($this->snapshot($participant)); }
            }
            $before = $course->getStatus()->value;
            $course->setStatus(CourseStatus::COMPLETED);
            if ($course->getId() !== null) { $this->audit->record($course->getId(), null, 'course_completed', $before, CourseStatus::COMPLETED->value); }
            $this->em->flush();
        });
    }
}
