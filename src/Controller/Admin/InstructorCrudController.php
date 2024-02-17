<?php

namespace App\Controller\Admin;

use AllowDynamicProperties;
use App\Entity\Instructor;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AllowDynamicProperties] class InstructorCrudController extends AbstractCrudController
{
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }
    public function configureFields(string $pageName): iterable
    {
        return [
            FormField::addFieldset('Personendaten')
                ->setColumns(8)
                ->collapsible(),
            IdField::new('id', 'ID')
                ->hideOnForm()
                ->setColumns(3),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            TextField::new('firstname', 'Vorname')
                ->setColumns(4),
            TextField::new('lastname',  'Nachname')
                ->setColumns(4),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            DateField::new('birthdate', 'Geburtsdatum')
                ->setColumns(2)
                ->setFormat('dd.MM.yyyy')
                ->setHelp('Format: dd.MM.yyyy')
                ->hideOnIndex(),
            FormField::addFieldset('Adressdaten')
                ->collapsible(),
            TextField::new('street', 'Straße')
                ->setColumns(4)
                ->hideOnIndex(),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            IntegerField::new('postal', 'PLZ')
                ->setColumns(1)
                ->hideOnIndex(),
            TextField::new('city', 'Wohnort')
                ->setColumns(5)
                ->hideOnIndex(),
            FormField::addFieldset('Kontaktdaten')
                ->collapsible(),
            EmailField::new('email', 'E-Mail')
                ->setColumns(4),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            TelephoneField::new('mobile', 'Mobil')
                ->setColumns(3),
            TelephoneField::new('phone', 'Telefon')
                ->setColumns(3)
                ->hideOnIndex(),
            FormField::addFieldset('Nutzerdaten')
                ->collapsible(),
            TextField::new('username', 'Benutzername')
                ->setColumns(3),
            TextField::new('password', 'Passwort')
                ->setColumns(3)
                ->hideOnIndex()
                ->setFormType(PasswordType::class)
                ->setHelp('Das Feld leer lassen um das vorhandene Passwort nicht zu ändern.')
                ->setFormTypeOptions([
                    // use this to set a default text
                    'empty_data' => 'Password'
                ])// make this a masked password field
                ->setFormTypeOptions([
                    // use this to set a placeholder text containing asterisks
                    'attr' => ['placeholder' => '********'],
                ]),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            ChoiceField::new('roles','Rollen')
                ->setColumns(3)
                ->allowMultipleChoices('true')
                ->setChoices([
                    'Kunde' => 'ROLE_CUSTOMER',
                    'Student' => 'ROLE_STUDENT',
                    'Instructor' => 'ROLE_INSTRUCTOR',
                    'Admin' => 'ROLE_ADMIN',
                    'SuperAdmin' => 'ROLE_SUPER_ADMIN',
                ]),
            FormField::addRow(breakpointName: 'md')
                ->setColumns(8),
            ChoiceField::new('status', 'Status')
                ->setColumns(2)
                ->setChoices([
                    'Aktiv' => 'true',
                    'Inaktiv' => 'false',
                ]),
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->hashPassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword($entity): void
    {
        $plainPassword = $entity->getPassword();

        if ($entity instanceof User && !empty($plainPassword) && $plainPassword != 'Password') {
            $entity->setPassword($this->passwordHasher->hashPassword($entity, $plainPassword));
        }
    }
}
