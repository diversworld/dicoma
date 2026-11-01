<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentRegulatorDetails;
use App\Entity\EquipmentItem;
use App\Service\AdminEquipmentScope;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class EquipmentRegulatorDetailsCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminEquipmentScope $scope, private readonly EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string { return EquipmentRegulatorDetails::class; }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->join('entity.equipmentItem', 'regulatorItem')
            ->andWhere('regulatorItem.club = :club')->setParameter('club', $this->scope->club());
    }

    private function assertDetails(EquipmentRegulatorDetails $details): void
    {
        $this->scope->assertAsset($details->getEquipmentItem());
    }

    public function edit(AdminContext $context)
    {
        $this->assertDetails($context->getEntity()->getInstance());
        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $this->assertDetails($context->getEntity()->getInstance());
        return parent::detail($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertDetails($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $item = $original['equipmentItem'] ?? null;
        $this->scope->assertAsset($item instanceof EquipmentItem ? $item : null);
        $this->assertDetails($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertDetails($entityInstance);
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== EquipmentRegulatorDetails::class) {
            throw new BadRequestHttpException('Ungültiger Ausrüstungs-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Ausrüstungs-ID.');
            }
            $details = $this->em->find(EquipmentRegulatorDetails::class, (int) $id);
            if ($details !== null) {
                $this->assertDetails($details);
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setFormOptions(['csrf_token_id' => 'equipment_regulator_details_'.$this->scope->club()->getId()])
            ->setEntityLabelInSingular('Atemregler-Technik')
            ->setEntityLabelInPlural('Atemregler-Technikdaten');
    }
    public function configureFields(string $pageName): iterable
    {
        $club = $this->scope->club();
        yield AssociationField::new('equipmentItem', 'Inventargegenstand')
            ->setFormTypeOption('query_builder', static fn (EntityRepository $repository): QueryBuilder =>
                $repository->createQueryBuilder('item')->where('item.club = :club')->setParameter('club', $club))
            ->setRequired(true);
        yield TextField::new('firstStageSerial', 'Seriennummer 1. Stufe')->setRequired(true);
        yield TextField::new('firstStageModel', 'Modell 1. Stufe');
        yield TextField::new('primarySecondStageSerial', 'Seriennummer 2. Stufe (primaer)')->setRequired(true);
        yield TextField::new('primarySecondStageModel', 'Modell 2. Stufe (primaer)');
        yield TextField::new('secondarySecondStageSerial', 'Seriennummer 2. Stufe (sekundaer)');
        yield TextField::new('secondarySecondStageModel', 'Modell 2. Stufe (sekundaer)');
        yield DateField::new('nextServiceAt', 'Naechste Revision');
    }
}
