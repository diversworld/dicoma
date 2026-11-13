<?php

namespace App\Controller\Admin;

use App\Entity\Club;
use App\Service\SingleClubMode;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
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
    public function __construct(
        private readonly SingleClubMode $singleClub,
        private readonly EntityManagerInterface $em,
    ) {}

    public static function getEntityFqcn(): string
    {
        return Club::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions->disable(Action::DELETE, Action::BATCH_DELETE);
        return $this->em->getRepository(Club::class)->count([]) === 0
            ? $actions
            : $actions->disable(Action::NEW);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $club = $this->singleClub->optionalClub();
        $query->andWhere('entity.id = :singleClubId')->setParameter('singleClubId', $club?->getId() ?? 0);
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
        if (!$entityInstance instanceof Club || !$entityInstance->isActive()) {
            throw $this->createAccessDeniedException('Ein aktiver Verein ist erforderlich.');
        }
        $connection = $entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();
        if (!$platform instanceof AbstractMySQLPlatform && !$platform instanceof SQLitePlatform) {
            throw new \LogicException('Die Vereinsanlage wird auf dieser Datenbankplattform nicht unterstützt.');
        }
        $lockName = 'dicoma_club_'.substr(hash('sha256', (string) $connection->getDatabase()), 0, 40);
        if ($platform instanceof AbstractMySQLPlatform && (int) $connection->fetchOne('SELECT GET_LOCK(?, 5)', [$lockName]) !== 1) {
            throw new \RuntimeException('Vereinsanlage ist gesperrt. Bitte erneut versuchen.');
        }
        try {
            $connection->transactional(function () use ($entityManager, $entityInstance): void {
                if ((int) $entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM club') !== 0) {
                    throw $this->createAccessDeniedException('Es können keine weiteren Vereine angelegt werden.');
                }
                parent::persistEntity($entityManager, $entityInstance);
            });
        } finally {
            if ($platform instanceof AbstractMySQLPlatform && (int) $connection->fetchOne('SELECT RELEASE_LOCK(?)', [$lockName]) !== 1) {
                throw new \RuntimeException('Sperre der Vereinsanlage konnte nicht freigegeben werden.');
            }
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->assertClub($entityInstance);
        if ($entityInstance instanceof Club && !$entityInstance->isActive()) {
            throw $this->createAccessDeniedException('Der konfigurierte Verein muss aktiv bleiben.');
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function assertClub(mixed $club): void
    {
        if (!$club instanceof Club || $club->getId() !== $this->singleClub->club()->getId()) {
            throw $this->createAccessDeniedException('Dieser Verein ist im Einzelvereinsbetrieb nicht verfügbar.');
        }
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Verein')
            ->setEntityLabelInPlural('Vereinsdaten')
            ->setPageTitle(Crud::PAGE_INDEX, 'Vereinsdaten')
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
            ->setHelp('Für die öffentliche Website muss PUBLIC_SITE_CLUB_ID auf diesen Verein gesetzt werden.')
            ->setColumns(6);

        yield TextField::new('shortName', 'Kurzname')
            ->setColumns(3);

        $active = BooleanField::new('active', 'Aktiv')->setColumns(3);
        $active->setFormTypeOption('disabled', true)->renderAsSwitch(false);
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