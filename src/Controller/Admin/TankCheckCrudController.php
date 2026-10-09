<?php

namespace App\Controller\Admin;

use App\Entity\TankCheck;
use App\Form\TankCheckArticleType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
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

            // TankCheckArticle fields as a collection
            CollectionField::new('articles')
                //->setEntryType(TankCheckArticleType::class)
                ->useEntryCrudForm(TankCheckArticleCrudController::class)
                ->setEntryIsComplex()
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms()
                ->setLabel('Artikel')
                ->renderExpanded()
            ->setColumns(12), // Hier die Breite auf 12 Spalten setzen
            ArrayField::new('articles')->hideOnForm()->setLabel('Artikel')
        ];
    }

    public function createEntity(string $entityFqcn)
    {
        $tankCheck = new TankCheck();
        $tankCheck->setCheckDate(new \DateTime('now'));
        return $tankCheck;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Prüfungstermin')
            ->setEntityLabelInPlural('Prüfungstermine')
            ->setSearchFields(['vendorName', 'notes', 'costInformation'])
            ->setDefaultSort(['checkDate' => 'DESC']);
    }
}
