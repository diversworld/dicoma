<?php

namespace App\Controller\Admin;

use App\Entity\Member;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class MemberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Member::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Personendaten')
                ->setColumns(8)
                ->collapsible(),
            IdField::new('id', 'ID')
                ->hideOnForm()
                ->setColumns(3),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            TextField::new('firstname', 'Vorname')
                ->setColumns(4),
            TextField::new('lastname',  'Nachname')
                ->setColumns(4),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            DateField::new('birthday', 'Geburtsdatum')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy')
                ->setHelp('Format: dd.MM.yyyy')
                ->hideOnIndex(),
            ChoiceField::new('category','Kategorie')
                ->setColumns(2)
                ->setChoices([
                    'Schüler' => 'student',
                    'Instructor' => 'instructor',
                    'Kunde' => 'customer',
                ]),
            FormField::addFieldset('Adressdaten')
                ->collapsible(),
            TextField::new('street', 'Straße')
                ->setColumns(4)
                ->hideOnIndex(),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            IntegerField::new('postal', 'PLZ')
                ->setColumns(1)
                ->hideOnIndex(),
            TextField::new('city', 'Wohnort')
                ->setColumns(5)
                ->hideOnIndex(),
            FormField::addFieldset('Kontaktdaten')
                ->collapsible(),
            EmailField::new('email', 'E-Mail')
                ->setColumns(4),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            TelephoneField::new('mobile', 'Mobil')
                ->setColumns(3),
            TelephoneField::new('phone', 'Telefon')
                ->setColumns(3)
                ->hideOnIndex(),
            FormField::addFieldset('Nutzerdaten')
                ->collapsible(),
            AssociationField::new('username', 'Benutzer')
                ->setColumns(3),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            BooleanField::new('published', 'Veröffentlicht')
                ->setColumns(2),
        ];
    }
}
