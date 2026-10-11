<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\CourseParticipant;
use App\Entity\Schedule;
use App\Entity\TrainingUnitResult;
use App\Enum\BookingAttendanceStatus;
use App\Enum\TrainingUnitResultStatus;
use Doctrine\ORM\EntityManagerInterface;

final class InstructorTaskService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /** @return array<string, mixed> */
	public function overview(int $days = 30, ?int $clubId = null, ?int $courseId = null, ?int $instructorId = null, bool $restrictedToInstructor = false): array 
	{
		if ($restrictedToInstructor && $instructorId === null) {
			throw new \InvalidArgumentException(
				'Instructor identity is required.'
			);
		}

        $today = new \DateTimeImmutable('today');
        $since = $today->modify('-30 days');
        $until = $today->modify('+' . $days . ' days');

        $base = $this->em->getRepository(Schedule::class)->createQueryBuilder('s')
            ->leftJoin('s.courses', 'c')->addSelect('c')
            ->leftJoin('c.club', 'club')->addSelect('club')
            ->leftJoin('s.instructor', 'i')->addSelect('i');

        // Populate filters from all existing scheduled courses, not only the visible time window.
        $options = ['clubs' => [], 'courses' => [], 'instructors' => []];
        $optionQuery = clone $base;
        if ($restrictedToInstructor) {
            $optionQuery->andWhere('i.id = :currentInstructor')->setParameter('currentInstructor', $instructorId);
        }
        foreach ($optionQuery->getQuery()->getResult() as $schedule) {
            if (!$schedule instanceof Schedule) { continue; }
            $course = $schedule->getCourses();
            $club = $course?->getClub();
            $instructor = $schedule->getInstructor();
            if ($club?->getId() !== null) { $options['clubs'][$club->getId()] = (string) $club; }
            if ($course?->getId() !== null) { $options['courses'][$course->getId()] = (string) $course; }
            if ($instructor?->getId() !== null) { $options['instructors'][$instructor->getId()] = (string) $instructor; }
        }
        foreach ($options as &$labels) { natcasesort($labels); }
        unset($labels);

        $qb = (clone $base)
            ->andWhere('s.startDate >= :since AND s.startDate <= :until')
            ->setParameter('since', $since->format('Y-m-d'))
            ->setParameter('until', $until->format('Y-m-d'));
        if ($clubId !== null) { $qb->andWhere('club.id = :clubId')->setParameter('clubId', $clubId); }
        if ($courseId !== null) { $qb->andWhere('c.id = :courseId')->setParameter('courseId', $courseId); }
        if ($instructorId !== null) { $qb->andWhere('i.id = :instructorId')->setParameter('instructorId', $instructorId); }
        $qb->orderBy('s.startDate', 'ASC')->addOrderBy('s.startTime', 'ASC');

        $upcoming = []; $attention = [];
        $totals = ['upcoming' => 0, 'attendance' => 0, 'assessments' => 0, 'makeup' => 0];
        foreach ($qb->getQuery()->getResult() as $schedule) {
            if (!$schedule instanceof Schedule || $schedule->getCourses() === null) { continue; }
            $date = $schedule->getStartDate();
            if ($date === null) { continue; }
            $dateKey = $date->format('Y-m-d');
            $isUpcoming = $dateKey >= $today->format('Y-m-d');
            $participants = $this->em->getRepository(CourseParticipant::class)->findBy(['course' => $schedule->getCourses()]);
            $bookings = $this->em->getRepository(Booking::class)->findBy(['schedule' => $schedule]);
            $results = $this->em->getRepository(TrainingUnitResult::class)->findBy(['schedule' => $schedule]);
            $bookingByMember = []; $resultByParticipant = [];
            foreach ($bookings as $booking) {
                $memberId = $booking->getMember()?->getId();
                if ($memberId !== null) { $bookingByMember[$memberId] = $booking; }
            }
            foreach ($results as $result) {
                $participantId = $result->getCourseParticipant()?->getId();
                if ($participantId !== null) { $resultByParticipant[$participantId] = $result; }
            }
            $attendance = 0; $assessments = 0; $makeup = 0;
            foreach ($participants as $participant) {
                $pid = $participant->getId(); $mid = $participant->getMember()?->getId();
                if ($pid === null || $mid === null) { continue; }
                $booking = $bookingByMember[$mid] ?? null;
                $result = $resultByParticipant[$pid] ?? null;
                if ($booking === null || $booking->getAttendanceStatus() === BookingAttendanceStatus::PLANNED) { ++$attendance; }
                if ($result === null || $result->getStatus() === TrainingUnitResultStatus::OPEN) { ++$assessments; }
                if ($booking?->getAttendanceStatus() === BookingAttendanceStatus::MAKEUP_REQUIRED
                    || $result?->getStatus() === TrainingUnitResultStatus::REPEAT_REQUIRED
                    || $result?->getStatus() === TrainingUnitResultStatus::FAILED) { ++$makeup; }
            }
            $overdue = $dateKey < $today->format('Y-m-d');
            $priority = $overdue && ($attendance + $assessments > 0) ? 3 : ($makeup > 0 ? 2 : 1);
            $item = [
                'id' => $schedule->getId(), 'title' => (string) $schedule,
                'course' => (string) $schedule->getCourses(), 'date' => $date->format('d.m.Y'),
                'dateSort' => $dateKey, 'time' => $schedule->getStartTime()?->format('H:i'),
                'instructor' => (string) ($schedule->getInstructor() ?? ''),
                'attendance' => $attendance, 'assessments' => $assessments, 'makeup' => $makeup,
                'participants' => count($participants), 'priority' => $priority,
                'priorityLabel' => $priority === 3 ? 'Überfällig' : ($priority === 2 ? 'Nachholbedarf' : 'Offen'),
            ];
            if ($isUpcoming) { $upcoming[] = $item; ++$totals['upcoming']; }
            if ((!$isUpcoming || $dateKey === $today->format('Y-m-d')) && $attendance + $assessments + $makeup > 0) {
                $attention[] = $item;
                $totals['attendance'] += $attendance;
                $totals['assessments'] += $assessments;
                $totals['makeup'] += $makeup;
            }
        }
        usort($attention, static fn (array $a, array $b): int =>
            ($b['priority'] <=> $a['priority']) ?: strcmp($a['dateSort'], $b['dateSort']));
        return [
            'upcoming' => array_slice($upcoming, 0, 20),
            'attention' => array_slice($attention, 0, 40),
            'totals' => $totals,
            'options' => $options,
            'filters' => ['club' => $clubId, 'course' => $courseId, 'instructor' => $instructorId],
        ];
    }
}
