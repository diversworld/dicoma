<?php

namespace App\Controller\Admin;

use App\Entity\Sport;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[IsGranted('ROLE_ADMIN')]
class SportCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminClubContext $clubContext, private readonly EntityManagerInterface $em) {}

    public static function getEntityFqcn(): string
    {
        return Sport::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $club = $this->clubContext->getClub();
        if ($club === null) {
            return $query->andWhere('1 = 0');
        }

        return $query->andWhere('entity.club = :sportClub')->setParameter('sportClub', $club);
    }

    public function createEntity(string $entityFqcn): Sport
    {
        $club = $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Bitte zuerst einen Verein auswählen.');
        return (new Sport())->setClub($club);
    }

    public function detail(AdminContext $context)
    {
        $this->assertSportContext($context->getEntity()->getInstance());
        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->assertSportContext($context->getEntity()->getInstance());
        if ($context->getRequest()->isXmlHttpRequest() && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Nur Superadmins dürfen den Aktiv-Status ändern.');
        }
        return parent::edit($context);
    }

    public function delete(AdminContext $context)
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->assertSportContext($context->getEntity()->getInstance());
        return parent::delete($context);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        if ($batchActionDto->getEntityFqcn() !== Sport::class) {
            throw $this->createAccessDeniedException('Ungültiger Sportarten-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Sportarten-ID.');
            }
            $sport = $this->em->find(Sport::class, (int) $id);
            if ($sport !== null) {
                $this->assertSportContext($sport);
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertSportContext($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertSportContext($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $this->assertSportContext($entityInstance);
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function configureActions(Actions $actions): Actions
    {
        $functions = Action::new('functions', 'Funktionen', 'fa fa-users')
            ->linkToRoute('admin_division_functions', static fn (Sport $sport): array => ['id' => $sport->getId()]);
        $actions->add(Crud::PAGE_INDEX, $functions);
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $actions->disable(Action::DELETE, Action::BATCH_DELETE);
        }

        return $actions;
    }

    private function assertSportContext(mixed $sport): void
    {
        $selectedId = $this->clubContext->getClub()?->getId();
        if (!$sport instanceof Sport || $selectedId === null || $sport->getClub()?->getId() !== $selectedId) {
            throw $this->createAccessDeniedException('Sportart gehört nicht zum ausgewählten Verein.');
        }
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Sportart')
            ->setEntityLabelInPlural('Sportarten')
            ->setPageTitle(Crud::PAGE_INDEX, 'Sportarten')
            ->setPageTitle(Crud::PAGE_NEW, 'Sportart anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Sportart bearbeiten')
            ->setDefaultSort([
                'sortOrder' => 'ASC',
                'name' => 'ASC',
            ])
            ->setSearchFields([
                'name',
                'shortName',
                'club.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Sportart');

        yield AssociationField::new('club', 'Verein')
            ->setRequired(true)
            ->setColumns(4)
            ->hideOnForm();

        yield TextField::new('name', 'Bezeichnung')
            ->setRequired(true)
            ->setColumns(4);

        yield TextField::new('shortName', 'Kurzbezeichnung')
            ->setColumns(2);

        yield IntegerField::new('sortOrder', 'Sortierung')
            ->setColumns(2);

        $active = BooleanField::new('active', 'Aktiv')->setColumns(2);
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $active->hideOnForm()->renderAsSwitch(false);
        }
        yield $active;

        yield TextareaField::new('description', 'Beschreibung')
            ->hideOnIndex();
    }
}