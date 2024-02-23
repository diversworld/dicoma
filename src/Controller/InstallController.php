<?php
// src/Controller/InstallController.php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
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
}
