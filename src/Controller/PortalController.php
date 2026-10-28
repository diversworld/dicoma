<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\PortalNavigation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class PortalController extends AbstractController
{
    #[Route('/portal/{_locale}', name: 'app_portal', requirements: ['_locale' => 'de|en|fr|es'], defaults: ['_locale' => 'de'], methods: ['GET'])]
    public function index(PortalNavigation $navigation): Response
    {
        $response = $this->render('portal/index.html.twig', ['portals' => $navigation->getLinks()]);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
