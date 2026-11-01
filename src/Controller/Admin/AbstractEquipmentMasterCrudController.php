<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Entity\EquipmentAttribute;
use App\Entity\EquipmentCategory;
use App\Entity\EquipmentManufacturer;
use App\Entity\EquipmentModel;
use App\Entity\EquipmentSize;
use App\Entity\EquipmentSubType;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class AbstractEquipmentMasterCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminClubContext $clubContext,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $club = $this->clubContext->getClub();
        if ($club === null) {
            return $query->andWhere('1 = 0');
        }
        if (static::getEntityFqcn() === EquipmentAttribute::class) {
            $query->join('entity.category', 'masterCategory')->andWhere('masterCategory.club = :masterClub');
        } else {
            $query->andWhere('entity.club = :masterClub');
            if (in_array(static::getEntityFqcn(), [EquipmentSubType::class, EquipmentSize::class, EquipmentModel::class], true)) {
                $query->join('entity.category', 'masterCategory')->andWhere('masterCategory.club = :masterClub');
            }
            if (static::getEntityFqcn() === EquipmentModel::class) {
                $query->join('entity.manufacturer', 'masterManufacturer')->andWhere('masterManufacturer.club = :masterClub');
            } elseif (static::getEntityFqcn() === EquipmentSize::class) {
                $query->leftJoin('entity.manufacturer', 'masterManufacturer')
                    ->andWhere('masterManufacturer.id IS NULL OR masterManufacturer.club = :masterClub');
            }
        }
        return $query->setParameter('masterClub', $club);
    }

    protected function configureMasterCrud(Crud $crud): Crud
    {
        return $crud->setFormOptions(['csrf_token_id' => 'equipment_master_'.static::getEntityFqcn().'_'.($this->clubContext->getClub()?->getId() ?? 'none')]);
    }

    protected function clubField(): AssociationField
    {
        return AssociationField::new('club', 'Verein')
            ->setFormTypeOption('choices', array_filter([$this->clubContext->getClub()]))
            ->setFormTypeOption('disabled', true)->setRequired(true);
    }

    protected function scopedField(string $property, string $label): AssociationField
    {
        $clubId = $this->clubContext->getClub()?->getId();
        return AssociationField::new($property, $label)->setFormTypeOption('query_builder',
            static function (EntityRepository $repository) use ($clubId): QueryBuilder {
                $query = $repository->createQueryBuilder('choice');
                return $clubId === null ? $query->andWhere('1 = 0')
                    : $query->andWhere('choice.club = :club')->setParameter('club', $clubId);
            });
    }

    public function createEntity(string $entityFqcn)
    {
        $entity = parent::createEntity($entityFqcn);
        if (!$entity instanceof EquipmentAttribute) {
            $entity->setClub($this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Bitte zuerst einen Verein auswählen.'));
        }
        return $entity;
    }

    private function assertClub(?Club $club): void
    {
        $id = $this->clubContext->getClub()?->getId();
        if ($id === null || $club?->getId() !== $id) {
            throw $this->createAccessDeniedException('Ausrüstung gehört nicht zum ausgewählten Verein.');
        }
    }

    private function assertMaster(object $entity): void
    {
        if ($entity instanceof EquipmentAttribute) {
            $this->assertClub($entity->getCategory()?->getClub());
            return;
        }
        if (!$entity instanceof EquipmentCategory && !$entity instanceof EquipmentManufacturer
            && !$entity instanceof EquipmentSubType && !$entity instanceof EquipmentSize && !$entity instanceof EquipmentModel) {
            throw new \InvalidArgumentException('Ungültige Ausrüstungsstammdaten.');
        }
        $this->assertClub($entity->getClub());
        if ($entity instanceof EquipmentSubType || $entity instanceof EquipmentSize || $entity instanceof EquipmentModel) {
            $this->assertClub($entity->getCategory()?->getClub());
        }
        if ($entity instanceof EquipmentModel || ($entity instanceof EquipmentSize && $entity->getManufacturer() !== null)) {
            $this->assertClub($entity->getManufacturer()?->getClub());
        }
    }

    public function edit(AdminContext $context)
    {
        $this->assertMaster($context->getEntity()->getInstance());
        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $this->assertMaster($context->getEntity()->getInstance());
        return parent::detail($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertMaster($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        if ($entityInstance instanceof EquipmentAttribute) {
            $category = $original['category'] ?? null;
            $this->assertClub($category instanceof EquipmentCategory ? $category->getClub() : null);
        } else {
            $club = $original['club'] ?? null;
            $this->assertClub($club instanceof Club ? $club : null);
        }
        $this->assertMaster($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertMaster($entityInstance);
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== static::getEntityFqcn()) {
            throw new BadRequestHttpException('Ungültiger Ausrüstungs-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Ausrüstungs-ID.');
            }
            $entity = $this->entityManager->find(static::getEntityFqcn(), (int) $id);
            if ($entity !== null) {
                $this->assertMaster($entity);
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }
}
