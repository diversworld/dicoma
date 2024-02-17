<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/profile', name: 'app_profile')]
    public function index(): Response
    {
        // Get the currently logged in user
        $user = $this->security->getUser();

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }
}
