<?php

namespace App\Controller\Admin;

use App\Entity\Club;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ClubCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Club::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Verein')
            ->setEntityLabelInPlural('Vereine')
            ->setPageTitle(Crud::PAGE_INDEX, 'Vereine')
            ->setPageTitle(Crud::PAGE_NEW, 'Verein anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Verein bearbeiten')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Vereinsdetails')
            ->setDefaultSort([
                'name' => 'ASC',
            ])
            ->setSearchFields([
                'name',
                'shortName',
                'association',
                'clubNumber',
                'city',
                'email',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield FormField::addFieldset('Verein');

        yield TextField::new('name', 'Vereinsname')
            ->setRequired(true)
            ->setColumns(6);

        yield TextField::new('shortName', 'Kurzname')
            ->setColumns(3);

        yield BooleanField::new('active', 'Aktiv')
            ->setColumns(3);

        yield TextField::new('association', 'Verband')
            ->setHelp('z. B. VDST, DOSB-Landesverband oder anderer Dachverband')
            ->setColumns(4);

        yield TextField::new('clubNumber', 'Vereins-/Verbandsnummer')
            ->setColumns(4);

        yield IntegerField::new('members.count', 'Mitglieder')
            ->onlyOnIndex();

        yield FormField::addFieldset('Kontakt');

        yield EmailField::new('email', 'E-Mail')
            ->setColumns(4);

        yield TelephoneField::new('phone', 'Telefon')
            ->setColumns(4);

        yield UrlField::new('website', 'Website')
            ->setColumns(4);

        yield FormField::addFieldset('Anschrift');

        yield TextField::new('street', 'Straße / Hausnummer')
            ->setColumns(6);

        yield TextField::new('postalCode', 'PLZ')
            ->setColumns(2);

        yield TextField::new('city', 'Ort')
            ->setColumns(4);

        yield CountryField::new('country', 'Land')
            ->setColumns(4);

        yield FormField::addFieldset('Weitere Informationen');

        yield TextareaField::new('description', 'Beschreibung')
            ->hideOnIndex();

        yield TextareaField::new('notes', 'Interne Notizen')
            ->hideOnIndex();

        yield TextField::new('createdAt', 'Erstellt')
            ->onlyOnDetail()
            ->formatValue(
                static fn ($value) =>
                    $value instanceof \DateTimeInterface
                        ? $value->format('d.m.Y H:i')
                        : ''
            );

        yield TextField::new('updatedAt', 'Geändert')
            ->onlyOnDetail()
            ->formatValue(
                static fn ($value) =>
                    $value instanceof \DateTimeInterface
                        ? $value->format('d.m.Y H:i')
                        : ''
            );
    }
}