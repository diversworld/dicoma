<?php
namespace App\Controller\Admin;
use App\Entity\RegulatorModel;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\{AssociationField,ChoiceField,TextField,BooleanField};
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_SUPER_ADMIN')]
class RegulatorModelCrudController extends AbstractCrudController {
 public static function getEntityFqcn(): string {return RegulatorModel::class;}
 public function configureCrud(Crud $crud): Crud {return $crud->setEntityLabelInSingular('Atemreglermodell')->setEntityLabelInPlural('Atemreglermodelle');}
 public function configureFields(string $pageName): iterable {
  yield AssociationField::new('club','Verein')->setRequired(true);
  yield AssociationField::new('manufacturer','Hersteller')->setRequired(true);
  yield ChoiceField::new('stage','Stufe')->setChoices(['1. Stufe'=>'first','2. Stufe'=>'second'])->setRequired(true);
  yield TextField::new('name','Modell')->setRequired(true);
  yield BooleanField::new('active','Aktiv');
 }
}
