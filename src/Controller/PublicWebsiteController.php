<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\WebsitePublication;
use App\Service\PublicWebsite;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PublicWebsiteController extends AbstractController
{
    #[Route('/articles', name: 'website_articles', methods: ['GET'])]
    #[Route('/events', name: 'website_events', methods: ['GET'])]
    public function index(\Symfony\Component\HttpFoundation\Request $request, PublicWebsite $website): Response
    {
        $kind = $request->attributes->get('_route') === 'website_events' ? 'event' : 'article';
        return $this->render('website/index.html.twig', ['kind' => $kind, 'publications' => $website->publications($kind, $kind === 'event')]);
    }

    #[Route('/news/{id}', name: 'website_publication', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, PublicWebsite $website, EntityManagerInterface $em): Response
    {
        $publication = $em->find(WebsitePublication::class, $id);
        if ($publication === null || !$website->visible($publication)) { throw $this->createNotFoundException(); }
        return $this->render('website/detail.html.twig', ['publication' => $publication]);
    }
}
