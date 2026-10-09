<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\CourseParticipantRepository;
use App\Service\TrainingProgressService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TrainingProgressController extends AbstractController
{
    #[Route('/admin/training-progress/{id}', name: 'admin_training_progress', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, CourseParticipantRepository $participants, TrainingProgressService $progress): Response
    {
        $participant = $participants->find($id);
        if ($participant === null) {
            throw $this->createNotFoundException('Kursteilnehmer nicht gefunden.');
        }

        return $this->render('admin/training_progress/show.html.twig', [
            'participant' => $participant,
            'progress' => $progress->calculate($participant),
        ]);
    }
}
