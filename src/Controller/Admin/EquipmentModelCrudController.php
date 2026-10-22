<?php

declare(strict_types=1);
namespace App\Controller\Admin;

use App\Entity\EquipmentModel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class EquipmentModelCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return EquipmentModel::class; }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Ausrüstungsmodell')
            ->setEntityLabelInPlural('Ausrüstungsmodelle')
            ->setDefaultSort(['name' => 'ASC']);
    }
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('club', 'Verein')->setRequired(true);
        yield AssociationField::new('category', 'Ausrüstungsart')->setRequired(true);
        yield AssociationField::new('manufacturer', 'Hersteller')->setRequired(true);
        yield TextField::new('name', 'Modellbezeichnung')->setRequired(true);
        yield BooleanField::new('active', 'Aktiv');
    }
}
