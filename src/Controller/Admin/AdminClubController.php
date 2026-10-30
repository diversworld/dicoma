<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Service\AdminClubContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class AdminClubController extends AbstractController
{
    #[Route('/admin/settings/club', name: 'admin_club_select', defaults: DashboardController::ROUTE_DEFAULTS, methods: ['POST'])]
    public function select(Request $request, AdminClubContext $context): Response
    {
        $form = $context->selectionForm()->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('admin/division/select_club.html.twig', ['form' => $form->createView()], new Response(status: 422));
        }

        $club = $form->get('club')->getData();
        if (!$club instanceof Club) {
            throw $this->createAccessDeniedException('Verein erforderlich.');
        }
        $context->select($club);

        return $this->redirectToRoute('admin_settings_sports_configuration', [], Response::HTTP_SEE_OTHER);
    }
}
