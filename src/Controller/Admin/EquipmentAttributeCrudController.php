<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentAttribute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EquipmentAttributeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return EquipmentAttribute::class; }
    public function configureCrud(Crud $crud): Crud { return $crud->setEntityLabelInSingular('Kategorie-Merkmal')->setEntityLabelInPlural('Kategorie-Merkmale'); }
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('category', 'Kategorie')->setRequired(true);
        yield TextField::new('code', 'Schlüssel');
        yield TextField::new('label', 'Bezeichnung');
        yield ChoiceField::new('dataType', 'Datentyp')->setChoices(['Text' => 'text', 'Zahl' => 'number', 'Ja/Nein' => 'boolean', 'Datum' => 'date']);
        yield BooleanField::new('required', 'Pflichtfeld');
    }
}
