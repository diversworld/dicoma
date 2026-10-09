<?php

namespace App\Controller\Admin;

use App\Entity\MemberQualification;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

#[IsGranted('ROLE_ADMIN')]
class MemberQualificationCrudController extends AbstractCrudController
{
	public function __construct(
		private readonly AdminUrlGenerator $adminUrlGenerator
	) {
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

		return $actions->add(
			Crud::PAGE_INDEX,
			$resetAction
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
}