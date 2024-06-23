<?php
// src/Controller/InstallController.php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use \Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class InstallController extends AbstractController
{
    private $em;
    private $passwordHasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher)
    {
        $this->em = $em;
        $this->passwordHasher = $passwordHasher;
    }

    #[Route('/install', name: 'app_install', methods: ['GET'])]
    public function install(Request $request): Response
    {
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $schemaTool->updateSchema($this->em->getMetadataFactory()->getAllMetadata());

        return $this->redirectToRoute('app_register_install');

    }

    #[Route('/save_db_config', name: 'save_db_config', methods: ['GET', 'POST'])]
    public function saveDbConfig(Request $request): Response
    {
        $dbHost = $request->request->get('db_host');
        $dbName = $request->request->get('db_name');
        $dbUser = $request->request->get('db_user');
        $dbPass = $request->request->get('db_pass');

        // Write the DB config to .env.local
        $filesystem = new Filesystem();
        try {
            $filesystem->dumpFile('.env.local', "DATABASE_URL=mysql://$dbUser:$dbPass@$dbHost/$dbName\n");
        } catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating your .env.local file at ".$exception->getPath();
            // or return error message to the user using flash messages or other technique
        }

        // Database details were saved, now redirect to home page (or any other page where you validate DB connection and create tables).
        return $this->redirectToRoute('home');
    }
}
