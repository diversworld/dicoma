<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\FileAction;
use App\Entity\MediaFolder;
use App\Entity\MediaFolderPermission;
use App\Entity\User;
use App\Security\DelegatedRoles;
use App\Service\AdminClubContext;
use App\Service\MembershipRoleContinuity;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/club-functions', defaults: DashboardController::ROUTE_DEFAULTS)]
final class ClubFunctionController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $context,
        private readonly MembershipRoleContinuity $continuity,
        private readonly RoleHierarchyInterface $hierarchy,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $enabled,
    ) {}

    #[Route('/new', name: 'admin_function_definition_new', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        return $this->definition($request, null);
    }

    #[Route('/{id}/edit', name: 'admin_function_definition_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        return $this->definition($request, $id);
    }

    #[Route('/{id}/delete', name: 'admin_function_definition_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        return $this->inContext($request, function (Club $club, User $actor, bool $super) use ($id, $request): Response {
            $function = $this->function($id, $club);
            $this->assertManageable($function, $actor, $super);
            if (!$this->isCsrfTokenValid('club_function_delete_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Ungültiger CSRF-Token.');
            }
            if (!hash_equals((string) $function->getVersion(), $request->request->getString('version'))) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Die Funktion wurde zwischenzeitlich geändert. Bitte neu laden.');
            }
            if ($this->em->getRepository(ClubFunctionAssignment::class)->count(['clubFunction' => $function]) > 0) {
                $this->addFlash('warning', 'Eine Funktion mit zugeordneten Inhabern kann nicht gelöscht werden.');
                return $this->redirectToRoute('admin_function_definition_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
            }
            $this->em->remove($function);
            $this->em->flush();
            $this->logger->notice('Club function deleted', ['actor_id' => $actor->getId(), 'club_id' => $club->getId(), 'function_id' => $id]);
            $this->addFlash('success', 'Funktion gelöscht.');
            return $this->redirectToRoute('admin_club_functions', [], Response::HTTP_SEE_OTHER);
        });
    }

    private function definition(Request $request, ?int $id): Response
    {
        return $this->inContext($request, function (Club $club, User $actor, bool $super) use ($request, $id): Response {
            $function = $id === null ? null : $this->function($id, $club);
            if ($function !== null) {
                $this->assertManageable($function, $actor, $super);
            }
            $permissions = $super
                ? array_filter(User::getAvailableRoles(), static fn (string $role): bool => !in_array($role, [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN], true))
                : DelegatedRoles::choices();
            $data = [
                'name' => $function?->getName() ?? '',
                'permissions' => $function?->getPermissions() ?? [],
                'successorRequired' => $function?->isSuccessorRequired() ?? false,
                'startsAt' => $function?->getStartsAt(),
                'endsAt' => $function?->getEndsAt(),
            ];
            foreach (FileAction::cases() as $action) {
                $data[$action->value] = $function?->rootPermission($action) === true;
            }
            $builder = $this->createFormBuilder($data, ['csrf_token_id' => 'club_function_'.($id ?? 'new')])
                ->add('name', TextType::class, ['label' => 'Name', 'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 120)]])
                ->add('permissions', ChoiceType::class, ['label' => 'Berechtigungen', 'choices' => $permissions, 'multiple' => true, 'expanded' => true, 'required' => false])
                ->add('successorRequired', CheckboxType::class, ['label' => 'Nachfolge erforderlich', 'required' => false])
                ->add('startsAt', DateType::class, ['label' => 'Gültig ab', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
                ->add('endsAt', DateType::class, ['label' => 'Gültig bis einschließlich', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false]);
            foreach (['read' => 'Lesen', 'upload' => 'Hochladen', 'edit' => 'Bearbeiten', 'delete' => 'Löschen'] as $action => $label) {
                $builder->add($action, CheckboxType::class, ['label' => $label.' im Hauptordner erlauben', 'required' => false]);
            }
            $form = $builder->add('version', HiddenType::class, ['mapped' => false, 'data' => $function === null ? 'new' : (string) $function->getVersion()])
                ->getForm()->handleRequest($request);
            $conflict = false;
            if ($form->isSubmitted() && $form->isValid()) {
                $expected = $function === null ? 'new' : (string) $function->getVersion();
                if (!hash_equals($expected, (string) $form->get('version')->getData())) {
                    $form->addError(new FormError('Die Funktion wurde zwischenzeitlich geändert. Bitte neu laden.'));
                    $conflict = true;
                } else {
                    $values = $form->getData();
                    try {
                        ClubFunction::assertValidity($values['startsAt'], $values['endsAt']);
                        $candidate = new ClubFunction($club, $values['name']);
                        $candidate->setPermissions($values['permissions']);
                        $duplicate = $this->em->getRepository(ClubFunction::class)->findOneBy(['club' => $club, 'name' => $candidate->getName()]);
                        if ($duplicate !== null && $duplicate->getId() !== $id) {
                            throw new \InvalidArgumentException('Eine Funktion mit diesem Namen existiert bereits.');
                        }
                        $function ??= $candidate;
                        $function->rename($candidate->getName());
                        $function->setPermissions($values['permissions']);
                        $function->setValidity($values['startsAt'], $values['endsAt']);
                        $function->setSuccessorRequired($values['successorRequired']);
                        foreach (FileAction::cases() as $action) {
                            $function->setRootPermission($action, $values[$action->value]);
                        }
                        $this->em->persist($function);
                        $this->em->flush();
                        $this->logger->notice('Club function changed', ['actor_id' => $actor->getId(), 'club_id' => $club->getId(), 'function_id' => $function->getId()]);
                        $this->addFlash('success', 'Funktion gespeichert.');
                        return $this->redirectToRoute('admin_function_definition_edit', ['id' => $function->getId()], Response::HTTP_SEE_OTHER);
                    } catch (\InvalidArgumentException $exception) {
                        $form->addError(new FormError($exception->getMessage()));
                    }
                }
            }
            $assignments = $function === null ? [] : $this->em->getRepository(ClubFunctionAssignment::class)->findBy(['clubFunction' => $function]);
            $accounts = $function === null ? [] : $this->em->createQueryBuilder()->select('account')->from(User::class, 'account')
                ->join('account.member', 'membership')->where('membership.club = :club')->andWhere('membership.membershipStatus = :active')
                ->andWhere('membership.leftAt IS NULL')->andWhere('membership.archivedAt IS NULL')
                ->setParameter('club', $club)->setParameter('active', 'active')->orderBy('account.user', 'ASC')->getQuery()->getResult();
            if (!$super) {
                $accounts = array_values(array_filter($accounts, fn (User $account): bool =>
                    $account->getId() !== $actor->getId() && !$this->isPrivileged($account)));
            }
            return $this->render('admin/roles/club_function.html.twig', [
                'function' => $function, 'form' => $form, 'assignments' => $assignments, 'accounts' => $accounts,
                'folders' => $function === null ? [] : $this->em->getRepository(MediaFolder::class)->findBy(['club' => $club], ['name' => 'ASC']),
            ], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
        });
    }

    #[Route('/{id}/folder/{folderId}', name: 'admin_club_function_folder', requirements: ['id' => '\d+', 'folderId' => '\d+'], methods: ['GET', 'POST'])]
    public function folder(int $id, int $folderId, Request $request): Response
    {
        return $this->inContext($request, function (Club $club, User $actor, bool $super) use ($id, $folderId, $request): Response {
            $function = $this->function($id, $club);
            $this->assertManageable($function, $actor, $super);
            $folder = $this->em->find(MediaFolder::class, $folderId);
            if ($folder === null || $folder->getClub()->getId() !== $club->getId()) {
                throw $this->createNotFoundException('Ordner nicht gefunden.');
            }
            $rule = $this->em->getRepository(MediaFolderPermission::class)->findOneBy(['clubFunction' => $function, 'folder' => $folder]);
            $data = [];
            foreach (FileAction::cases() as $action) {
                $value = $rule?->permission($action);
                $data[$action->value] = $value === null ? 'inherit' : ($value ? 'allow' : 'deny');
            }
            $builder = $this->createFormBuilder($data, ['csrf_token_id' => 'club_function_folder_'.$id.'_'.$folderId]);
            foreach (['read' => 'Lesen', 'upload' => 'Hochladen', 'edit' => 'Bearbeiten', 'delete' => 'Löschen'] as $action => $label) {
                $builder->add($action, ChoiceType::class, [
                    'label' => $label, 'choices' => ['Erben' => 'inherit', 'Erlauben' => 'allow', 'Sperren' => 'deny'],
                ]);
            }
            $form = $builder->add('version', HiddenType::class, ['mapped' => false, 'data' => $rule === null ? 'new' : (string) $rule->getVersion()])
                ->getForm()->handleRequest($request);
            $conflict = false;
            if ($form->isSubmitted() && $form->isValid()) {
                $expected = $rule === null ? 'new' : (string) $rule->getVersion();
                if (!hash_equals($expected, (string) $form->get('version')->getData())) {
                    $form->addError(new FormError('Die Ordnerregel wurde zwischenzeitlich geändert. Bitte neu laden.'));
                    $conflict = true;
                } else {
                    $values = $form->getData();
                    if (count(array_unique(array_values($values))) === 1 && reset($values) === 'inherit') {
                        if ($rule !== null) {
                            $this->em->remove($rule);
                        }
                    } else {
                        $rule ??= new MediaFolderPermission($function, $folder);
                        foreach (FileAction::cases() as $action) {
                            $rule->setPermission($action, match ($values[$action->value]) {
                                'inherit' => null, 'allow' => true, 'deny' => false,
                            });
                        }
                        $this->em->persist($rule);
                    }
                    $this->em->flush();
                    $this->logger->notice('Club function folder permissions changed', ['actor_id' => $actor->getId(), 'function_id' => $id, 'folder_id' => $folderId]);
                    $this->addFlash('success', 'Ordnerrechte gespeichert.');
                    return $this->redirectToRoute('admin_function_definition_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
                }
            }
            return $this->render('admin/roles/club_function_folder.html.twig', [
                'function' => $function, 'folder' => $folder, 'form' => $form,
            ], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
        });
    }

    #[Route('/{id}/holder', name: 'admin_club_function_choose_holder', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function chooseHolder(int $id, Request $request): Response
    {
        $user = $request->query->getString('user');
        if (!ctype_digit($user) || (int) $user < 1) {
            throw $this->createNotFoundException('Konto nicht gefunden.');
        }
        return $this->redirectToRoute('admin_club_function_holder', ['id' => $id, 'userId' => (int) $user]);
    }

    #[Route('/{id}/holder/{userId}', name: 'admin_club_function_holder', requirements: ['id' => '\d+', 'userId' => '\d+'], methods: ['GET', 'POST'])]
    public function holder(int $id, int $userId, Request $request): Response
    {
        return $this->inContext($request, function (Club $club, User $actor, bool $super) use ($id, $userId, $request): Response {
            $function = $this->function($id, $club);
            $this->assertManageable($function, $actor, $super);
            $user = $this->em->find(User::class, $userId) ?? throw $this->createNotFoundException('Konto nicht gefunden.');
            $member = $user->getMember();
            if ($member === null || !$member->isActiveMember() || $member->getArchivedAt() !== null || $member->getClub()?->getId() !== $club->getId()
                || (!$super && ($user->getId() === $actor->getId() || $this->isPrivileged($user)))) {
                throw $this->createAccessDeniedException('Dieses Konto kann nicht zugeordnet werden.');
            }
            $assignment = $this->em->getRepository(ClubFunctionAssignment::class)->findOneBy(['clubFunction' => $function, 'user' => $user]);
            $form = $this->createFormBuilder([
                'assigned' => $assignment !== null, 'startsAt' => $assignment?->getStartsAt(), 'endsAt' => $assignment?->getEndsAt(),
            ], ['csrf_token_id' => 'club_function_holder_'.$id.'_'.$userId])
                ->add('assigned', CheckboxType::class, ['label' => 'Funktion zugeordnet', 'required' => false])
                ->add('startsAt', DateType::class, ['label' => 'Individuell gültig ab', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
                ->add('endsAt', DateType::class, ['label' => 'Individuell gültig bis einschließlich', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
                ->add('version', HiddenType::class, ['mapped' => false, 'data' => $assignment === null ? 'new' : (string) $assignment->getVersion()])
                ->getForm()->handleRequest($request);
            $conflict = false;
            if ($form->isSubmitted() && $form->isValid()) {
                $expected = $assignment === null ? 'new' : (string) $assignment->getVersion();
                if (!hash_equals($expected, (string) $form->get('version')->getData())) {
                    $form->addError(new FormError('Die Zuordnung wurde zwischenzeitlich geändert. Bitte neu laden.'));
                    $conflict = true;
                } else {
                    $values = $form->getData();
                    try {
                        ClubFunction::assertValidity($values['startsAt'], $values['endsAt']);
                        if ($assignment !== null && $assignment->isEffectiveOn(new \DateTimeImmutable('today'))
                            && (!$values['assigned'] || !ClubFunction::withinValidity(new \DateTimeImmutable('today'), $values['startsAt'], $values['endsAt']))
                            && $function->isSuccessorRequired() && !$this->hasSuccessor($function, $user)) {
                            throw new \InvalidArgumentException('Für diese Funktion muss zuerst eine aktive Nachfolge zugeordnet werden.');
                        }
                        if ($values['assigned']) {
                            $assignment ??= new ClubFunctionAssignment($function, $user);
                            $assignment->setValidity($values['startsAt'], $values['endsAt']);
                            $this->em->persist($assignment);
                        } elseif ($assignment !== null) {
                            $user->removeFunctionAssignment($assignment);
                            $this->em->remove($assignment);
                        }
                        $this->em->flush();
                        $this->logger->notice('Club function assignment changed', ['actor_id' => $actor->getId(), 'user_id' => $userId, 'function_id' => $id]);
                        $this->addFlash('success', 'Funktionszuordnung gespeichert.');
                        return $this->redirectToRoute('admin_function_definition_edit', ['id' => $id], Response::HTTP_SEE_OTHER);
                    } catch (\InvalidArgumentException $exception) {
                        $form->addError(new FormError($exception->getMessage()));
                    }
                }
            }
            return $this->render('admin/roles/club_function_holder.html.twig', [
                'function' => $function, 'account' => $user, 'form' => $form,
            ], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
        });
    }

    private function inContext(Request $request, callable $operation): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException();
        }
        $clubId = $this->context->getClub()?->getId() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        $actorId = $this->getUser()?->getId() ?? throw $this->createAccessDeniedException();
        $run = function () use ($request, $operation, $clubId, $actorId): Response {
            if ($request->isMethod('POST')) {
                $this->continuity->lockClub($this->em->getReference(Club::class, $clubId));
                $this->em->clear();
            }
            $club = $this->em->find(Club::class, $clubId) ?? throw $this->createAccessDeniedException();
            $actor = $this->em->find(User::class, $actorId) ?? throw $this->createAccessDeniedException();
            $roles = $this->hierarchy->getReachableRoleNames($actor->getRoles());
            $super = in_array(User::ROLE_SUPER_ADMIN, $roles, true);
            if (!in_array(User::ROLE_ADMIN, $roles, true) || (!$super && (!$actor->getMember()?->isActiveMember()
                || $actor->getMember()?->getArchivedAt() !== null || $actor->getMember()?->getClub()?->getId() !== $clubId))) {
                throw $this->createAccessDeniedException();
            }
            return $operation($club, $actor, $super);
        };
        return $request->isMethod('POST') ? $this->em->getConnection()->transactional($run) : $run();
    }

    private function function(int $id, Club $club): ClubFunction
    {
        $function = $this->em->find(ClubFunction::class, $id);
        if ($function === null || $function->getClub()->getId() !== $club->getId()) {
            throw $this->createNotFoundException('Funktion nicht gefunden.');
        }
        return $function;
    }

    private function assertManageable(ClubFunction $function, User $actor, bool $super): void
    {
        if ($super) {
            return;
        }
        if (array_diff($function->getPermissions(), DelegatedRoles::ALLOWED) !== []) {
            throw $this->createAccessDeniedException('Geschützte Funktionsberechtigungen.');
        }
        foreach ($this->em->getRepository(ClubFunctionAssignment::class)->findBy(['clubFunction' => $function]) as $assignment) {
            $user = $assignment->getUser();
            if ($user->getId() === $actor->getId() || $this->isPrivileged($user)) {
                throw $this->createAccessDeniedException('Eigene oder geschützte Konten sind dieser Funktion zugeordnet.');
            }
        }
    }

    private function isPrivileged(User $user): bool
    {
        if (!DelegatedRoles::isDelegableTarget($user)) {
            return true;
        }
        foreach ($this->em->getRepository(ClubFunctionAssignment::class)->findBy(['user' => $user]) as $assignment) {
            if (array_diff($assignment->getClubFunction()->getPermissions(), DelegatedRoles::ALLOWED) !== []) {
                return true;
            }
        }
        return false;
    }

    private function hasSuccessor(ClubFunction $function, User $holder): bool
    {
        foreach ($this->em->getRepository(ClubFunctionAssignment::class)->findBy(['clubFunction' => $function]) as $assignment) {
            if ($assignment->getUser()->getId() !== $holder->getId() && $assignment->isEffectiveOn(new \DateTimeImmutable('today'))) {
                return true;
            }
        }
        return false;
    }

    #[Route('', name: 'admin_club_functions', methods: ['GET'])]
    public function index(AdminUrlGenerator $urls): Response
    {
        if (!$this->enabled || $this->context->getClub() === null) {
            throw $this->createAccessDeniedException();
        }
        return $this->redirect($urls->unsetAll()->setController(ClubFunctionCrudController::class)->setAction(Crud::PAGE_INDEX)->generateUrl());
    }
}
