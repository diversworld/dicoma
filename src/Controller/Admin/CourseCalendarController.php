<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Schedule;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use App\Repository\ScheduleRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[IsGranted('ROLE_ADMIN')]
final class CourseCalendarController extends AbstractController
{
    #[Route('/admin/course-calendar/duplicate/{id}', name: 'admin_course_calendar_duplicate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function duplicate(
        Schedule $schedule,
        Request $request,
        EntityManagerInterface $entityManager,
        AdminUrlGenerator $urls
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('calendar_duplicate_' . $schedule->getId(), (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }

        // No bookings, attendance records or unique training-unit sequence are copied.
        $copy = new Schedule();
        $copy->setTitle(($schedule->getTitle() ?? 'Termin') . ' (Kopie)');
        $copy->setCourses($schedule->getCourses());
        $copy->setStartDate($schedule->getStartDate() ? clone $schedule->getStartDate() : null);
        $copy->setStartTime($schedule->getStartTime() ? clone $schedule->getStartTime() : null);
        $copy->setDuration($schedule->getDuration());
        $copy->setLocation($schedule->getLocation());
        $copy->setLocationStreet($schedule->getLocationStreet());
        $copy->setLocationPostal($schedule->getLocationPostal());
        $copy->setLocationCity($schedule->getLocationCity());
        $copy->setInstructor($schedule->getInstructor());
        $copy->setPrice($schedule->getPrice());
        $copy->setNotes($schedule->getNotes());
        // Keep the type but leave sequence null to avoid the course/type/sequence unique key.
        $copy->setTrainingUnitType($schedule->getTrainingUnitType());
        $copy->setTrainingUnitSequence(null);

        $entityManager->persist($copy);
        $entityManager->flush();
        $this->addFlash('success', 'Der Termin wurde ohne Buchungen dupliziert. Bitte Datum und Ausbildungseinheit prüfen.');

        return $this->redirect((clone $urls)->unsetAll()
            ->setController(ScheduleCrudController::class)
            ->setAction(Crud::PAGE_EDIT)
            ->setEntityId($copy->getId())
            ->generateUrl());
    }

    #[Route('/admin/course-calendar', name: 'admin_course_calendar', methods: ['GET'])]
    public function index(Request $request, ScheduleRepository $repository, AdminUrlGenerator $urls, CsrfTokenManagerInterface $csrfTokenManager): Response
    {
        $view = in_array($request->query->get('view'), ['month', 'week', 'day'], true)
            ? $request->query->get('view') : 'month';
        $monthValue = (string) $request->query->get('month', date('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthValue)) {
            $monthValue = date('Y-m');
        }
        $month = \DateTimeImmutable::createFromFormat('!Y-m-d', $monthValue . '-01');
        if (!$month || $month->format('Y-m') !== $monthValue) {
            throw $this->createNotFoundException('Ungültiger Monat.');
        }
        $weekValue = (string) $request->query->get('week', date('Y-m-d'));
        $weekDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $weekValue);
        if (!$weekDay || $weekDay->format('Y-m-d') !== $weekValue) {
            $weekDay = $month;
        }
        $dayValue = (string) $request->query->get('day', $weekValue);
        $selectedDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $dayValue);
        if (!$selectedDay || $selectedDay->format('Y-m-d') !== $dayValue) {
            $selectedDay = $weekDay;
        }
        $weekStart = $weekDay->modify('monday this week');
        $nextMonth = $month->modify('first day of next month');
        $rangeStart = match ($view) {
            'day' => $selectedDay,
            'week' => $weekStart,
            default => $month->modify('monday this week'),
        };
        $rangeEnd = match ($view) {
            'day' => $selectedDay->modify('+1 day'),
            'week' => $weekStart->modify('+7 days'),
            default => $nextMonth->modify('monday this week')->modify('+7 days'),
        };

        $positiveId = static function (mixed $value): ?int {
            if (!is_scalar($value)) { return null; }
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            return $id === false ? null : $id;
        };
        $filters = [
            'club' => $positiveId($request->query->get('club')),
            'course' => $positiveId($request->query->get('course')),
            'instructor' => $positiveId($request->query->get('instructor')),
        ];

