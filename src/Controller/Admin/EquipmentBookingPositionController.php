<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\EquipmentBookingPositionService;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class EquipmentBookingPositionController extends AbstractController
{
    #[Route('/admin/equipment-booking/add-position', name: 'admin_equipment_booking_add_position', methods: ['POST'])]
    public function add(Request $request, EquipmentBookingPositionService $service, AdminUrlGenerator $urls): Response
    {
        $bookingId = filter_var($request->request->get('booking_id'), FILTER_VALIDATE_INT);
        $itemId = filter_var($request->request->get('equipment_item_id'), FILTER_VALIDATE_INT);
        $type = (string) $request->request->get('asset_type', 'equipment');
        if (!$bookingId || !$itemId) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('equipment_booking_add_'.$bookingId, (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException('Ungueltiger CSRF-Token.');
        }
        try {
            $service->add($bookingId, $itemId, $type);
            $this->addFlash('success', 'Ausrüstungsposition hinzugefügt.');
        } catch (\DomainException $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }
        return $this->redirect((clone $urls)->unsetAll()
            ->setController(EquipmentBookingCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)->setEntityId($bookingId)->generateUrl(), 303);
    }
}
