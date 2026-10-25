<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EquipmentBooking;
use App\Entity\User;
use App\Service\EquipmentBookingWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentBookingWorkflowController extends AbstractController
{
    #[Route('/equipment-manager/loans/{id}/{operation}', name: 'equipment_manager_loan_workflow', requirements: ['id' => '\d+', 'operation' => 'approve|close|confirm-fee'], methods: ['POST'])]
    public function change(int $id, string $operation, Request $request, EntityManagerInterface $em, EquipmentBookingWorkflow $workflow): Response
    {
        $user = $this->getUser();
        $clubId = $user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if (!$clubId || !$this->isCsrfTokenValid('loan_workflow_'.$operation.'_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        $booking = $em->find(EquipmentBooking::class, $id);
        if (!$booking || $booking->getMember()?->getClub()?->getId() !== $clubId) { throw $this->createNotFoundException(); }
        try {
            match ($operation) {
                'approve' => $workflow->approve($id, $clubId, $user->getUserIdentifier()),
                'close' => $workflow->close($id, $clubId, $user->getUserIdentifier()),
                'confirm-fee' => $workflow->confirmLegacyFee($id, $clubId, $request->request->getString('amount'), $user->getUserIdentifier()),
            };
            $this->addFlash('success', match ($operation) { 'approve' => 'Buchung genehmigt.', 'close' => 'Verleihvorgang abgeschlossen.', 'confirm-fee' => 'Vereinbarter Altpreis festgeschrieben.' });
        } catch (\DomainException $e) { $this->addFlash('danger', $e->getMessage()); }
        return $this->redirectToRoute('equipment_manager_loans', ['status' => 'all', 'checkout' => $id], Response::HTTP_SEE_OTHER);
    }
}
