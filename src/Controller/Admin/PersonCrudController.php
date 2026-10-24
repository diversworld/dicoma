<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Person;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class PersonCrudController extends AbstractCrudController
{
    public function __construct(private readonly AdminUrlGenerator $adminUrlGenerator)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Person::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Person')
            ->setEntityLabelInPlural('Personen und Gäste')
            ->setDefaultSort(['lastname' => 'ASC', 'firstname' => 'ASC'])
            ->setSearchFields(['firstname', 'lastname', 'email', 'city']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $membership = Action::new('createMembership', 'Mitgliedschaft anlegen', 'fa fa-user-plus')
            ->linkToUrl(fn (Person $person): string => (clone $this->adminUrlGenerator)->unsetAll()
                ->setController(MemberCrudController::class)->setAction(Crud::PAGE_NEW)
                ->set('personId', $person->getId())->generateUrl());
        // Identity deletion belongs to an explicit retention/anonymization process.
        return $actions->disable(Action::DELETE, Action::BATCH_DELETE)
            ->add(Crud::PAGE_INDEX, $membership);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('firstname', 'Vorname')->setRequired(true);
        yield TextField::new('lastname', 'Nachname')->setRequired(true);
        yield DateField::new('birthday', 'Geburtsdatum')->hideOnIndex();
        yield EmailField::new('email', 'E-Mail');
        yield TelephoneField::new('mobile', 'Mobil');
        yield TelephoneField::new('phone', 'Telefon')->hideOnIndex();
        yield TextField::new('street', 'Straße / Hausnummer')->hideOnIndex();
        yield TextField::new('postalCode', 'PLZ')->hideOnIndex();
        yield TextField::new('city', 'Wohnort');
    }
}
