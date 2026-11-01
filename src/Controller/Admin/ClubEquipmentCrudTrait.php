<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Entity\Regulator;
use App\Entity\RegulatorModel;
use App\Entity\Tank;
use App\Service\AdminEquipmentScope;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

trait ClubEquipmentCrudTrait
{
    private AdminEquipmentScope $clubEquipmentScope;
    private EntityManagerInterface $clubEquipmentEm;

    #[Required]
    public function setClubEquipmentScope(AdminEquipmentScope $scope, EntityManagerInterface $em): void
    {
        $this->clubEquipmentScope = $scope;
        $this->clubEquipmentEm = $em;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.club = :selectedClub')->setParameter('selectedClub', $this->clubEquipmentScope->club());
        if (static::getEntityFqcn() === RegulatorModel::class) {
            $query->join('entity.manufacturer', 'scopedManufacturer')
                ->andWhere('scopedManufacturer.club = :selectedClub');
        } elseif (static::getEntityFqcn() === Regulator::class) {
            $query->join('entity.manufacturer', 'scopedManufacturer')
                ->join('entity.firstStageModel', 'scopedFirst')
                ->join('entity.primarySecondStageModel', 'scopedSecond')
                ->leftJoin('entity.secondarySecondStageModel', 'scopedOptional')
                ->andWhere('scopedManufacturer.club = :selectedClub')
                ->andWhere('scopedFirst.club = :selectedClub AND scopedFirst.manufacturer = scopedManufacturer')
                ->andWhere('scopedSecond.club = :selectedClub AND scopedSecond.manufacturer = scopedManufacturer')
                ->andWhere('(scopedOptional.id IS NULL OR (scopedOptional.club = :selectedClub AND scopedOptional.manufacturer = scopedManufacturer))');
        }
        return $query;
    }

    public function createEntity(string $entityFqcn): Tank|Regulator|RegulatorModel
    {
        if ($entityFqcn !== static::getEntityFqcn()) {
            throw new \LogicException('Ungültiger Ausrüstungstyp.');
        }
        $entity = new $entityFqcn();
        if (!$entity instanceof Tank && !$entity instanceof Regulator && !$entity instanceof RegulatorModel) {
            throw new \LogicException('Ungültiger Ausrüstungstyp.');
        }
        return $entity->setClub($this->clubEquipmentScope->club());
    }

    private function assertClubEquipment(object $entity): void
    {
        if (!$entity instanceof Tank && !$entity instanceof Regulator && !$entity instanceof RegulatorModel) {
            throw new BadRequestHttpException('Ungültiger Ausrüstungstyp.');
        }
        $this->clubEquipmentScope->assertClub($entity->getClub());
        if ($entity instanceof RegulatorModel) {
            $this->clubEquipmentScope->assertClub($entity->getManufacturer()?->getClub());
        } elseif ($entity instanceof Regulator) {
            $manufacturer = $entity->getManufacturer();
            $this->clubEquipmentScope->assertClub($manufacturer?->getClub());
            foreach ([$entity->getFirstStageModel(), $entity->getPrimarySecondStageModel(), $entity->getSecondarySecondStageModel()] as $model) {
                if ($model === null) {
                    continue;
                }
                $this->clubEquipmentScope->assertClub($model->getClub());
                $this->clubEquipmentScope->assertClub($model->getManufacturer()?->getClub());
                if ($manufacturer?->getId() !== $model->getManufacturer()?->getId()) {
                    throw $this->createAccessDeniedException('Atemreglermodell gehört nicht zum Hersteller.');
                }
            }
        }
    }

    public function edit(AdminContext $context)
    {
        $this->assertClubEquipment($context->getEntity()->getInstance());
        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $this->assertClubEquipment($context->getEntity()->getInstance());
        return parent::detail($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertClubEquipment($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $club = $original['club'] ?? null;
        $this->clubEquipmentScope->assertClub($club instanceof Club ? $club : null);
        $this->assertClubEquipment($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertClubEquipment($entityInstance);
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
            $entity = $this->clubEquipmentEm->find(static::getEntityFqcn(), (int) $id);
            if ($entity !== null) {
                $this->assertClubEquipment($entity);
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }
}
