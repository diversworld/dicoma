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
    public function index(): Response
    {
        // Checking if the database is not configured
        if (!$this->container->has('doctrine')) {
            // Render the form asking for database parameters
            return $this->render('install/db_config.html.twig');

        } else {
            // Create the database connection
            $config = new \Doctrine\DBAL\Configuration();
            // Set up parameters
            $connectionParams = ['url' => $_ENV['DATABASE_URL']];
            $conn = DriverManager::getConnection($connectionParams, $config);
            // Check the database connectivity
            try {
                $conn->connect();
            } catch (\Exception $e) {
                // Render the form asking for database parameters as DB connection failed
                return $this->render('install/db_config.html.twig', ['error' => 'DB connection failed']);
            }
            // Database connectivity is okay, render your normal view or redirect to another controller action.
            // Or start your database schema creation process here (you might want to use doctrine:schema:create command or use the SchemaTool class if you use Doctrine ORM)
            // ...
        }
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }
}
