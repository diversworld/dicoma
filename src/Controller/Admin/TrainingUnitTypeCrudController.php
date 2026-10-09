<?php

namespace App\Controller\Admin;

use App\Entity\TrainingUnitType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class TrainingUnitTypeCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return TrainingUnitType::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Ausbildungseinheit')
            ->setEntityLabelInPlural('Ausbildungseinheiten')
            ->setDefaultSort([
                'name' => 'ASC',
            ])
            ->setSearchFields([
                'code',
                'name',
                'description',
                'club.name',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield AssociationField::new(
            'club',
            'Verein'
        )
            ->setHelp(
                'Leer lassen für eine allgemein verfügbare '
                . 'Ausbildungseinheit.'
            );

        yield TextField::new(
            'code',
            'Kennung'
        )
            ->setHelp(
                'Stabile technische Kennung, z. B. '
                . 'THEORY, POOL, ABC, OPEN_WATER oder EXAM.'
            );

        yield TextField::new(
            'name',
            'Bezeichnung'
        );

        yield TextareaField::new(
            'description',
            'Beschreibung'
        )
            ->hideOnIndex();

        yield BooleanField::new(
            'examination',
            'Prüfung'
        );

        yield BooleanField::new(
            'active',
            'Aktiv'
        );
    }
}