<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Entity\CourseParticipant;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use App\Service\CourseCompletionService;
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
final class CourseCompletionWizardController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TrainingProgressService $progress,
        private readonly CourseCompletionService $completion,
        private readonly AdminUrlGenerator $urls,
        private readonly CourseAuditLogger $audit,
    ) {}

    #[Route('/admin/course/{id}/completion-wizard', name: 'admin_course_completion_wizard', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function index(int $id): Response
    {
        $course = $this->loadCourse($id);
        $rows = [];
        foreach ($course->getParticipants() as $participant) {
            $progress = $this->progress->calculate($participant);
            $missing = [];
            foreach ($progress['requirements'] as $requirement) {
                if ($requirement['required'] && $requirement['missing'] > 0) {
                    $missing[] = sprintf('%s: %d', $requirement['name'], $requirement['missing']);
                }
            }
            $rows[] = [
                'participant' => $participant,
                'progress' => $progress,
                'missing' => $missing,
                'locked' => in_array($participant->getStatus(), [
                    CourseParticipantStatus::PASSED,
                    CourseParticipantStatus::FAILED,
                    CourseParticipantStatus::CANCELLED,
                ], true),
            ];
        }

        return $this->render('admin/course/completion_wizard.html.twig', [
            'course' => $course,
            'rows' => $rows,
            'backUrl' => $this->courseUrl($id),
            'courseCompleteUrl' => $this->generateUrl('admin_course_complete_confirm', ['id' => $id]),
            'canClose' => $course->canBeCompleted(),
            'isCompleted' => $course->getStatus() === CourseStatus::COMPLETED,
        ]);
    }

    #[Route('/admin/course/{id}/completion-wizard/{participantId}', name: 'admin_course_completion_wizard_decide', requirements: ['id' => '\\d+', 'participantId' => '\\d+'], methods: ['POST'])]
    public function decide(int $id, int $participantId, Request $request): Response
    {
        $course = $this->loadCourse($id);
        if ($course->getStatus() === CourseStatus::COMPLETED) {
            throw $this->createAccessDeniedException('Abgeschlossene Kurse koennen nicht bearbeitet werden.');
        }

        $participant = $this->em->getRepository(CourseParticipant::class)->find($participantId);
        if (!$participant instanceof CourseParticipant || $participant->getCourse()?->getId() !== $course->getId()) {
            throw $this->createNotFoundException('Kursteilnehmer nicht gefunden.');
        }
        $token = $request->request->get('_token');
        if (!is_string($token) || !$this->isCsrfTokenValid('course_participant_decision_'.$id.'_'.$participantId, $token)) {
            throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
        }
        if (in_array($participant->getStatus(), [CourseParticipantStatus::PASSED, CourseParticipantStatus::FAILED, CourseParticipantStatus::CANCELLED], true)) {
            $this->addFlash('warning', 'Dieser Teilnehmer hat bereits einen abschliessenden Status.');
            return $this->redirectToRoute('admin_course_completion_wizard', ['id' => $id]);
        }

        $decision = $request->request->get('decision');
        if (!is_string($decision) || !in_array($decision, ['passed', 'failed', 'cancelled'], true)) {
            throw $this->createAccessDeniedException('Ungueltige Abschlussentscheidung.');
        }

        try {
            if ($decision === 'passed') {
                // Only the existing domain service may award a qualification.
                $this->completion->pass($participant);
            } else {
                $this->em->wrapInTransaction(function () use ($participant, $course, $decision): void {
                    $before = $participant->getStatus()->value;
                    $next = $decision === 'failed' ? CourseParticipantStatus::FAILED : CourseParticipantStatus::CANCELLED;
                    $participant->setStatus($next);
                    $this->audit->record($course->getId(), $participant->getId(), 'participant_'.$decision, $before, $next->value);
                });
            }
            $this->addFlash('success', 'Die Abschlussentscheidung wurde gespeichert.');
        } catch (\LogicException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
        return $this->redirectToRoute('admin_course_completion_wizard', ['id' => $id]);
    }

    private function loadCourse(int $id): Courses
    {
        $course = $this->em->getRepository(Courses::class)->find($id);
        if (!$course instanceof Courses) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }
        return $course;
    }

    private function courseUrl(int $id): string
    {
        return (clone $this->urls)->unsetAll()->setController(CoursesCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)->setEntityId($id)->generateUrl();
    }
}
