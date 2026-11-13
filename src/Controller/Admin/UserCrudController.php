<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Club;
use App\Service\MembershipRoleContinuity;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

// Benutzer- und Rollenverwaltung ist bis zur fachlichen Freigabe
// einer delegierten Rollenvergabe ausschließlich Super-Admins erlaubt.
#[IsGranted('ROLE_SUPER_ADMIN')]
class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator,
        private readonly MembershipRoleContinuity $continuity,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

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
                'person.firstname',
                'person.lastname',
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
            ->setFormTypeOption('getter', static fn (User $user): array => $user->getStoredRoles())
            ->allowMultipleChoices(true)
            ->renderExpanded(false)
            ->setColumns(6)
            ->setHelp(
                'Ein Benutzer kann mehrere Funktionen im Verein besitzen. '
                . 'Die Rolle „Mitglied“ gilt nur mit aktivem Mitgliedsprofil und Vereinszuordnung. '
                . 'Funktionsrollen werden davon unabhängig vergeben.'
            );

        yield FormField::addFieldset('Mitglied');

        yield AssociationField::new('person', 'Zentrale Person')
            ->onlyOnDetail();

        yield AssociationField::new('member', 'Verknüpftes Mitglied')
            ->setColumns(6)
            ->setRequired(false)
            ->setHelp(
                'Optionales Vereinsmitglied, das mit diesem Benutzerkonto verbunden ist.'
            );
    }

    public function configureActions(Actions $actions): Actions
    {
        $validity = Action::new('roleValidity', 'Rollengültigkeiten')
            ->linkToRoute('admin_role_validity', static fn (User $user): array => ['id' => $user->getId()]);
        return $actions->add(Crud::PAGE_INDEX, $validity)->add(Crud::PAGE_DETAIL, $validity);
    }

    public function persistEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof User) {
            parent::persistEntity($entityManager, $entityInstance);

            return;
        }

        $entityManager->getConnection()->transactional(function () use ($entityManager, $entityInstance): void {
            $this->continuity->lockClub($entityInstance->getMember()?->getClub());
            $this->assertActiveRoleMember($entityManager, $entityInstance);
            $this->hashPassword($entityInstance, true);
            parent::persistEntity($entityManager, $entityInstance);
        });
    }

    public function updateEntity(
        EntityManagerInterface $entityManager,
        $entityInstance
    ): void {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);

            return;
        }

        $entityManager->getConnection()->transactional(function () use ($entityManager, $entityInstance): void {
            $connection = $entityManager->getConnection();
            $oldMember = $connection->fetchAssociative('SELECT id, club_id FROM member WHERE user_id = ?', [$entityInstance->getId()]);
            $newMember = $entityInstance->getMember();
            $clubIds = array_filter([$oldMember['club_id'] ?? null, $newMember?->getClub()?->getId()]);
            $clubIds = array_unique(array_map('intval', $clubIds));
            sort($clubIds);
            foreach ($clubIds as $clubId) {
                $this->continuity->lockClub($entityManager->getReference(Club::class, $clubId));
            }
            $lock = $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
            $currentMember = $connection->fetchAssociative('SELECT id, club_id FROM member WHERE user_id = ?'.$lock, [$entityInstance->getId()]);
            if ($currentMember !== $oldMember) {
                throw new ConflictHttpException('Die Mitgliedszuordnung wurde zwischenzeitlich geändert. Bitte neu laden.');
            }
            $row = $connection->fetchAssociative('SELECT roles, role_validity FROM `user` WHERE id = ?'.$lock, [$entityInstance->getId()]);
            $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
            if ($row === false || json_decode((string) $row['roles'], true, 512, JSON_THROW_ON_ERROR) !== ($original['roles'] ?? [])
                || ($row['role_validity'] === null ? null : json_decode((string) $row['role_validity'], true, 512, JSON_THROW_ON_ERROR)) !== ($original['roleValidity'] ?? null)) {
                throw new ConflictHttpException('Die Rollen wurden zwischenzeitlich geändert. Bitte neu laden.');
            }
            $oldId = $oldMember === false ? null : (int) $oldMember['id'];
            if ($oldId !== $newMember?->getId()
                && (array_diff($original['roles'] ?? [], [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN]) !== []
                    || $this->continuity->functions($entityInstance) !== [])) {
                throw new ConflictHttpException('Die Mitgliedszuordnung von Konten mit Funktionsrollen kann nicht geändert werden. Rollen zuerst gesondert klären.');
            }
            $this->assertActiveRoleMember($entityManager, $entityInstance);
            $this->hashPassword($entityInstance, false);
            parent::updateEntity($entityManager, $entityInstance);
        });
    }

    private function assertActiveRoleMember(EntityManagerInterface $em, User $user): void
    {
        $member = $user->getMember();
        if ($member === null) {
            return;
        }
        $connection = $em->getConnection();
        $lock = $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
        $row = $connection->fetchAssociative('SELECT user_id, membership_status, left_at, archived_at FROM member WHERE id = ?'.$lock, [$member->getId()]);
        if ($row === false || ($row['user_id'] !== null && (int) $row['user_id'] !== $user->getId())) {
            throw new ConflictHttpException('Die Mitgliedschaft ist nicht verfügbar oder bereits mit einem anderen Konto verbunden.');
        }
        if ($this->continuity->functions($user) !== []
            && ($row['membership_status'] !== 'active' || $row['left_at'] !== null || $row['archived_at'] !== null)) {
            throw new ConflictHttpException('Funktionsrollen dürfen nur aktiven Mitgliedern zugewiesen bleiben.');
        }
    }

    private function hashPassword(User $user, bool $passwordRequired): void
    {
        $plainPassword = $user->getPlainPassword();

        if ($plainPassword === null || trim($plainPassword) === '') {
            if ($passwordRequired) {
                throw new \LogicException(
                    $this->translator->trans('Beim Anlegen eines Benutzers muss ein Passwort angegeben werden.')
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
}