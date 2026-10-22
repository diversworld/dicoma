<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentSubType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class EquipmentSubTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return EquipmentSubType::class; }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Unterart')->setEntityLabelInPlural('Unterarten');
    }
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('club', 'Verein')->setRequired(true);
        yield AssociationField::new('category', 'Ausrüstungsart')->setRequired(true);
        yield TextField::new('name', 'Bezeichnung')->setRequired(true);
        yield BooleanField::new('active', 'Aktiv');
    }
}
