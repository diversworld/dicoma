<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Security\Voter\CourseManagementVoter;
use App\Enum\CourseStatus;
use App\Repository\ScheduleRepository;
use App\Service\CourseScheduleGenerator;
use App\Service\CourseTrainingPlanService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CoursePlanActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CourseTrainingPlanService $planService,
        private readonly CourseScheduleGenerator $scheduleGenerator,
        private readonly ScheduleRepository $scheduleRepository,
        private readonly AdminUrlGenerator $urls,
    ) {}

    #[Route('/admin/course/{id}/import-training-plan', name: 'admin_course_import_confirm', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function import(int $id, Request $request): Response
    {
        $course = $this->loadEditableCourse($id);
        $qualification = $course->getQualification();
        $entries = [];
        if ($qualification !== null) {
            foreach ($qualification->getTrainingUnits() as $unit) {
                $type = $unit->getTrainingUnitType();
                if ($type === null) { continue; }
                $already = false;
                foreach ($course->getTrainingUnits() as $existing) {
                    if ($existing->getTrainingUnitType()?->getId() === $type->getId()) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $entries[] = ['name' => (string) $type, 'count' => $unit->getRequiredSessions()];
                }
            }
        }
        if ($request->isMethod('POST')) {
            $this->verifyToken($request, 'course_import_'.$id);
            if ($qualification === null || $entries === []) {
                $this->addFlash('warning', 'Keine neuen Ausbildungsanforderungen vorhanden.');
                return $this->redirectToCourse($id);
            }
            try {
                $created = $this->planService->importFromQualification($course);
                $this->em->flush();
                $this->addFlash('success', sprintf('%d Ausbildungsanforderungen uebernommen.', $created));
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
            return $this->redirectToCourse($id);
        }
        return $this->render('admin/course/confirm_plan_action.html.twig', [
            'course' => $course, 'mode' => 'import', 'rows' => $entries,
            'submitUrl' => $this->generateUrl('admin_course_import_confirm', ['id' => $id]),
            'tokenId' => 'course_import_'.$id, 'backUrl' => $this->courseUrl($id),
        ]);
    }

    #[Route('/admin/course/{id}/generate-schedules', name: 'admin_course_generate_confirm', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function generate(int $id, Request $request): Response
    {
        $course = $this->loadEditableCourse($id);
        $rows = [];
        $missing = 0;
        foreach ($course->getTrainingUnits() as $unit) {
            $type = $unit->getTrainingUnitType();
            if ($type === null) { continue; }
            $target = $unit->getRequiredSessions();
            $existing = $this->scheduleRepository->countForCourseAndTrainingUnitType($course, $type);
            $difference = max(0, $target - $existing);
            $missing += $difference;
            $rows[] = ['name' => (string) $type, 'target' => $target,
                'existing' => $existing, 'missing' => $difference,
                'excess' => max(0, $existing - $target)];
        }
        if ($request->isMethod('POST')) {
            $this->verifyToken($request, 'course_generate_'.$id);
            if ($missing === 0) {
                $this->addFlash('info', 'Es sind keine weiteren Termine erforderlich.');
                return $this->redirectToCourse($id);
            }
            try {
                $created = $this->scheduleGenerator->generate($course);
                $this->addFlash('success', sprintf('%d Kurstermine erzeugt.', $created));
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
            return $this->redirectToCourse($id);
        }
        return $this->render('admin/course/confirm_plan_action.html.twig', [
            'course' => $course, 'mode' => 'generate', 'rows' => $rows,
            'missing' => $missing,
            'submitUrl' => $this->generateUrl('admin_course_generate_confirm', ['id' => $id]),
            'tokenId' => 'course_generate_'.$id, 'backUrl' => $this->courseUrl($id),
        ]);
    }

    private function loadEditableCourse(int $id): Courses
    {
        $course = $this->em->getRepository(Courses::class)->find($id);
        if (!$course instanceof Courses) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        if ($course->getStatus() === CourseStatus::COMPLETED) {
            throw $this->createAccessDeniedException('Abgeschlossene Kurse duerfen nicht veraendert werden.');
        }
        return $course;
    }

    private function verifyToken(Request $request, string $id): void
    {
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid($id, $token)) {
            throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
        }
    }

    private function courseUrl(int $id): string
    {
        return (clone $this->urls)->unsetAll()
            ->setController(CoursesCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)->setEntityId($id)->generateUrl();
    }

    private function redirectToCourse(int $id): RedirectResponse
    {
        return $this->redirect($this->courseUrl($id));
    }
}
