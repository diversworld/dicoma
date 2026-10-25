<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EquipmentFillCharge;
use App\Entity\User;
use App\Service\EquipmentCashGuard;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentFillBillingController extends AbstractController
{
    #[Route('/equipment-manager/fill-billing', name: 'equipment_manager_fill_billing', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('equipment_manager/fill_billing.html.twig', [
            'charges' => $em->getRepository(EquipmentFillCharge::class)->findBy(['club' => $this->clubId()], ['id' => 'DESC'], 200),
        ]);
    }

    #[Route('/equipment-manager/fill-billing/{id}/collect', name: 'equipment_manager_fill_collect', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function collect(int $id, Request $request, EntityManagerInterface $em, EquipmentCashGuard $guard): Response
    {
        $clubId = $this->clubId();
        if (!$this->isCsrfTokenValid('fill_collect_'.$id, $request->request->getString('_token'))) { throw $this->createAccessDeniedException(); }
        try {
            $em->wrapInTransaction(function () use ($em, $guard, $id, $clubId, $request): void {
                $charge = $em->find(EquipmentFillCharge::class, $id);
                if (!$charge || $charge->getClub()->getId() !== $clubId) { throw new \DomainException('Füllgebühr nicht gefunden.'); }
                $guard->lockAndAssertOpen($charge->getClub());
                $em->refresh($charge, LockMode::PESSIMISTIC_WRITE);
                $charge->collect($request->request->getString('method'), $this->getUser()->getUserIdentifier());
            });
            $this->addFlash('success', 'Füllgebühr bezahlt und im Kassenbuch erfasst.');
        } catch (\DomainException $e) { $this->addFlash('danger', $e->getMessage()); }
        return $this->redirectToRoute('equipment_manager_fill_billing', [], Response::HTTP_SEE_OTHER);
    }

    private function clubId(): int
    {
        $user = $this->getUser();
        $id = $user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
        if (!$id) { throw $this->createAccessDeniedException('Kein Verein zugeordnet.'); }
        return $id;
    }
}
