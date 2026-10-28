<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Entity\Person;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
final class PersonCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Person::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->setEntityLabelInSingular('Nichtmitglied / Gast')
            ->setEntityLabelInPlural('Nichtmitglieder und Gäste')
            ->setDefaultSort(['lastname' => 'ASC', 'firstname' => 'ASC'])
            ->setSearchFields(['firstname', 'lastname', 'email', 'city'])
            ->setHelp(Crud::PAGE_INDEX, 'Hier erscheinen nur Personen ohne Mitgliedschaft. Mitgliederdaten werden ausschließlich unter „Mitglieder“ verwaltet; Personendaten bleiben dort zentral verknüpft.');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $queryBuilder = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $alias = $queryBuilder->getRootAliases()[0];

        return $queryBuilder->andWhere(sprintf(
            'NOT EXISTS (SELECT mbr.id FROM App\Entity\Member mbr WHERE mbr.person = %s)',
            $alias
        ));
    }

    public function detail(AdminContext $context)
    {
        $this->denyMemberIdentity($context);

        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->denyMemberIdentity($context);

        return parent::edit($context);
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

    private function denyMemberIdentity(AdminContext $context): void
    {
        $person = $context->getEntity()->getInstance();
        if ($person instanceof Person && $this->entityManager->getRepository(Member::class)->count(['person' => $person]) > 0) {
            throw $this->createNotFoundException('Mitgliederdaten bitte in der Mitgliederverwaltung bearbeiten.');
        }
    }
}
