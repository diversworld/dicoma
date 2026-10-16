<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentManufacturer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EquipmentManufacturerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return EquipmentManufacturer::class; }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Hersteller')->setEntityLabelInPlural('Hersteller');
    }
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('club', 'Verein')->setRequired(true);
        yield TextField::new('name', 'Bezeichnung')->setRequired(true);
        yield BooleanField::new('active', 'Aktiv');
    }
}
