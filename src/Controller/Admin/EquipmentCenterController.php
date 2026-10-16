<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\TankInspectionOverview;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class EquipmentCenterController extends AbstractController
{
    #[Route('/admin/equipment-center', name: 'admin_equipment_center', methods: ['GET'])]
    public function index(TankInspectionOverview $overview, AdminUrlGenerator $urls): Response
    {
        $tankUrl = (clone $urls)->unsetAll()->setController(TankCrudController::class)->setAction(Crud::PAGE_INDEX)->generateUrl();
        $checkUrl = (clone $urls)->unsetAll()->setController(TankCheckCrudController::class)->setAction(Crud::PAGE_INDEX)->generateUrl();
        return $this->render('admin/equipment/center.html.twig', [
            'overview' => $overview->getOverview(),
            'tankUrl' => $tankUrl,
            'checkUrl' => $checkUrl,
        ]);
    }
}
