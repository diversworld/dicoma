<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\CourseParticipantRepository;
use App\Service\TrainingProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_MEMBER')]
final class StudentDashboardController extends AbstractController
{
    #[Route('/my-training/progress', name: 'app_my_training_progress', methods: ['GET'])]
    public function index(CourseParticipantRepository $participants, TrainingProgressService $progress): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember()?->getId() === null) {
            throw $this->createAccessDeniedException('Kein Mitgliedskonto zugeordnet.');
        }

        $member = $user->getMember();
        $enrollments = $participants->findBy(['member' => $member], ['id' => 'DESC']);
        $courses = [];
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->getCourse();
            if ($course === null) {
                continue;
            }
            $sessions = [];
            foreach ($course->getSchedule() as $schedule) {
                if ($schedule->getStartDate() === null) {
                    continue;
                }
                $sessions[] = [
                    'title' => $schedule->getTitle(),
                    'date' => $schedule->getStartDate()->format('d.m.Y'),
                    'sort' => $schedule->getStartDate()->format('Y-m-d') . ' ' . ($schedule->getStartTime()?->format('H:i') ?? '00:00'),
                    'time' => $schedule->getStartTime()?->format('H:i'),
                    'location' => $schedule->getLocation(),
                ];
            }
            usort($sessions, static fn (array $a, array $b): int => strcmp($a['sort'], $b['sort']));
            $courses[] = [
                'name' => (string) $course,
                'status' => $enrollment->getStatus()->label(),
                'progress' => $progress->calculate($enrollment),
                'sessions' => $sessions,
            ];
        }

        return $this->render('student/training_progress.html.twig', ['courses' => $courses]);
    }
}
