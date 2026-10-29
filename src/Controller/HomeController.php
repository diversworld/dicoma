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
    public function index(\App\Service\PublicWebsite $website, \App\Service\ClubEventBooking $booking): Response
    {

        $user = $this->getUser();
        $club = $website->club();
        $response = $this->render('home/index.html.twig', [
            'articles' => array_slice($website->publications('article'), 0, 6),
            'events' => array_slice($website->publications('event', true), 0, 6),
            'bookableEvents' => $club === null ? [] : $booking->visibleUpcoming($club, $user instanceof \App\Entity\User ? $user : null, 6),
        ]);
        if ($user !== null) { $response->setPrivate()->headers->addCacheControlDirective('no-store'); }
        return $response;
    }
}
