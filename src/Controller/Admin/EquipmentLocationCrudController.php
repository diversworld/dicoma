<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentLocation;
use App\Service\AdminClubContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_EQUIPMENT_MANAGER')]
final class EquipmentLocationCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminClubContext $clubs) {}
    public static function getEntityFqcn(): string { return EquipmentLocation::class; }

    public function createEntity(string $entityFqcn): EquipmentLocation
    {
        return new EquipmentLocation($this->clubs->getClub() ?? throw $this->createAccessDeniedException());
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $club = $this->clubs->getClub();
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere($club === null ? '1 = 0' : 'entity.club = :club')
            ->setParameter('club', $club);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Lagerort')->collapsible();
        yield TextField::new('name', 'Bezeichnung')->setColumns(8);
        yield BooleanField::new('active', 'Aktiv')->setColumns(4);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Lagerort')->setEntityLabelInPlural('Lagerorte')
            ->setFormOptions(['csrf_token_id' => 'equipment_location_'.$this->clubs->getClub()?->getId()]);
    }
}
