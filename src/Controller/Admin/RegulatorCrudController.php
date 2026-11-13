<?php

namespace App\Controller\Admin;

use App\Entity\Regulator;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,TextField,ChoiceField,BooleanField,MoneyField,DateField,TextareaField,FormField};
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;

#[IsGranted('ROLE_SUPER_ADMIN')]
class RegulatorCrudController extends AbstractCrudController {

    use ClubEquipmentCrudTrait;
    public function configureAssets(Assets $assets): Assets { 
        return $assets->addJsFile('js/dicoma-manufacturer-options.js?v=81')->addJsFile('js/dicoma-regulator-models.js?v=81'); 
    }

    public static function getEntityFqcn(): string {
        return Regulator::class;
    }
    
    public function configureCrud(Crud $crud): Crud {
        return $crud->setFormOptions(['csrf_token_id' => 'regulator_crud_'.$this->clubEquipmentScope->club()->getId()])
            ->setEntityLabelInSingular('Atemregler')
            ->setEntityLabelInPlural('Atemregler')
            ->setSearchFields(['inventoryNumber','firstStageSerial','primarySecondStageSerial']);
        }
    
    public function configureFields(string $pageName): iterable {
        $club = $this->clubEquipmentScope->club();
        $choices = static fn (EntityRepository $repository): QueryBuilder =>
            $repository->createQueryBuilder('choice')->where('choice.club = :club')->setParameter('club', $club);

        $modelChoices = static fn (EntityRepository $repository): QueryBuilder =>
            $repository->createQueryBuilder('choice')->join('choice.manufacturer', 'modelManufacturer')
                ->where('choice.club = :club AND modelManufacturer.club = :club')->setParameter('club', $club);

        // DiCoMa STEP85 INDEX: dedicated, consistent overview columns

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventoryNumber', 'Inventarnummer');

            yield AssociationField::new('club', 'Verein');
            yield AssociationField::new('manufacturer', 'Hersteller');
            yield AssociationField::new('firstStageModel', 'Erste Stufe');
            yield AssociationField::new('primarySecondStageModel', 'Zweite Stufe');
            yield ChoiceField::new('conditionStatus', 'Zustand')
                ->setChoices([
                    'Einsatzbereit' => 'ready', 'Wartung' => 'maintenance',
                    'Beschädigt' => 'damaged',
                ]
            );
            yield MoneyField::new('rentalFee','Leihgebuehr')
                ->setCurrency('EUR')
                ->setStoredAsCents(false);
            yield BooleanField::new('lendable', 'Verleihfreigabe');
            yield DateField::new('nextServiceAt', 'Nächste Revision')
                ->setFormat('dd.MM.yyyy');
            return;
        }

        yield FormField::addFieldset('Inventar')
            ->collapsible();
        yield AssociationField::new('club','Verein')
            ->setColumns(3)
            ->setFormTypeOption('choices', [$club])
            ->setFormTypeOption('disabled', true)
            ->setRequired(true);
        yield TextField::new('inventoryNumber','Inventarnummer')
            ->setColumns(3)
            ->setRequired(true);
        yield AssociationField::new('manufacturer','Hersteller')
            ->setColumns(3)
            ->setRequired(true)
            ->setFormTypeOption('query_builder', $choices)
            ->setFormTypeOption('attr', ['data-manufacturer-options-url' => $this->generateUrl('admin_equipment_manufacturer_options')]);
        yield FormField::addFieldset('Erste Stufe')
            ->collapsible();
        yield AssociationField::new('firstStageModel','Modell 1. Stufe')
            ->setColumns(3)
            ->setFormTypeOption('query_builder', $modelChoices)
            ->setRequired(true);
        yield TextField::new('firstStageSerial','Seriennummer 1. Stufe')
            ->setColumns(3)
            ->setRequired(true);
        yield FormField::addFieldset('Zweite Stufen')
            ->collapsible();
        yield AssociationField::new('primarySecondStageModel','Modell 2. Stufe (primaer)')
            ->setColumns(3)
            ->setFormTypeOption('query_builder', $modelChoices)
            ->setRequired(true);
        yield TextField::new('primarySecondStageSerial','Seriennummer 2. Stufe (primaer)')
            ->setColumns(3)
            ->setRequired(true);
        yield AssociationField::new('secondarySecondStageModel','Modell 2. Stufe (optional)')
            ->setColumns(3)
            ->setFormTypeOption('query_builder', $modelChoices);
        yield TextField::new('secondarySecondStageSerial','Seriennummer 2. Stufe (optional)')
            ->setColumns(3);
        yield FormField::addFieldset('Verleih')
            ->collapsible();
        yield ChoiceField::new('conditionStatus','Zustand')
            ->setColumns(3)
            ->setChoices(['Einsatzbereit'=>'ready','Wartung'=>'maintenance','Beschaedigt'=>'damaged']);
        yield BooleanField::new('lendable','Verleihfreigabe')
            ->setColumns(3);
        yield MoneyField::new('rentalFee','Leihgebuehr')
            ->setColumns(3)
            ->setCurrency('EUR')
            ->setStoredAsCents(false);
        yield DateField::new('nextServiceAt','Naechste Revision')
            ->setColumns(3);
        yield FormField::addFieldset('Notizen')
            ->collapsible();
        yield TextareaField::new('notes','Notizen')
            ->hideOnIndex();
	}
}
