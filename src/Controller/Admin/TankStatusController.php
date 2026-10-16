<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Tank;
use App\Entity\TankConditionHistory;
use App\Service\TankStatusWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class TankStatusController extends AbstractController
{
    #[Route('/admin/{_locale}/tank-status/{id}', name: 'admin_tank_status', requirements: ['id' => '\\d+', '_locale' => 'de|en|fr|es'], defaults: ['_locale' => 'de'], methods: ['GET', 'POST'])]
    public function show(int $id, Request $request, EntityManagerInterface $em, TankStatusWorkflow $workflow): Response
    {
        $tank = $em->find(Tank::class, $id);
        if (!$tank) {
            throw $this->createNotFoundException('Tauchflasche nicht gefunden.');
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tank_status_'.$id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            try {
                $user = $this->getUser();
                $actor = $user?->getUserIdentifier() ?? 'unbekannt';
                $workflow->apply($id, (string) $request->request->get('operation', ''), (string) $request->request->get('reason', ''), $actor);
                $this->addFlash('success', 'Statusänderung protokolliert.');
            } catch (\DomainException $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
            return $this->redirectToRoute('admin_tank_status', ['id' => $id, '_locale' => $request->attributes->get('_locale', 'de')], Response::HTTP_SEE_OTHER);
        }
        $history = $em->getRepository(TankConditionHistory::class)->findBy(['tank' => $tank], ['occurredAt' => 'DESC', 'id' => 'DESC'], 100);
        return $this->render('admin/tank/status.html.twig', ['tank' => $tank, 'history' => $history]);
    }
}
