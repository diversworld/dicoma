<?php

namespace App\Controller\Admin;

use App\Entity\Sport;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class SportCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Sport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Sportart')
            ->setEntityLabelInPlural('Sportarten')
            ->setPageTitle(Crud::PAGE_INDEX, 'Sportarten')
            ->setPageTitle(Crud::PAGE_NEW, 'Sportart anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Sportart bearbeiten')
            ->setDefaultSort([
                'sortOrder' => 'ASC',
                'name' => 'ASC',
            ])
            ->setSearchFields([
                'name',
                'shortName',
                'club.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Sportart');

        yield AssociationField::new('club', 'Verein')
            ->setRequired(true)
            ->setColumns(4);

        yield TextField::new('name', 'Bezeichnung')
            ->setRequired(true)
            ->setColumns(4);

        yield TextField::new('shortName', 'Kurzbezeichnung')
            ->setColumns(2);

        yield IntegerField::new('sortOrder', 'Sortierung')
            ->setColumns(2);

        yield BooleanField::new('active', 'Aktiv')
            ->setColumns(2);

        yield TextareaField::new('description', 'Beschreibung')
            ->hideOnIndex();
    }
}