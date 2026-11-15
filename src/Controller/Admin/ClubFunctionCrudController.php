<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ClubFunction;
use App\Service\AdminClubContext;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class ClubFunctionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminClubContext $clubContext,
    ) {}

    public static function getEntityFqcn(): string { return ClubFunction::class; }

    public function configureCrud(Crud $crud): Crud
    {
        $crud = $crud->setEntityLabelInSingular('Vereinsfunktion')
            ->setEntityLabelInPlural('Vereinsfunktionen')
            ->setPageTitle(Crud::PAGE_INDEX, 'Vereinsfunktionen verwalten')
            ->overrideTemplate('crud/index', 'admin/roles/function_index.html.twig')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name']);
        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, Action::new('functionCreate', 'Funktion anlegen')
                ->linkToRoute('admin_function_definition_new')->createAsGlobalAction())
            ->add(Crud::PAGE_INDEX, Action::new('functionEdit', 'Bearbeiten')
                ->linkToRoute('admin_function_definition_edit', static fn (ClubFunction $function): array => ['id' => $function->getId()]));
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'Funktion');
        yield TextField::new('name', 'Berechtigungen')->setSortable(false)
            ->formatValue(static fn (mixed $value, ClubFunction $function): string => htmlspecialchars(implode(', ', $function->getPermissions()), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        yield BooleanField::new('successorRequired', 'Nachfolge erforderlich')->renderAsSwitch(false);
        yield DateField::new('startsAt', 'Gültig ab');
        yield DateField::new('endsAt', 'Gültig bis');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $club = $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.club = :functionClub')->setParameter('functionClub', $club);
    }
}
