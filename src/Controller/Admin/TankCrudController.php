<?php

namespace App\Controller\Admin;

use App\Entity\Tank;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class TankCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Tank::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Inventarinformationen')
            ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            IdField::new('id')
                ->hideOnForm()
                ->setColumns(3),
            TextField::new('inventory')
                ->setColumns(3),
            TextField::new('serialnumber')
                ->setColumns(3),
            FormField::addRow(breakpointName: 'md' ),
            ChoiceField::new('size')
                ->setChoices([
                    '3L' => '3',
                    '5L' => '5',
                    '7L' => '7',
                    '8L' => '8',
                    '10L' => '10',
                    '12L' => '12',
                    '15L' => '15',
                    '18L' => '18',
                    '20L' => '20'
                ])
                ->setColumns(2),
            BooleanField::new('oxigenClean', 'Sauerstoffrein')
                ->setColumns(2),
            DateField::new('buyDate','Kaufdatum')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy'),
            FormField::addFieldset('Notizen')
                ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            TextEditorField::new('notes')
                ->setColumns(8),
            FormField::addFieldset('Prüfinformationen')
                ->collapsible(),
            FormField::addRow(breakpointName: 'md' ),
            DateField::new('lastCheckDate','letzte Prüfung')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy'),
            DateField::new('nextCheckDate','nächste Prüfung')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy'),
            FormField::addRow(breakpointName: 'md' ),
            AssociationField::new('tankChecks', 'Prüfungen')
                ->setColumns(8),
        ];
    }
}
