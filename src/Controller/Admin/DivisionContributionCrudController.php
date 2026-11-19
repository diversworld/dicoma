<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DivisionContribution;
use App\Entity\Sport;
use App\Service\AdminClubContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class DivisionContributionCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminClubContext $clubs) {}
    public static function getEntityFqcn(): string { return DivisionContribution::class; }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $club = $this->clubs->getClub();
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->join('entity.division', 'division')
            ->andWhere('division.club = :club')->setParameter('club', $club);
    }

    public function createEntity(string $entityFqcn): DivisionContribution
    {
        $club = $this->clubs->getClub() ?? throw $this->createAccessDeniedException();
        $division = $this->container->get('doctrine')->getRepository(Sport::class)->findOneBy(['club' => $club, 'active' => true])
            ?? throw $this->createAccessDeniedException('Zuerst eine aktive Sparte anlegen.');
        return new DivisionContribution($division, (int) date('Y'), 0);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Spartenzuschlag')->collapsible();
        yield AssociationField::new('division', 'Sparte')
            ->setFormTypeOption('query_builder', fn ($repository) => $repository->createQueryBuilder('division')
                ->where('division.club = :club')->andWhere('division.active = true')->setParameter('club', $this->clubs->getClub()))
            ->setColumns(6);
        yield IntegerField::new('year', 'Jahr')->setColumns(2);
        yield MoneyField::new('annualCents', 'Zuschlag pro Jahr')->setStoredAsCents()->setCurrency('EUR')->setColumns(2);
        yield BooleanField::new('active', 'Aktiv')->setColumns(2);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Spartenzuschlag')->setEntityLabelInPlural('Spartenzuschläge');
    }
}
