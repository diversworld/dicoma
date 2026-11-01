<?php

namespace App\Controller\Admin;

use App\Entity\MemberQualification;
use App\Entity\Member;
use App\Service\AdminClubContext;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class MemberQualificationCrudController extends AbstractCrudController
{
	public function __construct(
		private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $memberEntityManager,
        private readonly AdminClubContext $clubContext,
	) {
	}
    public function createEntity(string $entityFqcn): MemberQualification
    {
        $item = new MemberQualification();
        $raw = $this->getContext()?->getRequest()->query->get('memberId');
        if (is_scalar($raw) && ctype_digit((string) $raw) && (int) $raw > 0) {
            $member = $this->memberEntityManager->getRepository(Member::class)->find((int) $raw);
            if ($member instanceof Member) {
                $this->assertMemberClub($member);
                $item->setMember($member);
            }
        }
        return $item;
    }

    private function assertMemberClub(?Member $member): void
    {
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null || $member?->getClub()?->getId() !== $clubId) {
            throw $this->createAccessDeniedException('Mitglied gehört nicht zum ausgewählten Verein.');
        }
    }

    public function edit(AdminContext $context)
    {
        $this->assertMemberClub($context->getEntity()->getInstance()->getMember());
        return parent::edit($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MemberQualification) {
            $this->assertMemberClub($entityInstance->getMember());
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MemberQualification) {
            $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            $originalMember = $original['member'] ?? null;
            $this->assertMemberClub($originalMember instanceof Member ? $originalMember : null);
            $this->assertMemberClub($entityInstance->getMember());
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MemberQualification) {
            $this->assertMemberClub($entityInstance->getMember());
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function batchDelete(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        if ($batchActionDto->getEntityFqcn() !== MemberQualification::class) {
            throw new BadRequestHttpException('Ungültiger Qualifikations-Stapel.');
        }
        foreach ($batchActionDto->getEntityIds() as $id) {
            if ((!is_string($id) && !is_int($id)) || !ctype_digit((string) $id) || (int) $id < 1) {
                throw new BadRequestHttpException('Ungültige Qualifikations-ID.');
            }
            $record = $this->memberEntityManager->find(MemberQualification::class, (int) $id);
            if ($record !== null) {
                $this->assertMemberClub($record->getMember());
            }
        }
        return parent::batchDelete($context, $batchActionDto);
    }

    /**
     * Zeigt einen expliziten, sicheren Rueckweg bei Formularen aus der
     * Mitgliederzentrale. Die EasyAdmin-Standardaktion bleibt unveraendert.
     *
     * Bei EDIT wird die Mitgliedszuordnung aus der Datenbank gelesen,
     * niemals aus einem manipulierten memberId-Parameter.
     */
    private function memberCenterCancelUrl(): ?string
    {
        $request = $this->getContext()?->getRequest();
        if ($request === null || $request->query->get('returnToMember') !== '1') {
            return null;
        }

        $action = $request->query->get('crudAction');
        $member = null;

        if ($action === Crud::PAGE_EDIT) {
            $rawId = $request->query->get('entityId');
            if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
                return null;
            }
            $record = $this->memberEntityManager
                ->getRepository(\App\Entity\MemberQualification::class)
                ->find((int) $rawId);
            $member = $record?->getMember();
        } elseif ($action === Crud::PAGE_NEW) {
            $rawId = $request->query->get('memberId');
            if (!is_scalar($rawId) || !ctype_digit((string) $rawId) || (int) $rawId < 1) {
                return null;
            }
            $member = $this->memberEntityManager
                ->getRepository(Member::class)
                ->find((int) $rawId);
        }

        if (!$member instanceof Member || $member->getId() === null) {
            return null;
        }

        return (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(MemberCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($member->getId())
            ->generateUrl();
    }

    private function addMemberCenterReturnAction(Actions $actions): Actions
    {
        $url = $this->memberCenterCancelUrl();
        if ($url === null) {
            return $actions;
        }

        $return = Action::new(
            'backToMemberCenter',
            'Zur Mitgliederzentrale (ohne Speichern)',
            'fa fa-arrow-left'
        )
            ->linkToUrl($url)
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_NEW, $return)
            ->add(Crud::PAGE_EDIT, $return);
    }

    public static function getEntityFqcn(): string
    {
        return MemberQualification::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setFormOptions(['csrf_token_id' => 'member_qualification_crud_'.($this->clubContext->getClub()?->getId() ?? 'none')])
            ->setEntityLabelInSingular(
                'Mitgliedsqualifikation'
            )
			
            ->setEntityLabelInPlural(
                'Mitgliedsqualifikationen'
            )
            ->setPageTitle(
				Crud::PAGE_INDEX,
				function (): string {
					$validity = $this
						->getContext()
						?->getRequest()
						->query
						->get('validity');

					return match ($validity) {
						'expired' =>
							'Mitgliedsqualifikationen → Abgelaufen',

						'expiring' =>
							'Mitgliedsqualifikationen → Läuft innerhalb von 30 Tagen ab',

						default =>
							'Qualifikationen der Mitglieder',
					};
				}
			)
            ->setDefaultSort([
                'validUntil' => 'ASC',
            ])
            ->setSearchFields([
                'member.person.firstname',
                'member.person.lastname',
                'member.memberNumber',
                'qualification.name',
                'qualification.shortName',
                'certificateNumber',
                'issuer',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_EDIT || $pageName === Crud::PAGE_DETAIL) {
            $record = $this->getContext()?->getEntity()?->getInstance();
            if ($record instanceof MemberQualification) {
                $this->assertMemberClub($record->getMember());
            }
        }
        $clubId = $this->clubContext->getClub()?->getId();
        yield AssociationField::new(
            'member',
            'Mitglied'
        )->setFormTypeOption('query_builder', static function (EntityRepository $repository) use ($clubId): QueryBuilder {
            $qb = $repository->createQueryBuilder('memberChoice');
            return $clubId === null
                ? $qb->andWhere('1 = 0')
                : $qb->andWhere('memberChoice.club = :choiceClub')->setParameter('choiceClub', $clubId);
        });

        yield AssociationField::new(
            'qualification',
            'Qualifikation'
        );

        yield TextField::new(
            'certificateNumber',
            'Zertifikatsnummer'
        );

        yield DateField::new(
            'issuedAt',
            'Ausgestellt'
        )
            ->setFormat('dd.MM.yyyy');

        yield DateField::new(
            'validUntil',
            'Gültig bis'
        )
            ->setFormat('dd.MM.yyyy');

        yield BooleanField::new(
            'verified',
            'Geprüft'
        );

        yield TextField::new(
            'issuer',
            'Ausgestellt durch'
        )
            ->hideOnIndex();

        yield TextareaField::new(
            'notes',
            'Notizen'
        )
            ->hideOnIndex();
    }
	
	public function configureFilters(Filters $filters): Filters
	{
        $clubId = $this->clubContext->getClub()?->getId();
		return $filters
			->add(EntityFilter::new('member')->setFormTypeOption('value_type_options.query_builder', static function (EntityRepository $repository) use ($clubId): QueryBuilder {
                $qb = $repository->createQueryBuilder('filterMember');
                return $clubId === null
                    ? $qb->andWhere('1 = 0')
                    : $qb->andWhere('filterMember.club = :filterClub')->setParameter('filterClub', $clubId);
            }))
			->add('qualification')
			->add('verified');
	}
	
	public function configureActions(Actions $actions): Actions
	{
		$validity = $this
			->getContext()
			?->getRequest()
			->query
			->get('validity');

		$resetUrl = $this->adminUrlGenerator
			->unsetAll()
			->setController(self::class)
			->setAction(Crud::PAGE_INDEX)
			->generateUrl();

		$resetAction = Action::new(
			'resetValidityFilter',
			'Filter zurücksetzen',
			'fa fa-xmark'
		)
			->linkToUrl($resetUrl)
			->createAsGlobalAction()
			->addCssClass('btn btn-secondary');

		return $this->addMemberCenterReturnAction(
            $actions->add(Crud::PAGE_INDEX, $resetAction)
        );
	}
	
	public function createIndexQueryBuilder(
		SearchDto $searchDto,
		EntityDto $entityDto,
		FieldCollection $fields,
		FilterCollection $filters
	): QueryBuilder {
		$queryBuilder = parent::createIndexQueryBuilder(
			$searchDto,
			$entityDto,
			$fields,
			$filters
		);
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null) {
            $queryBuilder->andWhere('1 = 0');
        } else {
            $queryBuilder->join('entity.member', 'managedQualificationMember')
                ->andWhere('managedQualificationMember.club = :qualificationClub')
                ->setParameter('qualificationClub', $clubId);
        }

		$request = $this->getContext()?->getRequest();

		$status = $request?->query->get('validity');

		if ($status === null) {
			return $queryBuilder;
		}

		$today = new \DateTimeImmutable('today');

		switch ($status) {
			case 'expired':
				$queryBuilder
					->andWhere('entity.validUntil IS NOT NULL')
					->andWhere('entity.validUntil < :validityToday')
					->setParameter(
						'validityToday',
						$today
					);

				break;

			case 'expiring':
				$until = $today->modify('+30 days');

				$queryBuilder
					->andWhere('entity.validUntil IS NOT NULL')
					->andWhere(
						'entity.validUntil >= :validityToday'
					)
					->andWhere(
						'entity.validUntil <= :validityUntil'
					)
					->setParameter(
						'validityToday',
						$today
					)
					->setParameter(
						'validityUntil',
						$until
					);

				break;
		}

		return $queryBuilder;
	}
	
    protected function getRedirectResponseAfterSave(
        AdminContext $context,
        string $action
    ): RedirectResponse {
        $request = $context->getRequest();
        if ($request->query->get('returnToMember') !== '1') {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        // Never trust memberId as the destination. Use the actual saved relation.
        $entity = $context->getCrud() !== null
            ? $context->getEntity()?->getInstance()
            : null;

        if (!$entity instanceof MemberQualification) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        $member = $entity->getMember();
        if (!$member instanceof Member || $member->getId() === null) {
            return parent::getRedirectResponseAfterSave($context, $action);
        }

        $url = (clone $this->adminUrlGenerator)
            ->unsetAll()
            ->setController(MemberCrudController::class)
            ->setAction(Crud::PAGE_DETAIL)
            ->setEntityId($member->getId())
            ->generateUrl();

        return new RedirectResponse($url);
    }

}