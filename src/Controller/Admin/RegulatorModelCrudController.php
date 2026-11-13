<?php

namespace App\Controller\Admin;

use App\Entity\RegulatorModel;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,ChoiceField,TextField,BooleanField};
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
class RegulatorModelCrudController extends AbstractCrudController {

    use ClubEquipmentCrudTrait;

    public static function getEntityFqcn(): string {
        return RegulatorModel::class;
    }
    
    public function configureCrud(Crud $crud): Crud {
        return $crud->setFormOptions(['csrf_token_id' => 'regulator_model_crud_'.$this->clubEquipmentScope->club()->getId()])->setEntityLabelInSingular('Atemreglermodell')->setEntityLabelInPlural('Atemreglermodelle');
    }

    public function configureFields(string $pageName): iterable {

        $club = $this->clubEquipmentScope->club();
        
        yield AssociationField::new('club','Verein')->setFormTypeOption('choices', [$club])
            ->setFormTypeOption('disabled', true)
            ->setRequired(true);
        
        yield AssociationField::new('manufacturer','Hersteller')
            ->setFormTypeOption('query_builder',
            static fn (EntityRepository $repository): QueryBuilder => $repository->createQueryBuilder('manufacturer')
                ->where('manufacturer.club = :club')
                ->setParameter('club', $club))
                ->setRequired(true);
        
        yield ChoiceField::new('stage','Stufe')
            ->setChoices(['1. Stufe'=>'first','2. Stufe'=>'second'])
            ->setRequired(true);

        yield TextField::new('name','Modell')
            ->setRequired(true);
            
        yield BooleanField::new('active','Aktiv');
    }
}
