<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\EquipmentLoanService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class EquipmentLoanActionController extends AbstractController
{
    public function __construct(
        private readonly EquipmentLoanService $loans,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    #[Route('/admin/equipment-loan/{id}/{action}', name: 'admin_equipment_loan_action', requirements: ['id' => '\\d+', 'action' => 'issue|return|cancel'], methods: ['POST'])]
    public function change(int $id, string $action, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('equipment_loan_'.$action.'_'.$id, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungueltiges CSRF-Token.');
        }
        try {
            match ($action) {
                'issue' => $this->loans->issue($id),
                'return' => $this->loans->returnItem(
                    $id,
                    (string) $request->request->get('condition', ''),
                    $request->request->get('notes') === null ? null : (string) $request->request->get('notes')
                ),
                'cancel' => $this->loans->cancel($id),
            };
            $this->addFlash('success', 'Vorgang gespeichert.');
        } catch (\DomainException|\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirect((clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(EquipmentReservationCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($id)
            ->generateUrl());
    }
}
