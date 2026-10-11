<?php

declare(strict_types=1);

namespace App\Controller\Frontend;

use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\CourseParticipantRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Read-only frontend: no AdminContext and no user-controlled member identifier. */
#[IsGranted('ROLE_MEMBER')]
final class StudentAttendanceController extends AbstractController
{
    #[Route('/my-training/attendance', name: 'app_my_training_attendance', methods: ['GET'])]
    public function index(
        CourseParticipantRepository $participants,
        BookingRepository $bookings
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember()?->getId() === null) {
            throw $this->createAccessDeniedException('Kein Mitgliedskonto zugeordnet.');
        }

        $member = $user->getMember();
        $enrollments = $participants->findBy(['member' => $member]);
        $enrolledCourses = [];
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->getCourse();
            if ($course !== null && $course->getId() !== null) {
                $enrolledCourses[$course->getId()] = (string) $course;
            }
        }

        $rows = [];
        foreach ($bookings->findBy(['member' => $member]) as $booking) {
            $schedule = $booking->getSchedule();
            $course = $schedule?->getCourses();
            if ($schedule === null || $course === null || !isset($enrolledCourses[$course->getId()])) {
                continue;
            }

            $date = $schedule->getStartDate();
            $time = $schedule->getStartTime();
            $rows[] = [
                'course' => $enrolledCourses[$course->getId()],
                'title' => (string) $schedule,
                'date' => $date?->format('d.m.Y'),
                'time' => $time?->format('H:i'),
                'location' => $schedule->getLocation(),
                'attendance' => $booking->getAttendanceStatusLabel(),
                'sort' => ($date?->format('Y-m-d') ?? '9999-12-31') . ' ' . ($time?->format('H:i') ?? '00:00'),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));

        return $this->render('student/attendance.html.twig', ['rows' => $rows]);
    }
}
