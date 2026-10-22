<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentRegulatorDetails;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class EquipmentRegulatorDetailsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return EquipmentRegulatorDetails::class; }
    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Atemregler-Technik')
            ->setEntityLabelInPlural('Atemregler-Technikdaten');
    }
    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('equipmentItem', 'Inventargegenstand')->setRequired(true);
        yield TextField::new('firstStageSerial', 'Seriennummer 1. Stufe')->setRequired(true);
        yield TextField::new('firstStageModel', 'Modell 1. Stufe');
        yield TextField::new('primarySecondStageSerial', 'Seriennummer 2. Stufe (primaer)')->setRequired(true);
        yield TextField::new('primarySecondStageModel', 'Modell 2. Stufe (primaer)');
        yield TextField::new('secondarySecondStageSerial', 'Seriennummer 2. Stufe (sekundaer)');
        yield TextField::new('secondarySecondStageModel', 'Modell 2. Stufe (sekundaer)');
        yield DateField::new('nextServiceAt', 'Naechste Revision');
    }
}
