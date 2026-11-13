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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
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
    public function index(): Response
    {
        $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');

        return $this->render('admin/roles/functions.html.twig', [
            'functions' => self::functionChoices(),
        ]);
    }

    /** @return array<string, string> */
    public static function functionChoices(): array
    {
        return array_filter(User::getAvailableRoles(), static fn (string $role): bool =>
            !in_array($role, [User::ROLE_USER, User::ROLE_MEMBER], true));
    }

    #[Route('/functions/{role}/members', name: 'admin_function_members', requirements: ['role' => 'ROLE_[A-Z_]+'], methods: ['GET'])]
    public function members(string $role, AdminUrlGenerator $urls): Response
    {
        $this->functionLabel($role);
        $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');

        return $this->redirect($urls->unsetAll()->setController(DelegatedUserCrudController::class)
            ->setAction(Crud::PAGE_INDEX)->set('function', $role)->generateUrl());
    }

    #[Route('/functions/{role}/members/{id}/edit', name: 'admin_function_assignment_edit', requirements: ['role' => 'ROLE_[A-Z_]+', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function editAssignment(string $role, User $user, Request $request): Response
    {
        $label = $this->functionLabel($role);
        if (!in_array($role, DelegatedRoles::ALLOWED, true)) {
            throw $this->createAccessDeniedException('Geschützte Rollen können hier nicht vergeben werden.');
        }
        $this->denyAccessUnlessGranted(DelegatedRoleVoter::MANAGE, $user);
        $audit = null;
        $response = $this->em->getConnection()->transactional(function () use ($role, $label, $user, $request, &$audit): Response {
            if ($request->isMethod('POST')) {
                $this->continuity->lockClub($user->getMember()?->getClub());
                if ($this->em->getConnection()->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                    $this->em->lock($user, LockMode::PESSIMISTIC_WRITE);
                }
                $this->continuity->refresh($user);
                if ($user->getMember() !== null) {
                    $this->continuity->refresh($user->getMember());
                }
                $this->denyAccessUnlessGranted(DelegatedRoleVoter::MANAGE, $user);
            }
            $before = $user->getStoredRoles();
            $validityBefore = $user->getRoleValidity();
            $fingerprint = DelegatedRoles::fingerprint($user);
            $builder = $this->createFormBuilder([
                'assigned' => in_array($role, $before, true),
                'validity' => RoleValidity::formData($user, [$role]),
            ], [
                'csrf_token_id' => 'function_assignment_'.$role.'_'.$user->getId(),
                'action' => $this->generateUrl('admin_function_assignment_edit', ['role' => $role, 'id' => $user->getId()]),
            ])
                ->add('assigned', CheckboxType::class, ['label' => 'Funktion zugeordnet', 'required' => false])
                ->add('validity', RoleValidityType::class, ['label' => 'Gültigkeitszeitraum', 'roles' => [$label => $role]])
                ->add('snapshot', HiddenType::class, ['mapped' => false, 'data' => $fingerprint]);
            $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($role): void {
                $data = $event->getData();
                if (!is_array($data) || (array_key_exists('assigned', $data) && $data['assigned'] !== null) || !isset($data['validity'][$role])
                    || !is_array($data['validity'][$role])) {
                    return;
                }
                foreach (['startsAt', 'endsAt'] as $date) {
                    if (array_key_exists($date, $data['validity'][$role])) {
                        $data['validity'][$role][$date] = '';
                    }
                }
                $event->setData($data);
            });
            $form = $builder->getForm()->handleRequest($request);
            $conflict = false;
            if ($form->isSubmitted() && $form->isValid()) {
                if (!hash_equals($fingerprint, (string) $form->get('snapshot')->getData())) {
                    $form->addError(new FormError('Die Rollen wurden zwischenzeitlich geändert. Bitte neu laden.'));
                    $conflict = true;
                } else {
                    $assigned = $form->getData()['assigned'];
                    if ($assigned) {
                        $user->addRole($role);
                    } else {
                        $user->removeRole($role);
                    }
                    RoleValidity::replace($user, [$role], $assigned ? [$role] : [], $form->getData()['validity']);
                    $this->em->flush();
                    $audit = [
                        'actor_id' => $this->getUser()->getId(), 'target_id' => $user->getId(),
                        'club_id' => $this->clubContext->getClub()?->getId(), 'function' => $role,
                        'before' => $before, 'after' => $user->getStoredRoles(),
                        'validity_before' => $validityBefore, 'validity_after' => $user->getRoleValidity(),
                    ];
                    return $this->redirectToRoute('admin_function_members', ['role' => $role], Response::HTTP_SEE_OTHER);
                }
            }
            return $this->render('admin/roles/assignment.html.twig', [
                'account' => $user, 'role' => $role, 'label' => $label, 'form' => $form,
            ], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
        });
        if ($audit !== null) {
            $this->logger->notice('Delegated club role assignment changed', $audit);
            $this->addFlash('success', 'Funktionszuordnung gespeichert.');
        }
        return $response;
    }

    private function functionLabel(string $role): string
    {
        $label = array_search($role, self::functionChoices(), true);
        if ($label === false) {
            throw $this->createNotFoundException('Unbekannte Vereinsfunktion.');
        }
        return $label;
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
            $builder = $this->createFormBuilder([
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
                ->add('snapshot', HiddenType::class, ['mapped' => false, 'data' => $fingerprint]);
            $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
                $data = $event->getData();
                if (!is_array($data) || !is_array($data['validity'] ?? null)) {
                    return;
                }
                $selected = $data['roles'] ?? [];
                if (!is_array($selected)) {
                    return;
                }
                foreach (DelegatedRoles::ALLOWED as $role) {
                    if (!in_array($role, $selected, true) && isset($data['validity'][$role])
                        && is_array($data['validity'][$role])) {
                        foreach (['startsAt', 'endsAt'] as $date) {
                            if (array_key_exists($date, $data['validity'][$role])) {
                                $data['validity'][$role][$date] = '';
                            }
                        }
                    }
                }
                $event->setData($data);
            });
            $form = $builder->getForm()->handleRequest($request);
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
