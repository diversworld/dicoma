<?php

namespace App\Controller\Admin;

use App\Entity\Schedule;
use App\Entity\User;
use App\Repository\UserRepository;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TimeField;

class ScheduleCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Schedule::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
        FormField::addFieldset('Kurstermininformation')
            ->collapsible(),
        IdField::new('id')
            ->setColumns(1)
            ->hideOnForm(),
        TextField::new('title', 'Terminbezeichnung')
            ->setColumns(6),
        ImageField::new('image', 'Bild')
            ->setBasePath('/images/kurse/')
            ->setUploadDir('public/images/kurse/')
            ->setUploadedFileNamePattern('[randomness].[extension]')
            ->setRequired(false)
            ->setColumns(5),
        FormField::addFieldset('Termininformationen')
            ->collapsible(),
        DateField::new('startDate','Beginnt am')
            ->setColumns(2)
            ->setFormat('dd.MM.yyyy'),
        TimeField::new('startTime', 'um')
            ->setColumns(2)
            ->setFormat('HH:mm'),
        IntegerField::new('duration', 'Kursdauer in Tagen')
            ->setColumns(2),
        FormField::addFieldset('Bemerkungen')
            ->collapsible(),
        FormField::addRow(breakpointName: 'md' ),
        TextEditorField::new('notes', 'Notizen')
            ->setColumns(8)
            ->hideOnIndex(),
        FormField::addFieldset('Adressinformationen')
            ->collapsible(),
        FormField::addRow(breakpointName: 'md' ),
        TextField::new('location', 'Veranstaltungsort')
            ->setColumns(5),
        FormField::addRow(breakpointName: 'md' ),
        TextField::new('locationStreet', 'Straße')
            ->setColumns(5)
            ->hideOnIndex(),
        FormField::addRow(breakpointName: 'md' ),
        IntegerField::new('locationPostal', 'PLZ')
            ->setColumns(1)
            ->hideOnIndex(),
        TextField::new('locationCity', 'Ort')
            ->setColumns(4)
            ->hideOnIndex(),
        FormField::addFieldset('Kursinformation')
            ->collapsible(),
        FormField::addRow(breakpointName: 'md' ),
        AssociationField::new('courses', 'Kurs')
            ->setColumns(5),
        MoneyField::new('price', 'Preis')
            ->setColumns(2)
            ->setCurrency('EUR'),
        AssociationField::new('instructor', 'Instruktor')
            ->setColumns(4)
            ->setFormTypeOption('query_builder', function(UserRepository $userRepository) {
                return $userRepository->getInstructors();
            }),
        FormField::addFieldset('Buchungen')
            ->collapsible(),
        AssociationField::new('bookings', 'Buchungen')
            ->setColumns(5),
    ];
    }
}
