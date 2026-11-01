<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentItem;
use App\Entity\Club;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[IsGranted('ROLE_SUPER_ADMIN')]
class EquipmentItemCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminClubContext $clubContext, private readonly EntityManagerInterface $em) {}

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $club = $this->clubContext->getClub();
        return $club === null ? $query->andWhere('1 = 0')
            : $query->andWhere('entity.club = :equipmentClub')->setParameter('equipmentClub', $club);
    }

    private function assertClub(?Club $club): void
    {
        $id = $this->clubContext->getClub()?->getId();
        if ($id === null || $club?->getId() !== $id) {
            throw $this->createAccessDeniedException('Ausrüstung gehört nicht zum ausgewählten Verein.');
        }
    }

    public function createEntity(string $entityFqcn): EquipmentItem
    {
        return (new EquipmentItem())->setClub($this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Bitte zuerst einen Verein auswählen.'));
    }

    public function edit(AdminContext $context)
    {
        $this->assertClub($context->getEntity()->getInstance()->getClub());
        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $this->assertClub($context->getEntity()->getInstance()->getClub());
        return parent::detail($context);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof EquipmentItem) {
            $this->assertClub($entityInstance->getClub());
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== EquipmentItem::class) {
            throw new BadRequestHttpException('Ungültiger Ausrüstungs-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Ausrüstungs-ID.');
            }
            $item = $this->em->find(EquipmentItem::class, (int) $id);
            if ($item !== null) {
                $this->assertClub($item->getClub());
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

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
            $this->assertClub($entityInstance->getClub());
            $this->synchronizeSelectedModel($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof EquipmentItem) {
            $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $club = $original['club'] ?? null;
            $this->assertClub($club instanceof Club ? $club : null);
            $this->assertClub($entityInstance->getClub());
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
            ->setFormOptions(['csrf_token_id' => 'equipment_item_crud_'.($this->clubContext->getClub()?->getId() ?? 'none')])
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
        $clubId = $this->clubContext->getClub()?->getId();
        $clubChoices = static function (EntityRepository $repository) use ($clubId): QueryBuilder {
            $query = $repository->createQueryBuilder('equipmentChoice');
            return $clubId === null ? $query->andWhere('1 = 0')
                : $query->andWhere('equipmentChoice.club = :club')->setParameter('club', $clubId);
        };
        // DiCoMa STEP85 INDEX: dedicated, consistent overview columns

        if ($pageName === Crud::PAGE_INDEX) {
            yield TextField::new('inventoryNumber', 'Inventarnummer');
            yield TextField::new('name', 'Bezeichnung');
            yield AssociationField::new('category', 'Ausrüstungsart');
            yield AssociationField::new('manufacturer', 'Hersteller');
            yield TextField::new('displayModel', 'Modell');
            yield AssociationField::new('size', 'Größe');
			 yield MoneyField::new('rentalFee', 'Leihgebühr')
            ->setCurrency('EUR')->setStoredAsCents(false);
            yield ChoiceField::new('conditionStatus', 'Zustand')->setChoices([
                'Einsatzbereit' => 'ready', 'Wartung' => 'maintenance',
                'Beschädigt' => 'damaged', 'Ausgemustert' => 'retired',
            ]);
            yield BooleanField::new('lendable', 'Verleihfreigabe');
            return;
        }

        yield FormField::addFieldset('Zuordnung und Identifikation');
        yield AssociationField::new('club', 'Verein')
            ->setFormTypeOption('choices', array_filter([$this->clubContext->getClub()]))
            ->setFormTypeOption('disabled', true)->setRequired(true)->setColumns(6);
        yield AssociationField::new('category', 'Ausrüstungsart')
            ->setFormTypeOption('query_builder', $clubChoices)
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
        yield AssociationField::new('subType', 'Unterart')->setFormTypeOption('query_builder', $clubChoices)->setRequired(false)->setColumns(4);
        yield AssociationField::new('manufacturer', 'Hersteller')->setRequired(false)->setColumns(4)
            ->setFormTypeOption('query_builder', $clubChoices)
            ->setFormTypeOption('attr', ['data-manufacturer-options-url' => $this->generateUrl('admin_equipment_manufacturer_options')]);
        yield AssociationField::new('modelReference', 'Modell aus Einstellungen')->setFormTypeOption('query_builder', $clubChoices)->setRequired(false)->setColumns(4);
        yield TextField::new('displayModel', 'Modellbezeichnung')->onlyOnDetail();
        yield TextField::new('model', 'Bisheriger Freitextwert')->onlyOnDetail()
            ->setHelp('Altbestand; wird bei Auswahl eines Modells aus den Einstellungen automatisch synchronisiert.');
        yield AssociationField::new('size', 'Größe')->setFormTypeOption('query_builder', $clubChoices)->setRequired(false)->setColumns(4);
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