        $qb = $repository->createQueryBuilder('s')
            ->leftJoin('s.courses', 'c')->addSelect('c')
            ->leftJoin('s.instructor', 'i')->addSelect('i')
            ->leftJoin('c.club', 'club')
            ->andWhere('s.startDate >= :from AND s.startDate < :until')
            ->setParameter('from', $rangeStart->format('Y-m-d'))
            ->setParameter('until', $rangeEnd->format('Y-m-d'))
            ->orderBy('s.startDate', 'ASC')->addOrderBy('s.startTime', 'ASC');
        if ($filters['club']) { $qb->andWhere('club.id = :club')->setParameter('club', $filters['club']); }
        if ($filters['course']) { $qb->andWhere('c.id = :course')->setParameter('course', $filters['course']); }
        if ($filters['instructor']) { $qb->andWhere('i.id = :instructor')->setParameter('instructor', $filters['instructor']); }

        $options = ['clubs' => [], 'courses' => [], 'instructors' => []];
        foreach ($repository->createQueryBuilder('s')
            ->leftJoin('s.courses', 'c')->addSelect('c')
            ->leftJoin('s.instructor', 'i')->addSelect('i')
            ->leftJoin('c.club', 'club')->addSelect('club')
            ->getQuery()->getResult() as $schedule) {
            if (!$schedule instanceof Schedule) { continue; }
            $course = $schedule->getCourses();
            $club = $course?->getClub();
            $instructor = $schedule->getInstructor();
            if ($club?->getId()) { $options['clubs'][$club->getId()] = (string) $club; }
            if ($course?->getId()) { $options['courses'][$course->getId()] = (string) $course; }
            if ($instructor?->getId()) { $options['instructors'][$instructor->getId()] = (string) $instructor; }
        }
        foreach ($options as &$values) { natcasesort($values); }
        unset($values);

        $events = [];
        foreach ($qb->getQuery()->getResult() as $schedule) {
            if (!$schedule instanceof Schedule || !$schedule->getStartDate()) { continue; }
            $day = $schedule->getStartDate()->format('Y-m-d');
            $startTime = $schedule->getStartTime()?->format('H:i');
            $duration = $schedule->getDuration();
            $start = $startTime !== null ? new \DateTimeImmutable($day . ' ' . $startTime) : null;
            $end = ($start && $duration !== null && $duration > 0)
                ? $start->modify('+' . $duration . ' minutes') : null;
            $events[$day][] = [
                'id' => $schedule->getId(),
                'title' => $schedule->getTitle(),
                'time' => $startTime,
                'end' => $end?->format('H:i'),
                'startMinutes' => $start ? ((int) $start->format('H') * 60 + (int) $start->format('i')) : null,
                'durationMinutes' => $duration,
                'instructorId' => $schedule->getInstructor()?->getId(),
                'instructor' => (string) ($schedule->getInstructor() ?? ''),
                'course' => (string) ($schedule->getCourses() ?? ''),
                'location' => $schedule->getLocation(),
                'conflict' => false,
                'conflictWith' => [],
                'duplicateUrl' => $this->generateUrl('admin_course_calendar_duplicate', ['id' => $schedule->getId()]),
                'duplicateToken' => $csrfTokenManager->getToken('calendar_duplicate_' . $schedule->getId())->getValue(),
                'incomplete' => $startTime === null || $duration === null || $duration <= 0,
                'layoutColumn' => 0,
                'layoutColumns' => 1,
                'url' => (clone $urls)->unsetAll()->setController(ScheduleCrudController::class)
                    ->setAction(Crud::PAGE_EDIT)->setEntityId($schedule->getId())->generateUrl(),
            ];
        }

        $conflictPairs = 0;
        foreach ($events as &$dayEvents) {
            $count = count($dayEvents);
            for ($i = 0; $i < $count; ++$i) {
                for ($j = $i + 1; $j < $count; ++$j) {
                    $a = $dayEvents[$i]; $b = $dayEvents[$j];
                    if (!$a['instructorId'] || $a['instructorId'] !== $b['instructorId']) { continue; }
                    if ($a['startMinutes'] === null || $b['startMinutes'] === null ||
                        !$a['durationMinutes'] || !$b['durationMinutes']) { continue; }
                    if ($a['startMinutes'] < $b['startMinutes'] + $b['durationMinutes'] &&
                        $b['startMinutes'] < $a['startMinutes'] + $a['durationMinutes']) {
                        $dayEvents[$i]['conflict'] = true;
                        $dayEvents[$j]['conflict'] = true;
                        $dayEvents[$i]['conflictWith'][] = $b['title'] . ' (' . ($b['time'] ?? '?') . '–' . ($b['end'] ?? '?') . ')';
                        $dayEvents[$j]['conflictWith'][] = $a['title'] . ' (' . ($a['time'] ?? '?') . '–' . ($a['end'] ?? '?') . ')';
                        ++$conflictPairs;
                    }
                }
            }
        }
        unset($dayEvents);

