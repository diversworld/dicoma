<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class AdminClubController extends AbstractController
{
    #[Route('/admin/settings/club', name: 'admin_club_select', defaults: DashboardController::ROUTE_DEFAULTS, methods: ['POST'])]
    public function select(): Response
    {
        throw $this->createNotFoundException();
    }
}
