<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CourseParticipant;
use App\Enum\CourseStatus;
use App\Service\CourseCompletionService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ParticipantPassActionController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly CourseCompletionService $completion, private readonly AdminUrlGenerator $urls) {}

    #[Route('/admin/course-participant/{id}/pass', name: 'admin_participant_pass_confirm', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function confirm(int $id, Request $request): Response
    {
        $participant = $this->em->getRepository(CourseParticipant::class)->find($id);
        if (!$participant instanceof CourseParticipant) {
            throw $this->createNotFoundException('Kursteilnehmer nicht gefunden.');
        }
        $course = $participant->getCourse();
        if ($course === null) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }
        $this->denyAccessUnlessGranted('COURSE_MANAGE', $course);
        $allowed = $course !== null && $course->getStatus() !== CourseStatus::COMPLETED && !$participant->hasPassed();
        $backUrl = (clone $this->urls)->unsetAll()->setController(CourseParticipantCrudController::class)->setAction(Crud::PAGE_DETAIL)->setEntityId($id)->generateUrl();

        if ($request->isMethod('POST')) {
            $token = $request->request->get('_token');
            if (!is_string($token) || !$this->isCsrfTokenValid('participant_pass_'.$id, $token)) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            if (!$allowed) {
                throw $this->createAccessDeniedException('Teilnehmer kann nicht abgeschlossen werden.');
            }
            try {
                $this->completion->pass($participant);
                $this->addFlash('success', 'Teilnehmer erfolgreich abgeschlossen.');
            } catch (\LogicException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
            return $this->redirect($backUrl);
        }
        return $this->render('admin/course/confirm_participant_pass.html.twig', [
            'participant' => $participant, 'course' => $course, 'allowed' => $allowed,
            'backUrl' => $backUrl, 'tokenId' => 'participant_pass_'.$id,
        ]);
    }
}
