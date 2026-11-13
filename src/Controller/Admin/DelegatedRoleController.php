<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\RoleValidityType;
use App\Security\DelegatedRoles;
use App\Security\RoleValidity;
use App\Security\Voter\DelegatedRoleVoter;
use App\Service\AdminClubContext;
use App\Service\MembershipRoleContinuity;
use Doctrine\DBAL\LockMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/roles', defaults: DashboardController::ROUTE_DEFAULTS)]
final class DelegatedRoleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubContext,
        private readonly LoggerInterface $logger,
        private readonly MembershipRoleContinuity $continuity,
    ) {}

    #[Route('', name: 'admin_delegated_roles', methods: ['GET'])]
    public function index(AdminUrlGenerator $urls): Response
    {
        return $this->redirect($urls->unsetAll()->setController(DelegatedUserCrudController::class)->setAction(Crud::PAGE_INDEX)->generateUrl());
    }

    #[Route('/{id}/edit', name: 'admin_delegated_roles_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(User $user, Request $request): Response
    {
        $this->denyAccessUnlessGranted(DelegatedRoleVoter::MANAGE, $user);
        $audit = null;
        $response = $this->em->getConnection()->transactional(function () use ($user, $request, &$audit): Response {
            if ($request->isMethod('POST')) {
                $this->continuity->lockClub($user->getMember()?->getClub());
                if ($this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                    $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
                }
                $this->continuity->refresh($user);
                if ($user->getMember() !== null) { $this->continuity->refresh($user->getMember()); }
                $this->denyAccessUnlessGranted(DelegatedRoleVoter::MANAGE, $user);
            }
            $before = $user->getStoredRoles();
            $validityBefore = $user->getRoleValidity();
            $fingerprint = DelegatedRoles::fingerprint($user);
            $form = $this->createFormBuilder([
                'roles' => array_values(array_intersect($before, DelegatedRoles::ALLOWED)),
                'validity' => RoleValidity::formData($user, DelegatedRoles::ALLOWED),
            ], [
                'csrf_token_id' => 'delegated_roles_'.$user->getId(),
                'action' => $this->generateUrl('admin_delegated_roles_edit', ['id' => $user->getId()]),
            ])
                ->add('roles', ChoiceType::class, [
                    'label' => 'Vereinsfunktionen', 'choices' => DelegatedRoles::choices(),
                    'multiple' => true, 'expanded' => true, 'required' => false,
                ])
                ->add('validity', RoleValidityType::class, ['label' => 'Gültigkeitszeiträume', 'roles' => DelegatedRoles::choices()])
                ->add('snapshot', HiddenType::class, ['mapped' => false, 'data' => $fingerprint])
                ->getForm()->handleRequest($request);
            $conflict = false;
            if ($form->isSubmitted() && $form->isValid()) {
                if (!hash_equals($fingerprint, (string) $form->get('snapshot')->getData())) {
                    $form->addError(new FormError('Die Rollen wurden zwischenzeitlich geändert. Bitte neu laden.'));
                    $conflict = true;
                } else {
                    DelegatedRoles::replace($user, $form->getData()['roles']);
                    RoleValidity::replace($user, DelegatedRoles::ALLOWED, $form->getData()['roles'], $form->getData()['validity']);
                    $this->em->flush();
                    $audit = [
                        'actor_id' => $this->getUser()->getId(), 'target_id' => $user->getId(),
                        'club_id' => $this->clubContext->getClub()?->getId(), 'before' => $before, 'after' => $user->getStoredRoles(),
                        'validity_before' => $validityBefore, 'validity_after' => $user->getRoleValidity(),
                    ];
                    return $this->redirectToRoute('admin_delegated_roles', [], Response::HTTP_SEE_OTHER);
                }

            }
            return $this->render('admin/roles/edit.html.twig', [
                'account' => $user, 'form' => $form,
            ], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
        });
        if ($audit !== null) {
            $this->logger->notice('Delegated club roles changed', $audit);
            $this->addFlash('success', 'Vereinsfunktionen gespeichert.');
        }
        return $response;
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/{id}/validity', name: 'admin_role_validity', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function validity(User $user, Request $request): Response
    {
        $audit = null;
        $response = $this->em->getConnection()->transactional(function () use ($user, $request, &$audit): Response {
            if ($request->isMethod('POST')) {
                $this->continuity->lockClub($user->getMember()?->getClub());
                if ($this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                    $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
                }
                $this->continuity->refresh($user);
                if ($user->getMember() !== null) { $this->continuity->refresh($user->getMember()); }
            }
            $choices = array_filter(User::getAvailableRoles(), static fn (string $role): bool =>
                !in_array($role, [User::ROLE_USER, User::ROLE_MEMBER], true) && in_array($role, $user->getStoredRoles(), true));
            $roles = array_values($choices);
            $before = $user->getRoleValidity();
            $fingerprint = DelegatedRoles::fingerprint($user);
            $form = $this->createFormBuilder(['validity' => RoleValidity::formData($user, $roles)], [
                'csrf_token_id' => 'role_validity_'.$user->getId(),
                'action' => $this->generateUrl('admin_role_validity', ['id' => $user->getId()]),
            ])
                ->add('validity', RoleValidityType::class, ['label' => 'Gültigkeitszeiträume', 'roles' => $choices])
                ->add('snapshot', HiddenType::class, ['mapped' => false, 'data' => $fingerprint])
                ->getForm()->handleRequest($request);
            $conflict = false;
            if ($form->isSubmitted() && $form->isValid()) {
                if (!hash_equals($fingerprint, (string) $form->get('snapshot')->getData())) {
                    $form->addError(new FormError('Die Rollen wurden zwischenzeitlich geändert. Bitte neu laden.'));
                    $conflict = true;
                } else {
                    if ($user->getMember() !== null && !$user->getMember()->isActiveMember()
                        && $this->continuity->functions($user) !== []) {
                        $form->addError(new FormError('Funktionsrollen eines inaktiven Mitglieds können nicht geändert werden.'));
                        return $this->render('admin/roles/validity.html.twig', ['account' => $user, 'form' => $form], new Response(status: 409));
                    }
                    RoleValidity::replace($user, $roles, $roles, $form->getData()['validity']);
                    $this->em->flush();
                    $audit = ['actor_id' => $this->getUser()->getId(), 'target_id' => $user->getId(), 'before' => $before, 'after' => $user->getRoleValidity()];
                    return $this->redirectToRoute('admin_role_validity', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
                }
            }
            return $this->render('admin/roles/validity.html.twig', ['account' => $user, 'form' => $form], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
        });
        if ($audit !== null) {
            $this->logger->notice('User role validity changed', $audit);
            $this->addFlash('success', 'Rollengültigkeiten gespeichert.');
        }
        return $response;
    }
}
