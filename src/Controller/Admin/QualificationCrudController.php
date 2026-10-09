<?php

namespace App\Controller\Admin;

use App\Entity\Qualification;
use App\Enum\QualificationType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\QualificationTrainingUnitType;

#[IsGranted('ROLE_ADMIN')]
class QualificationCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Qualification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Qualifikation')
            ->setEntityLabelInPlural('Qualifikationen')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Qualifikationen'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Qualifikation anlegen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Qualifikation bearbeiten'
            )
            ->setDefaultSort([
                'sortOrder' => 'ASC',
                'name' => 'ASC',
            ])
            ->setSearchFields([
                'name',
                'shortName',
                'issuer',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Qualifikation');

        yield AssociationField::new('club', 'Verein')
            ->setRequired(false)
            ->setColumns(4)
            ->setHelp(
                'Leer lassen für eine vereinsübergreifende Qualifikation.'
            );

        yield ChoiceField::new('type', 'Typ')
            ->setChoices(QualificationType::choices())
            ->setRequired(true)
            ->setColumns(4);

        yield BooleanField::new('active', 'Aktiv')
            ->setColumns(2);

        yield TextField::new('name', 'Bezeichnung')
            ->setRequired(true)
            ->setColumns(5);

        yield TextField::new('shortName', 'Kurzbezeichnung')
            ->setColumns(3);

        yield TextField::new('issuer', 'Verband / Herausgeber')
            ->setColumns(4);

        yield IntegerField::new(
            'validityMonths',
            'Gültigkeit in Monaten'
        )
            ->setColumns(3)
            ->setHelp(
                'Leer lassen, wenn die Qualifikation nicht abläuft.'
            );

        yield IntegerField::new('sortOrder', 'Sortierung')
            ->setColumns(2);

        yield TextareaField::new('description', 'Beschreibung')
            ->hideOnIndex();

		yield FormField::addFieldset(
			'Ausbildungsplan'
		)
			->collapsible();

		yield CollectionField::new(
			'trainingUnits',
			'Ausbildungseinheiten'
		)
			->setEntryType(
				QualificationTrainingUnitType::class
			)
			->allowAdd()
			->allowDelete()
			->setFormTypeOption(
				'by_reference',
				false
			)
			->setHelp(
				'Standard-Ausbildungsplan für Kurse, '
				. 'die zu dieser Qualifikation führen.'
			)
			->onlyOnForms();		
		
    }
}