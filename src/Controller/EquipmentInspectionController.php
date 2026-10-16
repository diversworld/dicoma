<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tank;
use App\Entity\User;
use App\Service\TankInspectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentInspectionController extends AbstractController
{
    #[Route('/equipment-manager/tanks/{id}/inspection', name: 'equipment_manager_tank_inspection', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $clubId = $this->clubId();
        $tank = $em->getRepository(Tank::class)->find($id);
        if (!$tank || $tank->getClub()?->getId() !== $clubId) {
            throw $this->createNotFoundException('Flasche nicht gefunden.');
        }
        return $this->render('equipment_manager/inspection.html.twig', ['tank' => $tank]);
    }

    #[Route('/equipment-manager/tanks/{id}/inspection', name: 'equipment_manager_tank_inspection_submit', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function submit(int $id, Request $request, TankInspectionService $service): Response
    {
        $clubId = $this->clubId();
        if (!$this->isCsrfTokenValid('equipment_inspection_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
        }
        try {
            $service->record(
                $id,
                $clubId,
                (string) $request->request->get('performed_at', ''),
                (string) $request->request->get('next_at', ''),
                (string) $request->request->get('reference', ''),
                $this->getUser()->getUserIdentifier()
            );
            $this->addFlash('success', 'Prüfdaten dokumentiert. Eine gesperrte Flasche bleibt gesperrt.');
            return $this->redirectToRoute('equipment_manager_tank_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
        } catch (\DomainException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('equipment_manager_tank_inspection', ['id' => $id], Response::HTTP_SEE_OTHER);
        }
    }

    private function clubId(): int
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getMember()?->getClub()?->getId() === null) {
            throw $this->createAccessDeniedException('Keine gültige Vereinszuordnung.');
        }
        return $user->getMember()->getClub()->getId();
    }
}
