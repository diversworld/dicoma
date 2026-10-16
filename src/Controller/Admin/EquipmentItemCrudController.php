<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentItem;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class EquipmentItemCrudController extends AbstractCrudController
{
    /** Keep the legacy string in sync only when an explicit model was selected. */
    private function synchronizeSelectedModel(EquipmentItem $item): void
    {
        $reference = $item->getModelReference();
        if ($reference !== null) {
            $item->setModel($reference->getName());
        }
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof EquipmentItem) {
            $this->synchronizeSelectedModel($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof EquipmentItem) {
            $this->synchronizeSelectedModel($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public static function getEntityFqcn(): string
    {
        return EquipmentItem::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets
            ->addJsFile('js/equipment-attributes.js?v=78b1')
            ->addJsFile('js/equipment-inventory-options.js?v=86fix2');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Verleihgegenstand')
            ->setEntityLabelInPlural('Ausrüstungsinventar')
            ->setPageTitle(Crud::PAGE_NEW, 'Verleihgegenstand erfassen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Verleihgegenstand bearbeiten')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Inventardetails')
            ->setDefaultSort(['inventoryNumber' => 'ASC'])
            ->setSearchFields(['inventoryNumber', 'name', 'model', 'serialNumber']);
    }

    public function configureFields(string $pageName): iterable
    {
        // DiCoMa STEP85 INDEX: dedicated, consistent overview columns

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventoryNumber', 'Inventarnummer');
            yield TextField::new('name', 'Bezeichnung');
            yield AssociationField::new('category', 'Ausrüstungsart');
            yield AssociationField::new('manufacturer', 'Hersteller');
            yield TextField::new('displayModel', 'Modell');
            yield AssociationField::new('size', 'Größe');
            yield ChoiceField::new('conditionStatus', 'Zustand')->setChoices([
                'Einsatzbereit' => 'ready', 'Wartung' => 'maintenance',
                'Beschädigt' => 'damaged', 'Ausgemustert' => 'retired',
            ]);
            yield BooleanField::new('lendable', 'Verleihfreigabe');
            return;
        }

        yield FormField::addFieldset('Zuordnung und Identifikation');
        yield AssociationField::new('club', 'Verein')
            ->setRequired(true)->setColumns(6);
        yield AssociationField::new('category', 'Ausrüstungsart')
            ->setRequired(true)->setColumns(6)
            ->setFormTypeOption('attr', [
                'data-inventory-options-url' => $this->generateUrl('admin_equipment_inventory_options'),
            ]);
        yield TextField::new('inventoryNumber', 'Inventarnummer')
            ->setRequired(true)->setColumns(4)
            ->setHelp('Eindeutige Nummer für genau diesen Gegenstand innerhalb des Vereins.');
        yield TextField::new('name', 'Bezeichnung')
            ->setRequired(true)->setColumns(8);
        yield TextField::new('displayModel', 'Modell')->onlyOnIndex();


        yield FormField::addFieldset('Ausrüstungsdetails')->collapsible();
        yield AssociationField::new('subType', 'Unterart')->setRequired(false)->setColumns(4);
        yield AssociationField::new('manufacturer', 'Hersteller')->setRequired(false)->setColumns(4)
            ->setFormTypeOption('attr', ['data-manufacturer-options-url' => $this->generateUrl('admin_equipment_manufacturer_options')]);
        yield AssociationField::new('modelReference', 'Modell aus Einstellungen')->setRequired(false)->setColumns(4);
        yield TextField::new('displayModel', 'Modellbezeichnung')->onlyOnDetail();
        yield TextField::new('model', 'Bisheriger Freitextwert')->onlyOnDetail()
            ->setHelp('Altbestand; wird bei Auswahl eines Modells aus den Einstellungen automatisch synchronisiert.');
        yield AssociationField::new('size', 'Größe')->setRequired(false)->setColumns(4);
        yield TextField::new('color', 'Farbe')->setColumns(4)->hideOnIndex();
        yield TextField::new('serialNumber', 'Seriennummer')->setColumns(4)->hideOnIndex();
        yield DateField::new('purchasedAt', 'Kaufdatum')->setColumns(4)->hideOnIndex();

        yield FormField::addFieldset('Verleih und Zustand')->collapsible();
        yield ChoiceField::new('conditionStatus', 'Zustand')->setColumns(4)
            ->setChoices([
                'Einsatzbereit' => 'ready',
                'Wartung' => 'maintenance',
                'Beschädigt' => 'damaged',
                'Ausgemustert' => 'retired',
            ]);
        yield BooleanField::new('lendable', 'Zum Verleih freigegeben')->setColumns(4);
        yield MoneyField::new('rentalFee', 'Leihgebühr')
            ->setCurrency('EUR')->setStoredAsCents(false)->setColumns(4);

        yield FormField::addFieldset('Zusatzmerkmale und Notizen')->collapsible();
        yield TextareaField::new('attributeValuesJson', 'Kategorie-Merkmale')
            ->onlyOnForms()->setColumns(12)
            ->setFormTypeOption('attr', [
                'data-equipment-schema-url' => $this->generateUrl('admin_equipment_attribute_schema'),
            ])
            ->setHelp('Kategorieabhängige Eingabefelder werden automatisch geladen. JSON bleibt als Fallback ohne JavaScript verfügbar.');
        yield ArrayField::new('attributeValues', 'Merkmalswerte')->onlyOnDetail();
        yield TextareaField::new('notes', 'Notizen')->setColumns(12)->hideOnIndex();
    }
}
