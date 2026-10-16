<?php

namespace App\Controller\Admin;

use App\Entity\MemberQualification;
use App\Entity\Member;
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
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class MemberQualificationCrudController extends AbstractCrudController
{
	public function __construct(
		private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $memberEntityManager
	) {
	}
    public function createEntity(string $entityFqcn): MemberQualification
    {
        $item = new MemberQualification();
        $raw = $this->getContext()?->getRequest()->query->get('memberId');
        if (is_scalar($raw) && ctype_digit((string) $raw) && (int) $raw > 0) {
            $member = $this->memberEntityManager->getRepository(Member::class)->find((int) $raw);
            if ($member instanceof Member) {
                $item->setMember($member);
            }
        }
        return $item;
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
                'member.firstname',
                'member.lastname',
                'member.memberNumber',
                'qualification.name',
                'qualification.shortName',
                'certificateNumber',
                'issuer',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new(
            'member',
            'Mitglied'
        );

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
		return $filters
			->add('member')
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