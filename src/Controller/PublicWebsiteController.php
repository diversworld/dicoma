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
    #[Route('/club', name: 'website_club', methods: ['GET'])]
    #[Route('/contact', name: 'website_contact', methods: ['GET'])]
    public function club(PublicWebsite $website): Response
    {
        $club = $website->club();
        if ($club === null || !$club->isActive()) {
            throw $this->createNotFoundException('Kein öffentlicher Verein konfiguriert.');
        }
        $url = $club->getWebsite();
        $safeUrl = $url !== null && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);

        return $this->render('website/club.html.twig', [
            'club' => $club,
            'websiteUrl' => $safeUrl ? $url : null,
        ]);
    }

    #[Route('/articles', name: 'website_articles', methods: ['GET'])]
    #[Route('/events', name: 'website_events', methods: ['GET'])]
    public function index(\Symfony\Component\HttpFoundation\Request $request, PublicWebsite $website, \App\Service\ClubEventBooking $booking, \App\Service\WebsitePagination $pagination): Response
    {
        $kind = $request->attributes->get('_route') === 'website_events' ? 'event' : 'article';
        $user = $this->getUser();
        $limits = $pagination->limits();
        $items = $kind === 'article' ? $website->publications('article') : $website->upcomingEvents($booking, $user instanceof \App\Entity\User ? $user : null);
        $response = $this->render('website/index.html.twig', [
            'kind' => $kind,
            'contentPage' => $pagination->page($items, $limits[$kind === 'article' ? 'articles' : 'events'], $request, 'seite'),
        ]);
        if ($kind === 'event' && $this->getUser() !== null) {
            $response->setPrivate()->headers->addCacheControlDirective('no-store');
        }
        return $response;
    }

    #[Route('/news/{id}', name: 'website_publication', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, PublicWebsite $website, EntityManagerInterface $em): Response
    {
        $publication = $em->find(WebsitePublication::class, $id);
        if ($publication === null || !$website->visible($publication)) { throw $this->createNotFoundException(); }
        return $this->render('website/detail.html.twig', ['publication' => $publication]);
    }

    #[Route('/news/{id}/image', name: 'website_publication_image', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function image(int $id, PublicWebsite $website, EntityManagerInterface $em, \App\Service\MediaFileStorage $storage): Response
    {
        $publication = $em->find(WebsitePublication::class, $id);
        $image = $publication?->getImage();
        if ($publication === null || !$website->visible($publication) || $image === null || !$image->isImage()
            || $image->getClub()->getId() !== $publication->getClub()->getId()) { throw $this->createNotFoundException(); }
        return $storage->response($image, \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
