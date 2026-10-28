<?php
declare(strict_types=1);
namespace App\Controller;

use App\Entity\User;
use App\Service\InstructorTaskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_INSTRUCTOR')]
final class InstructorPortalController extends AbstractController
{
    #[Route('/instructor-portal/courses', name: 'instructor_portal_courses', methods: ['GET'])]
    public function courses(\Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $member = $user instanceof User ? $user->getMember() : null;
        if ($member === null || $member->getClub() === null) {
            throw $this->createAccessDeniedException('Keine Vereinszuordnung.');
        }
        $courses = $em->getRepository(\App\Entity\Courses::class)->createQueryBuilder('c')
            ->leftJoin('c.schedule', 's')
            ->where('c.club = :club AND (c.leadInstructor = :member OR s.instructor = :member)')
            ->setParameter('club', $member->getClub())->setParameter('member', $member)
            ->distinct()->orderBy('c.id', 'DESC')->getQuery()->getResult();

        return $this->render('instructor_portal/courses.html.twig', ['courses' => $courses]);
    }

    #[Route('/instructor-portal', name: 'instructor_portal_dashboard', methods: ['GET'])]
    public function dashboard(Request $request, InstructorTaskService $tasks): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember()?->getId() === null) {
            throw $this->createAccessDeniedException('Keine Mitgliedszuordnung.');
        }
        $memberId = $user->getMember()->getId();
        $data = $tasks->overview(30, null, null, $memberId, true);
        return $this->render('instructor_portal/dashboard.html.twig', $data);
    }
    #[Route('/instructor-portal/tasks', name: 'instructor_portal_tasks', methods: ['GET'])]
    public function tasks(Request $request, InstructorTaskService $tasks): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember()?->getId() === null) {
            throw $this->createAccessDeniedException('Keine Mitgliedszuordnung.');
        }
        $courseId = filter_var($request->query->get('course'), FILTER_VALIDATE_INT) ?: null;
        return $this->render('instructor_portal/tasks.html.twig', $tasks->overview(
            30, null, $courseId, $user->getMember()->getId(), true
        ) + ['personalView'=>true]);
    }
}
