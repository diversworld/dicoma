<?php

namespace App\Controller\Admin;

use App\Entity\TeamMember;
use App\Enum\TeamMemberRole;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class TeamMemberCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TeamMember::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Gruppenmitglied')
            ->setEntityLabelInPlural('Gruppenmitglieder')
            ->setPageTitle(
                Crud::PAGE_INDEX,
                'Gruppen- und Mannschaftsmitglieder'
            )
            ->setPageTitle(
                Crud::PAGE_NEW,
                'Mitglied einer Gruppe zuordnen'
            )
            ->setPageTitle(
                Crud::PAGE_EDIT,
                'Gruppenzuordnung bearbeiten'
            )
            ->setDefaultSort([
                'id' => 'DESC',
            ])
            ->setSearchFields([
                'member.firstname',
                'member.lastname',
                'member.memberNumber',
                'team.name',
                'team.sport.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('Zuordnung');

        yield AssociationField::new('team', 'Gruppe / Mannschaft')
            ->setRequired(true)
            ->setColumns(4);

        yield AssociationField::new('member', 'Mitglied')
            ->setRequired(true)
            ->setColumns(4);

        yield ChoiceField::new('role', 'Funktion')
            ->setChoices(TeamMemberRole::choices())
            ->setRequired(true)
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