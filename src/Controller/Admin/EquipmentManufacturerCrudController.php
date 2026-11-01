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

#[IsGranted('ROLE_SUPER_ADMIN')]
class EquipmentManufacturerCrudController extends AbstractEquipmentMasterCrudController
{
    public static function getEntityFqcn(): string { return EquipmentManufacturer::class; }
    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureMasterCrud($crud)->setEntityLabelInSingular('Hersteller')->setEntityLabelInPlural('Hersteller');
    }
    public function configureFields(string $pageName): iterable
    {
        yield $this->clubField();
        yield TextField::new('name', 'Bezeichnung')->setRequired(true);
        yield BooleanField::new('active', 'Aktiv');
    }
}
