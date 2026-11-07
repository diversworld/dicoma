<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Admin\DashboardController;
use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use App\Form\AccountPasswordType;
use App\Form\PersonContactType;
use App\Service\MembershipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    #[Route('/account', name: 'account_profile', methods: ['GET', 'POST'])]
    #[Route('/admin/account', name: 'admin_account_profile', defaults: DashboardController::ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $em, MembershipService $memberships, TranslatorInterface $translator): Response
    {
        $user = $this->account();
        $member = $user->getMember();
        $person = $user->getPerson();
        $form = null;
        if ($person !== null) {
            $form = $this->createForm(PersonContactType::class, $person, ['member' => $member]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $em->wrapInTransaction(function () use ($member, $memberships, $user, $form): void {
                    if ($member !== null) {
                        $member->setEmergencyContactName($form->get('emergencyContactName')->getData());
                        $member->setEmergencyContactPhone($form->get('emergencyContactPhone')->getData());
                        $member->setEmergencyContactRelation($form->get('emergencyContactRelation')->getData());
                        $memberships->record($member, 'contact_updated', $user->getUserIdentifier(), 'Eigene Kontakt- und Notfalldaten geändert. Konto-E-Mail und Verwaltungsdaten bleiben unverändert.');
                    }
                });
                $this->addFlash('success', $translator->trans('Kontaktdaten gespeichert.'));
                return $this->redirectToRoute($this->isBackend($request) ? 'admin_account_profile' : 'account_profile', [], Response::HTTP_SEE_OTHER);
            }
        } elseif ($request->isMethod('POST')) {
            throw $this->createAccessDeniedException('Diesem Konto ist keine Person zugeordnet.');
        }

        return $this->privatePage('account/profile.html.twig', [
            'backend' => $this->isBackend($request),
            'account' => $user,
            'form' => $form?->createView(),
        ], $form !== null && $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    #[Route('/account/password', name: 'account_password', methods: ['GET', 'POST'])]
    #[Route('/admin/account/password', name: 'admin_account_password', defaults: DashboardController::ROUTE_DEFAULTS, methods: ['GET', 'POST'])]
    public function password(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, Security $security): Response
    {
        $user = $this->account();
        $form = $this->createForm(AccountPasswordType::class)->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->wrapInTransaction(function () use ($em, $user, $hasher, $form): void {
                $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
                foreach ($em->getRepository(ResetPasswordRequest::class)->findBy(['user' => $user]) as $resetRequest) {
                    $em->remove($resetRequest);
                }
            });
            $security->logout(false);
            $this->addFlash('account_password_changed', 'Kennwort geändert. Bitte melden Sie sich erneut an.');
            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }

        return $this->privatePage('account/password.html.twig', [
            'backend' => $this->isBackend($request),
            'form' => $form->createView(),
        ], $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK);
    }

    private function account(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }
        return $user;
    }

    private function isBackend(Request $request): bool
    {
        return str_starts_with((string) $request->attributes->get('_route'), 'admin_account_');
    }

    private function privatePage(string $template, array $parameters, int $status): Response
    {
        $response = $this->render($template, $parameters, new Response(status: $status));
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
