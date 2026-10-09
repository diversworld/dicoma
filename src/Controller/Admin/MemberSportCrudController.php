<?php

namespace App\Controller\Admin;

use App\Entity\MemberSport;
use App\Enum\MemberSportStatus;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class MemberSportCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MemberSport::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
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
                'member.firstname',
                'member.lastname',
                'member.memberNumber',
                'sport.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Zuordnung');

        yield AssociationField::new('member', 'Mitglied')
            ->setRequired(true)
            ->setColumns(4);

		yield AssociationField::new('sport', 'Sportart')
			->setRequired(true)
			->setColumns(4)
			->setQueryBuilder(
				static function (QueryBuilder $queryBuilder): QueryBuilder {
					return $queryBuilder
						->andWhere('entity.active = :active')
						->setParameter('active', true)
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