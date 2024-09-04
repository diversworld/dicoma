<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class UserCrudController extends AbstractCrudController
{
    private $passwordHasher;

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
            IdField::new('id', 'ID')->hideOnForm()->setColumns(3),
            FormField::addRow()->setColumns(8),
            FormField::addFieldset('Nutzerdaten')->collapsible(),
            TextField::new('user', 'Benutzername')->setColumns(3),
            EmailField::new('email', 'E-Mail')->setColumns(3),
            FormField::addRow()->setColumns(8),
            TextField::new('plainPassword', 'Passwort')
                ->onlyOnForms()
                ->setColumns(3),
            FormField::addRow()->setColumns(8),
            BooleanField::new('isVerified','Verifiziert')
                ->renderAsSwitch(true)
                ->setColumns(4),
            FormField::addRow()->setColumns(8),
            ChoiceField::new('roles', 'Rollen')
                ->setColumns(3)
                ->allowMultipleChoices(true)
                ->setChoices([
                    'Kunde' => 'ROLE_CUSTOMER',
                    'Student' => 'ROLE_STUDENT',
                    'Instructor' => 'ROLE_INSTRUCTOR',
                    'Admin' => 'ROLE_ADMIN',
                    'SuperAdmin' => 'ROLE_SUPER_ADMIN',
                ]),
            FormField::addFieldset('Mitglied')->collapsible(),
            AssociationField::new('member', 'Mitgliedsname')->setColumns(3),
        ];
    }

    // Hash the password if it's present before persisting the entity
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            if ($entityInstance->getPlainPassword()) {
                $hashedPassword = $this->passwordHasher->hashPassword(
                    $entityInstance,
                    $entityInstance->getPlainPassword()
                );
                $entityInstance->setPassword($hashedPassword);
                $entityInstance->eraseCredentials();
            }
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    // Hash the password if it's present before updating the entity
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            if ($entityInstance->getPlainPassword()) {
                $hashedPassword = $this->passwordHasher->hashPassword(
                    $entityInstance,
                    $entityInstance->getPlainPassword()
                );
                $entityInstance->setPassword($hashedPassword);
                $entityInstance->eraseCredentials();
            }
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}