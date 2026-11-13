<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Security\DelegatedRoles;
use App\Security\Voter\DelegatedRoleVoter;
use App\Service\AdminClubContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class DelegatedUserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminClubContext $clubContext,
        private readonly RequestStack $requests,
        private readonly TranslatorInterface $translator,
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $role = $this->functionRole();
        $label = $role === null ? null : array_search($role, DelegatedRoleController::functionChoices(), true);
        return $crud
            ->setEntityLabelInSingular('Konto')
            ->setEntityLabelInPlural('Vereinsfunktionen')
            ->setPageTitle(Crud::PAGE_INDEX, $label === null ? 'Konten' : $this->translator->trans('Zuordnungen: %function%', ['%function%' => $this->translator->trans($label)]))
            ->setHelp(Crud::PAGE_INDEX, $this->translator->trans('Alle Konten werden angezeigt. Nur zulässige Zuordnungen können bearbeitet werden; geschützte Rollen verwalten Superadministratoren in der Benutzerverwaltung. Zugeordnet bedeutet ausdrücklich vergeben, nicht geerbt. „…“ bezeichnet eine offene Zeitgrenze.'))
            ->overrideTemplate('crud/index', 'admin/roles/function_members.html.twig')
            ->setDefaultSort(['user' => 'ASC'])
            ->setSearchFields(['user', 'member.person.firstname', 'member.person.lastname']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->functionRole();
        $edit = Action::new('editFunctionAssignment', 'Zuordnung bearbeiten')
            ->linkToRoute('admin_function_assignment_edit', static fn (User $user): array => ['role' => $role, 'id' => $user->getId()])
            ->displayIf(fn (User $user): bool => $role !== null && in_array($role, DelegatedRoles::ALLOWED, true)
                && $this->isGranted(DelegatedRoleVoter::MANAGE, $user));
        $back = Action::new('backToFunctions', 'Zur Funktionsliste')
            ->linkToRoute('admin_delegated_roles')
            ->createAsGlobalAction();
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $edit)
            ->add(Crud::PAGE_INDEX, $back);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('member.fullName', 'Mitglied');
        yield TextField::new('user', 'Benutzername');
        $role = $this->functionRole();
        if ($role !== null) {
            yield TextField::new('user', 'Zuordnung')
                ->setSortable(false)
                ->formatValue(fn (mixed $value, User $user): string => htmlspecialchars($this->translator->trans(
                    in_array($role, $user->getStoredRoles(), true) ? 'Zugeordnet' : 'Nicht zugeordnet'
                ), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            yield TextField::new('user', 'Gültigkeitszeitraum')
                ->setSortable(false)
                ->formatValue(function (mixed $value, User $user) use ($role): string {
                    if (!in_array($role, $user->getStoredRoles(), true)) {
                        return '–';
                    }
                    $window = $user->getRoleValidity()[$role] ?? ['startsAt' => null, 'endsAt' => null];
                    return htmlspecialchars(($window['startsAt'] ?? '…').' – '.($window['endsAt'] ?? '…'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                });
        }
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
    }

    private function functionRole(): ?string
    {
        $role = $this->requests->getCurrentRequest()?->query->get('function');
        if ($role !== null && !in_array($role, DelegatedRoleController::functionChoices(), true)) {
            throw $this->createNotFoundException('Unbekannte Vereinsfunktion.');
        }
        return $role;
    }
}
