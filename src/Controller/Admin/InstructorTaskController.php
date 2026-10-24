<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\InstructorTaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class InstructorTaskController extends AbstractController
{
    #[Route('/admin/instructor/tasks', name: 'admin_instructor_tasks', methods: ['GET'], defaults: ['routeCreatedByEasyAdmin' => true, 'dashboardControllerFqcn' => DashboardController::class])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function index(Request $request, InstructorTaskService $tasks): Response
    {
        return $this->render('admin/instructor/tasks.html.twig', $tasks->overview(
            30,
            $this->positiveId($request->query->get('club')),
            $this->positiveId($request->query->get('course')),
            $this->positiveId($request->query->get('instructor'))
        ) + ['personalView' => false]);
    }

    #[Route('/my-training/tasks', name: 'instructor_my_tasks', methods: ['GET'])]
    #[IsGranted('ROLE_INSTRUCTOR')]
    public function myTasks(): Response
    {
        return $this->redirectToRoute('instructor_portal_tasks', [], Response::HTTP_SEE_OTHER);
    }

    private function positiveId(mixed $value): ?int
    {
        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            return null;
        }
        $id = (int) $value;
        return $id > 0 ? $id : null;
    }
}
