<?php

namespace App\Controller\Admin;

use App\Entity\Team;
use App\Entity\TeamMember;
use Doctrine\ORM\EntityManagerInterface;
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

#[IsGranted('ROLE_ADMIN')]
class TeamCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator
    ) {}

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

        yield AssociationField::new('sport', 'Sportart')
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
			->allowAdd()
			->allowDelete()
			->setFormTypeOption('by_reference', false)
			->setHelp(
				'Mitglieder und Trainer dieser Gruppe oder Mannschaft.'
			)
			->onlyOnForms();
    }
}