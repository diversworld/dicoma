<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Security\Voter\DelegatedRoleVoter;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class DelegatedUserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminClubContext $clubContext,
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Konto')
            ->setEntityLabelInPlural('Vereinsfunktionen')
            ->setPageTitle(Crud::PAGE_INDEX, 'Vereinsfunktionen verwalten')
            ->setDefaultSort(['user' => 'ASC'])
            ->setSearchFields(['user', 'member.person.firstname', 'member.person.lastname']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $edit = Action::new('editFunctions', 'Funktionen bearbeiten')
            ->linkToRoute('admin_delegated_roles_edit', static fn (User $user): array => ['id' => $user->getId()]);
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $edit);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('member.fullName', 'Mitglied');
        yield TextField::new('user', 'Benutzername');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $club = $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        $users = $this->em->getRepository(User::class)->createQueryBuilder('account')
            ->join('account.member', 'membership')
            ->where('membership.club = :club')
            ->setParameter('club', $club)
            ->getQuery()->getResult();
        $ids = [];
        foreach ($users as $user) {
            if ($this->isGranted(DelegatedRoleVoter::MANAGE, $user)) {
                $ids[] = $user->getId();
            }
        }

        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        return $ids === [] ? $query->andWhere('1 = 0')
            : $query->andWhere('entity.id IN (:managedUsers)')->setParameter('managedUsers', $ids);
    }
}
