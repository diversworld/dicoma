<?php

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class ClubCrudController extends AbstractCrudController
{
    public function __construct(private readonly SingleClubMode $singleClub) {}

    public static function getEntityFqcn(): string
    {
        return Club::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->singleClub->isEnabled()
            ? $actions->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE)
            : $actions;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        if ($this->singleClub->isEnabled()) {
            $query->andWhere('entity.id = :singleClubId')->setParameter('singleClubId', $this->singleClub->club()->getId());
        }
        return $query;
    }

    public function detail(AdminContext $context)
    {
        $this->assertClub($context->getEntity()->getInstance());
        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->assertClub($context->getEntity()->getInstance());
        return parent::edit($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($this->singleClub->isEnabled()) {
            throw $this->createAccessDeniedException('Im Einzelvereinsbetrieb können keine weiteren Vereine angelegt werden.');
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertClub($entityInstance);
        if ($this->singleClub->isEnabled() && $entityInstance instanceof Club && !$entityInstance->isActive()) {
            throw $this->createAccessDeniedException('Der konfigurierte Verein muss aktiv bleiben.');
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function assertClub(mixed $club): void
    {
        if ($this->singleClub->isEnabled()
            && (!$club instanceof Club || $club->getId() !== $this->singleClub->club()->getId())) {
            throw $this->createAccessDeniedException('Dieser Verein ist im Einzelvereinsbetrieb nicht verfügbar.');
        }
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Verein')
            ->setEntityLabelInPlural('Vereine')
            ->setPageTitle(Crud::PAGE_INDEX, 'Vereine')
            ->setPageTitle(Crud::PAGE_NEW, 'Verein anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Verein bearbeiten')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Vereinsdetails')
            ->setDefaultSort([
                'name' => 'ASC',
            ])
            ->setSearchFields([
                'name',
                'shortName',
                'association',
                'clubNumber',
                'city',
                'email',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield FormField::addFieldset('Verein');

        yield TextField::new('name', 'Vereinsname')
            ->setRequired(true)
            ->setColumns(6);

        yield TextField::new('shortName', 'Kurzname')
            ->setColumns(3);

        $active = BooleanField::new('active', 'Aktiv')->setColumns(3);
        if ($this->singleClub->isEnabled()) {
            $active->setFormTypeOption('disabled', true)->renderAsSwitch(false);
        }
        yield $active;

        yield TextField::new('association', 'Verband')
            ->setHelp('z. B. VDST, DOSB-Landesverband oder anderer Dachverband')
            ->setColumns(4);

        yield TextField::new('clubNumber', 'Vereins-/Verbandsnummer')
            ->setColumns(4);

        yield IntegerField::new('members.count', 'Mitglieder')
            ->onlyOnIndex();

        yield FormField::addFieldset('Kontakt');

        yield EmailField::new('email', 'E-Mail')
            ->setColumns(4);

        yield TelephoneField::new('phone', 'Telefon')
            ->setColumns(4);

        yield UrlField::new('website', 'Website')
            ->setColumns(4);

        yield FormField::addFieldset('Anschrift');

        yield TextField::new('street', 'Straße / Hausnummer')
            ->setColumns(6);

        yield TextField::new('postalCode', 'PLZ')
            ->setColumns(2);

        yield TextField::new('city', 'Ort')
            ->setColumns(4);

        yield CountryField::new('country', 'Land')
            ->setColumns(4);

        yield FormField::addFieldset('Weitere Informationen');

        yield TextareaField::new('description', 'Beschreibung')
            ->hideOnIndex();

        yield TextareaField::new('notes', 'Interne Notizen')
            ->hideOnIndex();

        yield TextField::new('createdAt', 'Erstellt')
            ->onlyOnDetail()
            ->formatValue(
                static fn ($value) =>
                    $value instanceof \DateTimeInterface
                        ? $value->format('d.m.Y H:i')
                        : ''
            );

        yield TextField::new('updatedAt', 'Geändert')
            ->onlyOnDetail()
            ->formatValue(
                static fn ($value) =>
                    $value instanceof \DateTimeInterface
                        ? $value->format('d.m.Y H:i')
                        : ''
            );
    }
}