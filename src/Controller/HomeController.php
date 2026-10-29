<?php

namespace App\Controller;

use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, \App\Service\PublicWebsite $website, \App\Service\ClubEventBooking $booking, \App\Service\WebsitePagination $pagination): Response
    {

        $user = $this->getUser();
        $limits = $pagination->limits();
        $response = $this->render('home/index.html.twig', [
            'articlesPage' => $pagination->page($website->publications('article'), $limits['articles'], $request, 'artikel_seite'),
            'eventsPage' => $pagination->page($website->upcomingEvents($booking, $user instanceof \App\Entity\User ? $user : null), $limits['events'], $request, 'veranstaltungen_seite'),
        ]);
        if ($user !== null) { $response->setPrivate()->headers->addCacheControlDirective('no-store'); }
        return $response;
    }
}
