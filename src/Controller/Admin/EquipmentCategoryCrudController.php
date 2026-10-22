<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\EquipmentCategory;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class EquipmentCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string { return EquipmentCategory::class; }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Ausrüstungskategorie')
            ->setEntityLabelInPlural('Ausrüstungskategorien');
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('club', 'Verein')->setRequired(true);
        yield TextField::new('code', 'Schlüssel');
        yield TextField::new('name', 'Bezeichnung');
        yield BooleanField::new('active', 'Aktiv');
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        try {
            parent::deleteEntity($entityManager, $entityInstance);
        } catch (ForeignKeyConstraintViolationException $exception) {
            $this->addFlash('danger',
                'Diese Ausrüstungskategorie kann nicht gelöscht werden, weil noch Verknüpfungen bestehen. '
                .'Bitte ordne die zugehörigen Ausrüstungsgegenstände zuerst einer anderen Kategorie zu '
                .'oder setze die Kategorie auf „Inaktiv“.'
            );
        }
    }
}
