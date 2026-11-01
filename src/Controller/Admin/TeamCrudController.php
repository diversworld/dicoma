<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\Sport;
use App\Entity\Member;
use App\Service\AdminTeamScope;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use App\Enum\TeamType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\TeamMemberType;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;

#[IsGranted('ROLE_SUPER_ADMIN')]
class TeamCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly AdminTeamScope $scope,
    ) {}

    private function assertTeam(Team $team): void
    {
        $this->scope->assertTeam($team);
        foreach ($team->getMembers() as $assignment) {
            $this->scope->assertMember($assignment->getMember());
        }
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $clubId = $this->scope->clubId();
        return $clubId === null ? $query->andWhere('1 = 0')
            : $query->join('entity.sport', 'teamSport')->andWhere('teamSport.club = :teamClub')->setParameter('teamClub', $clubId);
    }

    public function edit(AdminContext $context)
    {
        $this->assertTeam($context->getEntity()->getInstance());
        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $this->assertTeam($context->getEntity()->getInstance());
        return parent::detail($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Team) {
            $this->assertTeam($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Team) {
            $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $sport = $original['sport'] ?? null;
            if (!$sport instanceof Sport || $sport->getClub()?->getId() !== $this->scope->clubId() || $this->scope->clubId() === null) {
                throw $this->createAccessDeniedException('Mannschaft gehört nicht zum ausgewählten Verein.');
            }
            $this->assertTeam($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Team) {
            $this->assertTeam($entityInstance);
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== Team::class) {
            throw new BadRequestHttpException('Ungültiger Mannschafts-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Mannschafts-ID.');
            }
            $team = $this->entityManager->find(Team::class, (int) $id);
            if ($team !== null) {
                $this->assertTeam($team);
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

    public function configureActions(Actions $actions): Actions
    {
        $center = Action::new('teamCenter', 'Gruppenzentrale', 'fa fa-users')
            ->linkToCrudAction(Crud::PAGE_DETAIL);

        return $actions->add(Crud::PAGE_INDEX, $center);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if ($responseParameters->get('pageName') !== Crud::PAGE_DETAIL) {
            return $responseParameters;
        }
        $context = $this->getContext();
        $team = $context !== null && $context->getCrud() !== null
            ? $context->getEntity()?->getInstance() : null;
        if (!$team instanceof Team || $team->getId() === null) {
            return $responseParameters;
        }
        $members = $this->entityManager->getRepository(TeamMember::class)
            ->findBy(['team' => $team], ['id' => 'DESC']);
        $responseParameters->set('teamCenterMembers', $members);
        $url = function (string $controller, string $action, ?int $id = null): string {
            $generator = (clone $this->adminUrlGenerator)->unsetAll()
                ->setController($controller)->setAction($action);
            if ($id !== null) {
                $generator->setEntityId($id);
            }
            return $generator->generateUrl();
        };
        $responseParameters->set('teamCenterLinks', [
            'edit' => $url(self::class, Crud::PAGE_EDIT, $team->getId()),
            'membersIndex' => $url(TeamMemberCrudController::class, Crud::PAGE_INDEX),
            'membersNew' => (clone $this->adminUrlGenerator)->unsetAll()
                ->setController(TeamMemberCrudController::class)
                ->setAction(Crud::PAGE_NEW)
                ->set('teamId', $team->getId())
                ->generateUrl(),
        ]);
        $editUrls = [];
        $memberUrls = [];
        foreach ($members as $item) {
            if ($item->getId() !== null) {
                $editUrls[$item->getId()] = $url(TeamMemberCrudController::class, Crud::PAGE_EDIT, $item->getId());
            }
            if ($item->getMember()?->getId() !== null) {
                $memberUrls[$item->getMember()->getId()] = $url(MemberCrudController::class, Crud::PAGE_DETAIL, $item->getMember()->getId());
            }
        }
        $responseParameters->set('teamCenterEditUrls', $editUrls);
        $responseParameters->set('teamCenterMemberUrls', $memberUrls);
        return $responseParameters;
    }

    public static function getEntityFqcn(): string
    {
        return Team::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setFormOptions(['csrf_token_id' => 'team_crud_'.($this->scope->clubId() ?? 'none')])
            ->overrideTemplate('crud/detail', 'admin/team/detail.html.twig')
            ->setEntityLabelInSingular('Gruppe / Mannschaft')
            ->setEntityLabelInPlural('Gruppen / Mannschaften')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Gruppen und Mannschaften'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Gruppe / Mannschaft anlegen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Gruppe / Mannschaft bearbeiten'
            )
            ->setDefaultSort([
                'sport.name' => 'ASC',
                'sortOrder' => 'ASC',
                'name' => 'ASC',
            ])
            ->setSearchFields([
                'name',
                'sport.name',
                'sport.club.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_DETAIL) {
            yield IdField::new('id', 'ID');
            return;
        }

        yield FormField::addFieldset('Zuordnung');

        $clubId = $this->scope->clubId();
        yield AssociationField::new('sport', 'Sportart')
            ->setFormTypeOption('query_builder', static function (EntityRepository $repository) use ($clubId): QueryBuilder {
                $query = $repository->createQueryBuilder('sportChoice');
                return $clubId === null ? $query->andWhere('1 = 0') : $query->andWhere('sportChoice.club = :club')->setParameter('club', $clubId);
            })
            ->setRequired(true)
            ->setColumns(5);

        yield ChoiceField::new('type', 'Typ')
            ->setChoices(TeamType::choices())
            ->setRequired(true)
            ->setColumns(4);

        yield BooleanField::new('active', 'Aktiv')
            ->setColumns(3);

        yield FormField::addFieldset('Gruppe / Mannschaft');

        yield TextField::new('name', 'Bezeichnung')
            ->setRequired(true)
            ->setColumns(6);

        yield IntegerField::new('sortOrder', 'Sortierung')
            ->setColumns(2);

        yield TextareaField::new('description', 'Beschreibung')
            ->hideOnIndex();
		
		yield FormField::addFieldset('Mitglieder')
			->collapsible();

		yield CollectionField::new('members', 'Mitglieder')
			->setEntryType(TeamMemberType::class)
            ->setFormTypeOption('entry_options', ['members' => $clubId === null ? [] : $this->entityManager->getRepository(Member::class)->findBy(['club' => $clubId])])
			->allowAdd()
			->allowDelete()
			->setFormTypeOption('by_reference', false)
			->setHelp(
				'Mitglieder und Trainer dieser Gruppe oder Mannschaft.'
			)
			->onlyOnForms();
    }
}