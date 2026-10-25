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
    public function index(\App\Service\PublicWebsite $website): Response
    {

        return $this->render('home/index.html.twig', [
            'articles' => array_slice($website->publications('article'), 0, 6),
            'events' => array_slice($website->publications('event', true), 0, 6),
        ]);
    }
}
