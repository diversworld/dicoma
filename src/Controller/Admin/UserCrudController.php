<?php

namespace App\Controller\Admin;

use App\Entity\User;
<<<<<<< HEAD
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
=======
>>>>>>> origin/main
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
<<<<<<< HEAD
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
=======
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;

class UserCrudController extends AbstractCrudController
{
    private $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
>>>>>>> origin/main
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

<<<<<<< HEAD
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Benutzer')
            ->setEntityLabelInPlural('Benutzer')
            ->setPageTitle(Crud::PAGE_INDEX, 'Benutzerverwaltung')
            ->setPageTitle(Crud::PAGE_NEW, 'Benutzer anlegen')
            ->setPageTitle(Crud::PAGE_EDIT, 'Benutzer bearbeiten')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Benutzerdetails')
            ->setDefaultSort([
                'user' => 'ASC',
            ])
            ->setSearchFields([
                'user',
                'email',
                'member.firstname',
                'member.lastname',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield FormField::addFieldset('Benutzerkonto');

        yield TextField::new('user', 'Benutzername')
            ->setColumns(4)
            ->setRequired(true);

        yield EmailField::new('email', 'E-Mail-Adresse')
            ->setColumns(4)
            ->setRequired(true);

        yield BooleanField::new('isVerified', 'Verifiziert')
            ->renderAsSwitch(true)
            ->setColumns(4);

        /*
         * plainPassword wird nicht in der Datenbank gespeichert.
         *
         * Beim Anlegen ist ein Passwort erforderlich.
         * Beim Bearbeiten bleibt das Feld leer, wenn das vorhandene
         * Passwort nicht geändert werden soll.
         */
        $passwordField = TextField::new(
            'plainPassword',
            $pageName === Crud::PAGE_NEW
                ? 'Passwort'
                : 'Neues Passwort'
        )
            ->setFormType(PasswordType::class)
            ->onlyOnForms()
            ->setColumns(4)
            ->setHelp(
                $pageName === Crud::PAGE_EDIT
                    ? 'Leer lassen, wenn das bestehende Passwort beibehalten werden soll.'
                    : 'Passwort für das neue Benutzerkonto.'
            );

        if ($pageName === Crud::PAGE_NEW) {
            $passwordField->setRequired(true);
        } else {
            $passwordField->setRequired(false);
        }

        yield $passwordField;

        yield FormField::addFieldset('Berechtigungen');

        yield ChoiceField::new('roles', 'Rollen')
            ->setChoices(User::getAvailableRoles())
            ->allowMultipleChoices(true)
            ->renderExpanded(false)
            ->setColumns(6)
            ->setHelp(
                'Ein Benutzer kann mehrere Funktionen im Verein besitzen. '
                . 'Die Rolle „Mitglied“ wird während der Übergangsphase '
                . 'automatisch berücksichtigt.'
            );

        yield FormField::addFieldset('Mitglied');

        yield AssociationField::new('member', 'Verknüpftes Mitglied')
            ->setColumns(6)
            ->setRequired(false)
            ->setHelp(
                'Optionales Vereinsmitglied, das mit diesem Benutzerkonto verbunden ist.'
            );
    }

    public function persistEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof User) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        $this->hashPassword($entityInstance, true);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        $this->hashPassword($entityInstance, false);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPassword(User $user, bool $passwordRequired): void
    {
        $plainPassword = $user->getPlainPassword();

        if ($plainPassword === null || trim($plainPassword) === '') {
            if ($passwordRequired) {
                throw new \LogicException(
                    'Beim Anlegen eines Benutzers muss ein Passwort angegeben werden.'
                );
            }

            return;
        }

        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                $plainPassword
            )
        );

        $user->eraseCredentials();
    }
=======
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
>>>>>>> origin/main
}