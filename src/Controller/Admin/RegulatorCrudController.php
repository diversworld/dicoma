<?php
namespace App\Controller\Admin;
use App\Entity\Regulator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,TextField,ChoiceField,BooleanField,MoneyField,DateField,TextareaField,FormField};
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
#[IsGranted('ROLE_SUPER_ADMIN')]
class RegulatorCrudController extends AbstractCrudController {
 public function configureAssets(Assets $assets): Assets { return $assets->addJsFile('js/dicoma-manufacturer-options.js?v=81')->addJsFile('js/dicoma-regulator-models.js?v=81'); }
 public static function getEntityFqcn(): string {return Regulator::class;}
 public function configureCrud(Crud $crud): Crud {return $crud->setEntityLabelInSingular('Atemregler')->setEntityLabelInPlural('Atemregler')->setSearchFields(['inventoryNumber','firstStageSerial','primarySecondStageSerial']);}
 public function configureFields(string $pageName): iterable {
        // DiCoMa STEP85 INDEX: dedicated, consistent overview columns

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventoryNumber', 'Inventarnummer');
            yield AssociationField::new('club', 'Verein');
            yield AssociationField::new('manufacturer', 'Hersteller');
            yield AssociationField::new('firstStageModel', 'Erste Stufe');
            yield AssociationField::new('primarySecondStageModel', 'Zweite Stufe');
            yield ChoiceField::new('conditionStatus', 'Zustand')->setChoices([
                'Einsatzbereit' => 'ready', 'Wartung' => 'maintenance',
                'Beschädigt' => 'damaged',
            ]);
			yield MoneyField::new('rentalFee','Leihgebuehr')->setCurrency('EUR')->setStoredAsCents(false);
            yield BooleanField::new('lendable', 'Verleihfreigabe');
            yield DateField::new('nextServiceAt', 'Nächste Revision')->setFormat('dd.MM.yyyy');
            return;
        }

	  	yield FormField::addFieldset('Inventar');
	  	yield AssociationField::new('club','Verein')->setRequired(true);
	  	yield TextField::new('inventoryNumber','Inventarnummer')->setRequired(true);
	  	yield AssociationField::new('manufacturer','Hersteller')->setRequired(true)
			->setFormTypeOption('attr', ['data-manufacturer-options-url' => $this->generateUrl('admin_equipment_manufacturer_options')]);
	 	yield FormField::addFieldset('Erste Stufe');
	 	yield AssociationField::new('firstStageModel','Modell 1. Stufe')->setRequired(true);
	 	yield TextField::new('firstStageSerial','Seriennummer 1. Stufe')->setRequired(true);
	 	yield FormField::addFieldset('Zweite Stufen');
		yield AssociationField::new('primarySecondStageModel','Modell 2. Stufe (primaer)')->setRequired(true);
		yield TextField::new('primarySecondStageSerial','Seriennummer 2. Stufe (primaer)');
		yield AssociationField::new('secondarySecondStageModel','Modell 2. Stufe (optional)');
		yield TextField::new('secondarySecondStageSerial','Seriennummer 2. Stufe (optional)');
		yield FormField::addFieldset('Verleih');
		yield ChoiceField::new('conditionStatus','Zustand')->setChoices(['Einsatzbereit'=>'ready','Wartung'=>'maintenance','Beschaedigt'=>'damaged']);
		yield BooleanField::new('lendable','Verleihfreigabe');
		yield MoneyField::new('rentalFee','Leihgebuehr')->setCurrency('EUR')->setStoredAsCents(false);
		yield DateField::new('nextServiceAt','Naechste Revision');
		yield TextareaField::new('notes','Notizen')->hideOnIndex();
	}
}
