<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Security\Voter\CourseManagementVoter;
use App\Enum\CourseStatus;
use App\Service\TrainingProgressService;
use App\Service\CourseAuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class CourseStatusActionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $urls,
        private readonly TrainingProgressService $trainingProgress,
        private readonly CourseAuditLogger $audit,
    ) {}

    #[Route('/admin/course/{id}/complete', name: 'admin_course_complete_confirm', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function complete(int $id, Request $request): Response
    {
        $course = $this->loadCourse($id);
        $alreadyCompleted = $course->getStatus() === CourseStatus::COMPLETED;
        $statusesFinal = $course->canBeCompleted();
        $participantCount = 0;
        $missingEvidence = 0;
        foreach ($course->getParticipants() as $participant) {
            ++$participantCount;
            if (!$this->trainingProgress->calculate($participant)['complete']) {
                ++$missingEvidence;
            }
        }
        $hasRequirements = !$course->getTrainingUnits()->isEmpty();
        if ($request->isMethod('POST')) {
            $this->verifyToken($request, 'course_complete_'.$id);
            if ($alreadyCompleted) {
                $this->addFlash('info', 'Der Kurs ist bereits abgeschlossen.');
            } elseif (!$course->canBeCompleted()) {
                $this->addFlash('warning', 'Der Kurs kann erst abgeschlossen werden, wenn alle Teilnehmer einen abschliessenden Status besitzen.');
            } else {
                $this->em->wrapInTransaction(function () use ($course, $id): void {
                    $before = $course->getStatus()->value;
                    $course->setStatus(CourseStatus::COMPLETED);
                    $this->audit->record($id, null, 'course_completed', $before, CourseStatus::COMPLETED->value);
                });
                $this->addFlash('success', 'Der Kurs wurde abgeschlossen.');
            }
            return $this->redirect($this->courseUrl($id));
        }
        return $this->render('admin/course/confirm_status_action.html.twig', [
            'course' => $course, 'mode' => 'complete', 'canSubmit' => !$alreadyCompleted && $statusesFinal,
            'statusesFinal' => $statusesFinal, 'hasRequirements' => $hasRequirements,
            'participantCount' => $participantCount, 'missingEvidence' => $missingEvidence,
            'submitUrl' => $this->generateUrl('admin_course_complete_confirm', ['id' => $id]),
            'tokenId' => 'course_complete_'.$id, 'backUrl' => $this->courseUrl($id),
        ]);
    }

    #[Route('/admin/course/{id}/reopen', name: 'admin_course_reopen_confirm', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function reopen(int $id, Request $request): Response
    {
        $course = $this->loadCourse($id);
        $completed = $course->getStatus() === CourseStatus::COMPLETED;
        if ($request->isMethod('POST')) {
            $this->verifyToken($request, 'course_reopen_'.$id);
            $reason = $request->request->get('reason');
            if (!$completed) {
                $this->addFlash('info', 'Der Kurs ist nicht abgeschlossen.');
            } elseif (!is_string($reason) || mb_strlen(trim($reason)) < 3) {
                $this->addFlash('warning', 'Bitte eine Begruendung mit mindestens drei Zeichen angeben.');
                return $this->redirectToRoute('admin_course_reopen_confirm', ['id' => $id]);
            } else {
                // Begründung wird zusammen mit dem Statuswechsel protokolliert.
                $this->em->wrapInTransaction(function () use ($course, $id, $reason): void {
                    $before = $course->getStatus()->value;
                    $course->setStatus(CourseStatus::RUNNING);
                    $this->audit->record($id, null, 'course_reopened', $before, CourseStatus::RUNNING->value, trim($reason));
                });
                $this->addFlash('success', 'Der Kurs wurde zur Korrektur wieder geoeffnet.');
            }
            return $this->redirect($this->courseUrl($id));
        }
        return $this->render('admin/course/confirm_status_action.html.twig', [
            'course' => $course, 'mode' => 'reopen', 'canSubmit' => $completed,
            'submitUrl' => $this->generateUrl('admin_course_reopen_confirm', ['id' => $id]),
            'tokenId' => 'course_reopen_'.$id, 'backUrl' => $this->courseUrl($id),
        ]);
    }

    private function loadCourse(int $id): Courses
    {
        $course = $this->em->getRepository(Courses::class)->find($id);
        if (!$course instanceof Courses) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }
        $this->denyAccessUnlessGranted(CourseManagementVoter::MANAGE, $course);
        return $course;
    }

    private function verifyToken(Request $request, string $tokenId): void
    {
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid($tokenId, $token)) {
            throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
        }
    }

    private function courseUrl(int $id): string
    {
        return (clone $this->urls)->unsetAll()->setController(CoursesCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)->setEntityId($id)->generateUrl();
    }
}
