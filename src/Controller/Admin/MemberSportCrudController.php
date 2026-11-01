<?php

namespace App\Controller\Admin;

use App\Entity\MemberSport;
use App\Entity\Member;
use App\Enum\MemberSportStatus;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use App\Entity\Sport;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class MemberSportCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminClubContext $clubContext, private readonly EntityManagerInterface $em) {}

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $query = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $club = $this->clubContext->getClub();
        if ($club === null) {
            return $query->andWhere('1 = 0');
        }
        return $query->join('entity.member', 'managedMember')
            ->join('entity.sport', 'managedSport')
            ->andWhere('managedMember.club = :managedClub AND managedSport.club = :managedClub')
            ->setParameter('managedClub', $club);
    }

    private function assertClub(?Member $member, ?Sport $sport): void
    {
        $id = $this->clubContext->getClub()?->getId();
        if ($id === null || $member?->getClub()?->getId() !== $id || $sport?->getClub()?->getId() !== $id) {
            throw $this->createAccessDeniedException('Sportzuordnung gehört nicht zum ausgewählten Verein.');
        }
    }

    public function edit(AdminContext $context)
    {
        $record = $context->getEntity()->getInstance();
        $this->assertClub($record->getMember(), $record->getSport());
        return parent::edit($context);
    }

    public function detail(AdminContext $context)
    {
        $record = $context->getEntity()->getInstance();
        $this->assertClub($record->getMember(), $record->getSport());
        return parent::detail($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MemberSport) {
            $this->assertClub($entityInstance->getMember(), $entityInstance->getSport());
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MemberSport) {
            $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $member = $original['member'] ?? null;
            $sport = $original['sport'] ?? null;
            $this->assertClub($member instanceof Member ? $member : null, $sport instanceof Sport ? $sport : null);
            $this->assertClub($entityInstance->getMember(), $entityInstance->getSport());
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MemberSport) {
            $this->assertClub($entityInstance->getMember(), $entityInstance->getSport());
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== MemberSport::class) {
            throw new BadRequestHttpException('Ungültiger Sportzuordnungs-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Sportzuordnungs-ID.');
            }
            $record = $this->em->find(MemberSport::class, (int) $id);
            if ($record !== null) {
                $this->assertClub($record->getMember(), $record->getSport());
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

    public static function getEntityFqcn(): string
    {
        return MemberSport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setFormOptions(['csrf_token_id' => 'member_sport_crud_'.($this->clubContext->getClub()?->getId() ?? 'none')])
            ->setEntityLabelInSingular('Sportzuordnung')
            ->setEntityLabelInPlural('Sportzuordnungen')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Sportzuordnungen der Mitglieder'
            )
            ->setDefaultSort([
                'id' => 'DESC',
            ])
            ->setSearchFields([
                'member.person.firstname',
                'member.person.lastname',
                'member.memberNumber',
                'sport.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        $clubId = $this->clubContext->getClub()?->getId();
        $record = $this->getContext()?->getEntity()?->getInstance();
        $currentSportId = $record instanceof MemberSport ? $record->getSport()?->getId() : null;
        yield FormField::addFieldset('Zuordnung');

        yield AssociationField::new('member', 'Mitglied')
            ->setFormTypeOption('query_builder', static function (EntityRepository $repository) use ($clubId): QueryBuilder {
                $query = $repository->createQueryBuilder('memberChoice');
                return $clubId === null ? $query->andWhere('1 = 0') : $query->andWhere('memberChoice.club = :club')->setParameter('club', $clubId);
            })
            ->setRequired(true)
            ->setColumns(4);

		yield AssociationField::new('sport', 'Sportart')
			->setRequired(true)
			->setColumns(4)
			->setQueryBuilder(
				static function (QueryBuilder $queryBuilder) use ($clubId, $currentSportId): QueryBuilder {
					return $queryBuilder
						->andWhere('entity.club = :club')
                        ->andWhere('(entity.active = :active OR entity.id = :currentSport)')
                        ->setParameter('club', $clubId)
                        ->setParameter('active', true)
                        ->setParameter('currentSport', $currentSportId)
						->orderBy('entity.name', 'ASC');
				}
			);

        yield ChoiceField::new('status', 'Status')
            ->setChoices(MemberSportStatus::choices())
            ->setColumns(4);

        yield DateField::new('joinedAt', 'Seit')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3);

        yield DateField::new('leftAt', 'Bis')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3);

        yield TextareaField::new('notes', 'Notizen')
            ->hideOnIndex();
    }
}