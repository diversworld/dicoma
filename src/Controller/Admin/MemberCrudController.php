<?php

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Security\Voter\MemberManagementVoter;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use App\Entity\MemberDocument;
use App\Entity\MemberQualification;
use App\Entity\CourseParticipant;
use App\Entity\TeamMember;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Enum\MembershipStatus;
use App\Form\MemberSportType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Form\MemberQualificationType;

#[IsGranted('ROLE_ADMIN')]
class MemberCrudController extends AbstractCrudController
{
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly UrlGeneratorInterface $router, private readonly AdminUrlGenerator $adminUrlGenerator) {}

    /** Schritt 32: Listenfilter erfolgt in SQL, nicht erst beim Rendern. */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $clubId = $this->currentClubId();
            if ($clubId === null) {
                $qb->andWhere('1 = 0');
            } else {
                $qb->andWhere('entity.club = :memberManagedClub')->setParameter('memberManagedClub', $clubId);
            }
        }
        return $qb;
    }

    private function currentClubId(): ?int
    {
        $user = $this->getUser();
        return $user instanceof User ? $user->getMember()?->getClub()?->getId() : null;
    }

    private function assertMemberAccess(Member $member): void
    {
        $this->denyAccessUnlessGranted(MemberManagementVoter::MANAGE, $member);
    }

    /** Schritte 34-36: Formulardaten duerfen keine fremde Vereinszuordnung erzeugen. */
    private function enforceMemberClub(Member $member, ?EntityManagerInterface $em = null): void
    {
        if ($this->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }
        $user = $this->getUser();
        $club = $user instanceof User ? $user->getMember()?->getClub() : null;
        if ($club === null || $club->getId() === null) {
            throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        }
        if ($em !== null) {
            // Die Datenbankzuordnung pruefen, nicht den bereits durch das Formular
            // veraenderbaren Entity-Zustand. Auch eine manipulierte club-ID darf
            // keinen Zugriff auf Mitglieder eines anderen Vereins ermoeglichen.
            $memberId = $member->getId();
            if ($memberId === null) {
                throw $this->createAccessDeniedException('Mitglied nicht vorhanden.');
            }

            $storedClubId = $em->getConnection()->fetchOne(
                'SELECT club_id FROM member WHERE id = :id',
                ['id' => $memberId]
            );
            if ($storedClubId === false || (int) $storedClubId !== $club->getId()) {
                throw $this->createAccessDeniedException('Mitglied gehoert nicht zum eigenen Verein.');
            }
        }
        $member->setClub($club);
    }

    public function createEntity(string $entityFqcn): Member
    {
        $member = new Member();
        $personId = $this->getContext()?->getRequest()->query->get('personId');
        if ($personId !== null) {
            $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
            if (!is_string($personId) || !ctype_digit($personId) || (int) $personId < 1) {
                throw $this->createNotFoundException('Ungültige Personen-ID.');
            }
            $person = $this->entityManager->find(Person::class, (int) $personId);
            if ($person === null) {
                throw $this->createNotFoundException('Person nicht gefunden.');
            }
            $member->setPerson($person);
            $account = $this->entityManager->getRepository(User::class)->findOneBy(['person' => $person]);
            if ($account !== null && $account->getMember() === null) {
                $member->setUser($account);
            }
        }
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $user = $this->getUser();
            $club = $user instanceof User ? $user->getMember()?->getClub() : null;
            if ($club === null || $club->getId() === null) {
                throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
            }
            $member->setClub($club);
        }
        return $member;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Member) {
            $this->enforceMemberClub($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Member) {
            $this->enforceMemberClub($entityInstance, $entityManager);
            $this->assertMemberAccess($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Member) {
            $this->assertMemberAccess($entityInstance);
        }
        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function configureActions(Actions $actions): Actions
    {
        $center = Action::new('memberCenter', 'Mitgliederzentrale', 'fa fa-address-card')
            ->linkToCrudAction(Crud::PAGE_DETAIL);
        return $actions->add(Crud::PAGE_INDEX, $center);
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if ($responseParameters->get('pageName') !== Crud::PAGE_DETAIL) {
            return $responseParameters;
        }
        $context = $this->getContext();
        $member = $context !== null && $context->getCrud() !== null
            ? $context->getEntity()?->getInstance() : null;
        if (!$member instanceof Member || $member->getId() === null) {
            return $responseParameters;
        }
        $this->assertMemberAccess($member);
        $em = $this->entityManager;
        $responseParameters->set('memberCenterQualifications', $em->getRepository(MemberQualification::class)->findBy(['member' => $member], ['id' => 'DESC']));
        $responseParameters->set('memberCenterCourses', $em->getRepository(CourseParticipant::class)->findBy(['member' => $member], ['id' => 'DESC']));
        $responseParameters->set('memberCenterTeams', $em->getRepository(TeamMember::class)->findBy(['member' => $member], ['id' => 'DESC']));
        $responseParameters->set('memberCenterDocuments', $em->getRepository(MemberDocument::class)->findBy(['member' => $member], ['id' => 'DESC']));
        // Die URLs werden serverseitig von EasyAdmin erzeugt. Keine CRUD-Parameter
        // im Twig-Template zusammenbauen (insbesondere bei lokalisierten Dashboards).
        $url = fn (string $controller, string $action, ?int $id = null): string =>
            $this->adminLink($controller, $action, $id);
        $responseParameters->set('memberCenterLinks', [
            'memberEdit' => $url(self::class, Crud::PAGE_EDIT, $member->getId()),
            'qualificationsNew' => $this->memberPrefillUrl(MemberQualificationCrudController::class, $member->getId()),
            'qualificationsIndex' => $url(MemberQualificationCrudController::class, Crud::PAGE_INDEX),
            'coursesIndex' => $url(CourseParticipantCrudController::class, Crud::PAGE_INDEX),
            'teamsNew' => $this->memberPrefillUrl(TeamMemberCrudController::class, $member->getId()),
            'teamsIndex' => $url(TeamMemberCrudController::class, Crud::PAGE_INDEX),
        ]);
        $responseParameters->set('memberCenterQualificationEditUrls', $this->editLinks(MemberQualificationCrudController::class, $responseParameters->get('memberCenterQualifications')));
        $responseParameters->set('memberCenterCourseEditUrls', $this->editLinks(CourseParticipantCrudController::class, $responseParameters->get('memberCenterCourses')));
        $responseParameters->set('memberCenterTeamEditUrls', $this->editLinks(TeamMemberCrudController::class, $responseParameters->get('memberCenterTeams')));
        return $responseParameters;
    }

    private function adminLink(string $controller, string $action, ?int $id = null): string
    {
        $generator = (clone $this->adminUrlGenerator)->unsetAll()
            ->setController($controller)->setAction($action);
        if ($id !== null) {
            $generator->setEntityId($id);
        }
        return $generator->generateUrl();
    }
	
	private function memberReturnUrl(int $memberId): string
	{
		return (clone $this->adminUrlGenerator)
			->unsetAll()
			->setController(self::class)
			->setAction(Crud::PAGE_DETAIL)
			->setEntityId($memberId)
			->generateUrl();
	}

	private function memberPrefillUrl(
		string $controller,
		int $memberId
	): string {
		return (clone $this->adminUrlGenerator)
			->unsetAll()
			->setController($controller)
			->setAction(Crud::PAGE_NEW)
			->set('memberId', $memberId)
			->set('returnToMember', '1')
			->generateUrl();
	}

	private function editLinks(
		string $controller,
		iterable $entities
	): array {
		$links = [];

		foreach ($entities as $entity) {
			if ($entity->getId() === null) {
				continue;
			}

			$member = $entity->getMember();

			if (!$member instanceof Member || $member->getId() === null) {
				continue;
			}

			$links[$entity->getId()] =
				(clone $this->adminUrlGenerator)
					->unsetAll()
					->setController($controller)
					->setAction(Crud::PAGE_EDIT)
					->setEntityId($entity->getId())
					->set('memberId', $member->getId())
					->set('returnToMember', '1')
					->generateUrl();
		}

		return $links;
	}

    public static function getEntityFqcn(): string
    {
        return Member::class;
    }

	public function configureCrud(Crud $crud): Crud
	{
		return $crud
			->overrideTemplate(
				'crud/detail',
				'admin/member/detail.html.twig'
			)
			->setEntityLabelInSingular('Mitglied')
			->setEntityLabelInPlural('Mitglieder')
			->setPageTitle(Crud::PAGE_INDEX, 'Mitglieder')
			->setPageTitle(Crud::PAGE_NEW, 'Mitglied anlegen')
			->setPageTitle(Crud::PAGE_EDIT, 'Mitglied bearbeiten')
			->setPageTitle(Crud::PAGE_DETAIL, 'Mitglied')
			->setDefaultSort([
				'person.lastname' => 'ASC',
				'person.firstname' => 'ASC',
			])
			->setSearchFields([
				'memberNumber',
				'person.firstname',
				'person.lastname',
				'person.email',
				'person.city',
			]);
	}

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_EDIT || $pageName === Crud::PAGE_DETAIL) {
            $entity = $this->getContext()?->getEntity()?->getInstance();
            if ($entity instanceof Member) {
                $this->assertMemberAccess($entity);
            }
        }

        if ($pageName === Crud::PAGE_DETAIL) {
			yield IdField::new('id', 'ID');

			return;
		}

        /*
         * Übersicht
         */
        yield IdField::new('id', 'ID')
            ->onlyOnIndex();

        yield TextField::new('memberNumber', 'Mitgliedsnr.')
            ->onlyOnIndex();

        yield AssociationField::new('club', 'Verein')
            ->onlyOnIndex();

        yield ChoiceField::new('membershipStatus', 'Status')
            ->setChoices(MembershipStatus::choices())
            ->onlyOnIndex();

        yield TextField::new('sportNames', 'Sportarten')
            ->onlyOnIndex();

        /*
         * Mitgliedschaft
         */
        yield FormField::addFieldset('Mitgliedschaft')
            ->collapsible();

        yield AssociationField::new('club', 'Verein')
            ->setFormTypeOption('disabled', !$this->isGranted('ROLE_SUPER_ADMIN'))
            ->setRequired(true)
            ->setColumns(4)
            ->hideOnIndex();

        yield TextField::new('memberNumber', 'Mitgliedsnummer')
            ->setColumns(3)
            ->hideOnIndex();

        yield ChoiceField::new('membershipStatus', 'Status')
            ->setChoices(MembershipStatus::choices())
            ->setRequired(true)
            ->setColumns(3)
            ->hideOnIndex();

        yield DateField::new('joinedAt', 'Eintritt')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3)
            ->hideOnIndex();

        yield DateField::new('leftAt', 'Austritt')
            ->setFormat('dd.MM.yyyy')
            ->setColumns(3)
            ->hideOnIndex();

        /*
         * Person
         */
        yield FormField::addFieldset('Personendaten')
            ->collapsible();

        yield TextField::new('firstname', 'Vorname')
            ->setSortable(false)
            ->setRequired(true)
            ->setColumns(4);

        yield TextField::new('lastname', 'Nachname')
            ->setSortable(false)
            ->setRequired(true)
            ->setColumns(4);

        yield DateField::new('birthday', 'Geburtsdatum')
            ->setRequired(true)
            ->setColumns(3)
            ->setFormat('dd.MM.yyyy')
            ->setHelp('Format: TT.MM.JJJJ')
            ->hideOnIndex();

        /*
         * Das alte category-Feld wird bewusst nicht mehr angezeigt.
         *
         * Die Zuordnung zu Sportarten erfolgt jetzt über MemberSport.
         * Instructor/Trainer wird später über Qualifikationen abgebildet.
         */

        /*
         * Anschrift
         */
        yield FormField::addFieldset('Adressdaten')
            ->collapsible();

        yield TextField::new('street', 'Straße / Hausnummer')
            ->setColumns(6)
            ->hideOnIndex();

        yield TextField::new('postalCode', 'PLZ')
            ->setColumns(2)
            ->hideOnIndex();

        yield TextField::new('city', 'Wohnort')
            ->setColumns(4)
            ->hideOnIndex();

        /*
         * Kontakt
         */
        yield FormField::addFieldset('Kontaktdaten')
            ->collapsible();

        yield EmailField::new('email', 'E-Mail')
            ->setSortable(false)
            ->setColumns(4);

        yield TelephoneField::new('mobile', 'Mobil')
            ->setSortable(false)
            ->setColumns(3);

        yield TelephoneField::new('phone', 'Telefon')
            ->setColumns(3)
            ->hideOnIndex();

        /*
         * Sportarten
         */
        if ($pageName === Crud::PAGE_DETAIL) {
            yield FormField::addFieldset('Sportarten')->collapsible();
            yield TextField::new('sportNames', 'Zugeordnete Sportarten')
                ->onlyOnDetail();
        }

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield FormField::addFieldset('Sportarten')->collapsible();
        }

        yield CollectionField::new('sports', 'Sportarten')
            ->setEntryType(MemberSportType::class)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            ->setHelp(
                'Hier können dem Mitglied eine oder mehrere '
                . 'Sportarten zugeordnet werden.'
            )
            ->onlyOnForms();

        if ($pageName === Crud::PAGE_DETAIL) {
            yield FormField::addFieldset('Qualifikationen')->collapsible();
            yield TextField::new('qualificationNames', 'Qualifikationen und Brevets')
                ->onlyOnDetail();
        }

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield FormField::addFieldset('Qualifikationen')->collapsible();
        }

		yield CollectionField::new(
			'qualifications',
			'Qualifikationen & Brevets'
		)
			->setEntryType(MemberQualificationType::class)
			->allowAdd()
			->allowDelete()
			->setFormTypeOption('by_reference', false)
			->setHelp(
				'Brevets, Trainer- und Tauchlehrerqualifikationen, '
				. 'Erste Hilfe, Sauerstoff, Technik usw.'
			)
			->onlyOnForms();
		
        /*
         * Benutzerkonto
         */
        yield FormField::addFieldset('Benutzerkonto')
            ->collapsible();

        yield AssociationField::new('user', 'Benutzer')
            ->setFormTypeOption('disabled', !$this->isGranted('ROLE_SUPER_ADMIN'))
            ->setRequired(false)
            ->setColumns(4)
            ->setHelp(
                'Optionales Benutzerkonto für den Zugang '
                . 'zum Mitgliederportal.'
            );

        /*
         * Weitere Informationen
         */
        yield FormField::addFieldset('Weitere Informationen')
            ->collapsible();

        yield TextareaField::new('notes', 'Notizen')
            ->hideOnIndex();

        yield BooleanField::new('published', 'Veröffentlicht')
            ->setColumns(2);
    }
}