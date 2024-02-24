<?php

namespace App\Controller\Admin;

use App\Entity\TankCheck;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TankCheckCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TankCheck::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Termininformationen')
                ->collapsible(),
            IdField::new('id')
                ->hideOnForm()
                ->setColumns(3),
            DateField::new('checkDate','Prüfdatum')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy'),
            FormField::addFieldset('Prüferinformationen')
            ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            AssociationField::new('vendor', 'Prüfunternehmen')
                ->setColumns(8),
            FormField::addRow(breakpointName: 'md' ),
            TextEditorField::new('notes', 'Bemerkungen')
                ->setColumns(5),
            FormField::addFieldset('Angebotsinformationen')
                ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            TextEditorField::new('costInformation')
                ->setColumns(5),
            FormField::addFieldset('Flascheninformationen')
                ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            AssociationField::new('tank', 'Flaschen')
                ->setColumns(8),
        ];
    }

}
