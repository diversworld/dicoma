<?php

namespace App\Controller\Admin;

use App\Entity\Team;
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
    public static function getEntityFqcn(): string
    {
        return Team::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
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