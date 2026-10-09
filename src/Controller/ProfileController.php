<?php

namespace App\Controller;

use App\Entity\Member;
use App\Repository\MemberRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/profile', name: 'app_profile')]
    public function index(MemberRepository $memberRepository): Response
    {
        // Get the currently logged in user
        $user = $this->security->getUser();
        $member = $user->getMember();

        /*if ($member === null) {
            // handle the case when there's no Member for the current user
            return $this->render('profile/index.html.twig', [
                'user' => $user,
            ]);
        }*/

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'member' => $member
        ]);
    }
}
