<?php

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Enum\MembershipStatus;
use App\Form\MemberSportType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\MemberQualificationType;

#[IsGranted('ROLE_ADMIN')]
class MemberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Member::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Mitglied')
            ->setEntityLabelInPlural('Mitglieder')
            ->setPageTitle(Crud::PAGE_INDEX, 'Mitglieder')
            ->setPageTitle(Crud::PAGE_NEW, 'Mitglied anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Mitglied bearbeiten')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Mitglied')
            ->setDefaultSort([
                'lastname' => 'ASC',
                'firstname' => 'ASC',
            ])
            ->setSearchFields([
                'memberNumber',
                'firstname',
                'lastname',
                'email',
                'city',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        /*
         * Übersicht
         */
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield TextField::new('memberNumber', 'Mitgliedsnr.')
            ->onlyOnIndex();

        yield AssociationField::new('club', 'Verein')
            ->onlyOnIndex();

        yield ChoiceField::new('membershipStatus', 'Status')
            ->setChoices(MembershipStatus::choices())
            ->onlyOnIndex();

        yield TextField::new('sportNames', 'Sportarten')
            ->onlyOnIndex();

        /*
         * Mitgliedschaft
         */
        yield FormField::addFieldset('Mitgliedschaft')
            ->collapsible();

        yield AssociationField::new('club', 'Verein')
            ->setRequired(true)
            ->setColumns(4)
            ->hideOnIndex();

        yield TextField::new('memberNumber', 'Mitgliedsnummer')
            ->setColumns(3)
            ->hideOnIndex();

        yield ChoiceField::new('membershipStatus', 'Status')
            ->setChoices(MembershipStatus::choices())
            ->setRequired(true)
            ->setColumns(3)
            ->hideOnIndex();

        yield DateField::new('joinedAt', 'Eintritt')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3)
            ->hideOnIndex();

        yield DateField::new('leftAt', 'Austritt')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3)
            ->hideOnIndex();

        /*
         * Person
         */
        yield FormField::addFieldset('Personendaten')
            ->collapsible();

        yield TextField::new('firstname', 'Vorname')
            ->setRequired(true)
            ->setColumns(4);

        yield TextField::new('lastname', 'Nachname')
            ->setRequired(true)
            ->setColumns(4);

        yield DateField::new('birthday', 'Geburtsdatum')
            ->setColumns(3)
            ->setFormat('dd.MM.yyyy')
            ->setHelp('Format: TT.MM.JJJJ')
            ->hideOnIndex();

        /*
         * Das alte category-Feld wird bewusst nicht mehr angezeigt.
         *
         * Die Zuordnung zu Sportarten erfolgt jetzt über MemberSport.
         * Instructor/Trainer wird später über Qualifikationen abgebildet.
         */

        /*
         * Anschrift
         */
        yield FormField::addFieldset('Adressdaten')
            ->collapsible();

        yield TextField::new('street', 'Straße / Hausnummer')
            ->setColumns(6)
            ->hideOnIndex();

        yield TextField::new('postalCode', 'PLZ')
            ->setColumns(2)
            ->hideOnIndex();

        yield TextField::new('city', 'Wohnort')
            ->setColumns(4)
            ->hideOnIndex();

        /*
         * Kontakt
         */
        yield FormField::addFieldset('Kontaktdaten')
            ->collapsible();

        yield EmailField::new('email', 'E-Mail')
            ->setColumns(4);

        yield TelephoneField::new('mobile', 'Mobil')
            ->setColumns(3);

        yield TelephoneField::new('phone', 'Telefon')
            ->setColumns(3)
            ->hideOnIndex();

        /*
         * Sportarten
         */
        yield FormField::addFieldset('Sportarten')
            ->collapsible();

        yield CollectionField::new('sports', 'Sportarten')
            ->setEntryType(MemberSportType::class)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            ->setHelp(
                'Hier können dem Mitglied eine oder mehrere '
                . 'Sportarten zugeordnet werden.'
            )
            ->onlyOnForms();

		yield FormField::addFieldset('Qualifikationen')
			->collapsible();

		yield CollectionField::new(
			'qualifications',
			'Qualifikationen & Brevets'
		)
			->setEntryType(MemberQualificationType::class)
			->allowAdd()
			->allowDelete()
			->setFormTypeOption('by_reference', false)
			->setHelp(
				'Brevets, Trainer- und Tauchlehrerqualifikationen, '
				. 'Erste Hilfe, Sauerstoff, Technik usw.'
			)
			->onlyOnForms();
		
        /*
         * Benutzerkonto
         */
        yield FormField::addFieldset('Benutzerkonto')
            ->collapsible();

        yield AssociationField::new('user', 'Benutzer')
            ->setRequired(false)
            ->setColumns(4)
            ->setHelp(
                'Optionales Benutzerkonto für den Zugang '
                . 'zum Mitgliederportal.'
            );

        /*
         * Weitere Informationen
         */
        yield FormField::addFieldset('Weitere Informationen')
            ->collapsible();

        yield TextareaField::new('notes', 'Notizen')
            ->hideOnIndex();

        yield BooleanField::new('published', 'Veröffentlicht')
            ->setColumns(2);
    }
}