        // Place simultaneous appointments in separate lanes in the weekly view.
        // Layout is calculated for each day independently, preserving the existing
        // conflict detection and the edit links.
        foreach ($events as &$dayEvents) {
            $indexes = [];
            foreach ($dayEvents as $index => $event) {
                if ($event['startMinutes'] !== null && $event['startMinutes'] >= 360 &&
                    $event['startMinutes'] < 1380 && !$event['incomplete']) {
                    $indexes[] = $index;
                }
            }
            usort($indexes, static fn (int $a, int $b): int =>
                ($dayEvents[$a]['startMinutes'] <=> $dayEvents[$b]['startMinutes'])
                ?: ($dayEvents[$b]['durationMinutes'] <=> $dayEvents[$a]['durationMinutes'])
            );
            $groups = [];
            $activeGroup = [];
            $groupEnd = -1;
            foreach ($indexes as $index) {
                $event = $dayEvents[$index];
                $endMinute = $event['startMinutes'] + $event['durationMinutes'];
                if ($activeGroup !== [] && $event['startMinutes'] >= $groupEnd) {
                    $groups[] = $activeGroup;
                    $activeGroup = [];
                    $groupEnd = -1;
                }
                $activeGroup[] = $index;
                $groupEnd = max($groupEnd, $endMinute);
            }
            if ($activeGroup !== []) { $groups[] = $activeGroup; }
            foreach ($groups as $group) {
                $lanes = [];
                $assignment = [];
                foreach ($group as $index) {
                    $event = $dayEvents[$index];
                    $lane = 0;
                    while (isset($lanes[$lane]) && $lanes[$lane] > $event['startMinutes']) { ++$lane; }
                    $lanes[$lane] = $event['startMinutes'] + $event['durationMinutes'];
                    $assignment[$index] = $lane;
                }
                $laneCount = count($lanes);
                foreach ($assignment as $index => $lane) {
                    $dayEvents[$index]['layoutColumn'] = $lane;
                    $dayEvents[$index]['layoutColumns'] = $laneCount;
                }
            }
        }
        unset($dayEvents);

        $weeks = [];
        for ($day = $rangeStart; $day < $rangeEnd; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $weeks[$day->format('o-W')][] = [
                'date' => $key,
                'number' => $day->format('j'),
                'label' => $day->format('d.m.'),
                'current' => $day->format('Y-m') === $monthValue,
                'events' => $events[$key] ?? [],
            ];
        }
        $query = array_filter($filters, static fn ($v) => $v !== null);
        $calendarUrl = fn (array $args) => $this->generateUrl('admin_course_calendar', array_merge($query, $args));
        return $this->render('admin/calendar/index.html.twig', [
            'month' => $month,
            'selectedDay' => $selectedDay,
            'weeks' => $weeks,
            'view' => $view,
            'weekStart' => $weekStart,
            'options' => $options,
            'filters' => $filters,
            'conflictPairs' => $conflictPairs,
            'todayUrl' => $calendarUrl(['view' => 'day', 'month' => date('Y-m'), 'day' => date('Y-m-d')]),
            'monthUrl' => $calendarUrl(['view' => 'month', 'month' => $month->format('Y-m')]),
            'weekUrl' => $calendarUrl(['view' => 'week', 'month' => $month->format('Y-m'), 'week' => $weekStart->format('Y-m-d')]),
            'dayUrl' => $calendarUrl(['view' => 'day', 'month' => $selectedDay->format('Y-m'), 'day' => $selectedDay->format('Y-m-d')]),
            'prevUrl' => $view === 'day'
                ? $calendarUrl(['view' => 'day', 'month' => $selectedDay->modify('-1 day')->format('Y-m'), 'day' => $selectedDay->modify('-1 day')->format('Y-m-d')])
                : ($view === 'week'
                ? $calendarUrl(['view' => 'week', 'month' => $month->format('Y-m'), 'week' => $weekStart->modify('-7 days')->format('Y-m-d')])
                : $calendarUrl(['view' => 'month', 'month' => $month->modify('-1 month')->format('Y-m')])),
            'nextUrl' => $view === 'day'
                ? $calendarUrl(['view' => 'day', 'month' => $selectedDay->modify('+1 day')->format('Y-m'), 'day' => $selectedDay->modify('+1 day')->format('Y-m-d')])
                : ($view === 'week'
                ? $calendarUrl(['view' => 'week', 'month' => $month->format('Y-m'), 'week' => $weekStart->modify('+7 days')->format('Y-m-d')])
                : $calendarUrl(['view' => 'month', 'month' => $nextMonth->format('Y-m')])),
        ]);
    }
}
