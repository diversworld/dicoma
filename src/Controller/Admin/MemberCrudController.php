<?php

namespace App\Controller\Admin;

use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use App\Security\Voter\MemberManagementVoter;
use App\Service\AdminClubContext;
use App\Service\MembershipRoleContinuity;
use App\Service\MembershipHistoryRecorder;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use App\Entity\MemberDocument;
use App\Entity\MemberQualification;
use App\Entity\CourseParticipant;
use App\Entity\TeamMember;
use App\Entity\ContributionClass;
use App\Entity\MembershipCharge;
use App\Entity\MembershipEvent;
use App\Service\MembershipFinanceService;
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
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type as F;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Form\MemberQualificationType;

#[IsGranted('ROLE_USER')]
class MemberCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $router,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly \App\Service\MembershipService $memberships,
        private readonly AdminClubContext $clubContext,
        private readonly MembershipRoleContinuity $continuity,
        private readonly MembershipFinanceService $finance,
        private readonly MembershipHistoryRecorder $history,
        private readonly FormFactoryInterface $forms,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $functionModelEnabled,
    ) {}

    /** Schritt 32: Listenfilter erfolgt in SQL, nicht erst beim Rendern. */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        if (!$this->isGranted(User::ROLE_ADMIN) && !$this->isGranted(User::ROLE_BOARD)) {
            throw $this->createAccessDeniedException();
        }
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->leftJoin('entity.person', 'memberPerson')->addSelect('memberPerson');
        if ($searchDto->getSort() === []) {
            $qb->orderBy('memberPerson.lastname', 'ASC')->addOrderBy('memberPerson.firstname', 'ASC');
        }
        if ($this->getContext()?->getRequest()->query->get('unassigned') === '1' && $this->isGranted('ROLE_SUPER_ADMIN')) {
            return $qb->andWhere('entity.club IS NULL');
        }
        $clubId = $this->clubContext->getClub()?->getId();
        if ($clubId === null) {
            $qb->andWhere('1 = 0');
        } else {
            $qb->andWhere('entity.club = :memberManagedClub')->setParameter('memberManagedClub', $clubId);
        }
        return $qb;
    }

    private function assertMemberAccess(Member $member): void
    {
        $this->denyAccessUnlessGranted(MemberManagementVoter::MANAGE, $member);
        $selectedId = $this->clubContext->getClub()?->getId();
        $memberClubId = $member->getClub()?->getId();
        if ($selectedId === null || ($memberClubId !== $selectedId && !($memberClubId === null && $this->isGranted('ROLE_SUPER_ADMIN')))) {
            throw $this->createAccessDeniedException('Mitglied gehört nicht zum ausgewählten Verein.');
        }
    }

    /** Schritte 34-36: Formulardaten duerfen keine fremde Vereinszuordnung erzeugen. */
    private function enforceMemberClub(Member $member, ?EntityManagerInterface $em = null): void
    {
        $club = $this->clubContext->getClub();
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
            if ($storedClubId === false || ($storedClubId === null ? !$this->isGranted('ROLE_SUPER_ADMIN') : (int) $storedClubId !== $club->getId())) {
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
        $club = $this->clubContext->getClub() ?? throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
        $member->setClub($club);
        return $member;
    }

    public function edit(AdminContext $context)
    {
        $this->assertMemberAccess($context->getEntity()->getInstance());
        return parent::edit($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Member) {
            $entityManager->getConnection()->transactional(function () use ($entityManager, $entityInstance): void {
                $this->enforceMemberClub($entityInstance);
                $this->continuity->lockClub($entityInstance->getClub());
                $this->assertAccountAssignment($entityManager, $entityInstance, null);
                $entityManager->persist($entityInstance);
                if ($entityInstance->getContributionClass() !== null
                    || $entityInstance->getFamilyPayer() !== null
                    || $entityInstance->getContributionDiscountCents() !== 0
                    || $entityInstance->getContributionDiscountReason() !== null) {
                    $this->finance->assign(
                        $entityInstance,
                        $entityInstance->getContributionClass(),
                        $entityInstance->getFamilyPayer(),
                        $entityInstance->getContributionDiscountCents(),
                        $entityInstance->getContributionDiscountReason(),
                        $this->getUser()->getUserIdentifier(),
                    );
                }
                $this->memberships->record($entityInstance, 'created', $this->getUser()->getUserIdentifier(), 'Mitgliedschaft durch die Verwaltung angelegt.');
                parent::persistEntity($entityManager, $entityInstance);
            });
            return;
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Member) {
            $entityManager->getConnection()->transactional(function () use ($entityManager, $entityInstance): void {
                $this->enforceMemberClub($entityInstance, $entityManager);
                $this->continuity->lockClub($entityInstance->getClub());
                $connection = $entityManager->getConnection();
                $lock = $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
                $stored = $connection->fetchAssociative('SELECT user_id FROM member WHERE id = ?'.$lock, [$entityInstance->getId()]);
                if ($stored === false) { throw new ConflictHttpException('Mitgliedschaft nicht mehr vorhanden.'); }
                $this->assertMemberAccess($entityInstance);
                $this->assertAccountAssignment($entityManager, $entityInstance, $stored['user_id'] === null ? null : (int) $stored['user_id']);
                $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
                $personOriginal = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance->getPerson());
                if (($original['contributionClass'] ?? null) !== $entityInstance->getContributionClass()
                    || ($original['familyPayer'] ?? null) !== $entityInstance->getFamilyPayer()
                    || ($original['contributionDiscountCents'] ?? 0) !== $entityInstance->getContributionDiscountCents()
                    || ($original['contributionDiscountReason'] ?? null) !== $entityInstance->getContributionDiscountReason()) {
                    $this->finance->assign(
                        $entityInstance,
                        $entityInstance->getContributionClass(),
                        $entityInstance->getFamilyPayer(),
                        $entityInstance->getContributionDiscountCents(),
                        $entityInstance->getContributionDiscountReason(),
                        $this->getUser()->getUserIdentifier(),
                    );
                }
                $changes = [];
                foreach (['memberNumber', 'membershipType', 'clubFunctions', 'terminationDate', 'joinedAt', 'leftAt', 'membershipStatus', 'contributionClass', 'familyPayer', 'contributionDiscountCents', 'contributionDiscountReason', 'notes', 'published', 'category', 'status'] as $field) {
                    $getter = 'get'.ucfirst($field);
                    if ($field === 'published') { $getter = 'isPublished'; }
                    if (method_exists($entityInstance, $getter)) {
                        $changes[$field] = ['from' => $original[$field] ?? null, 'to' => $entityInstance->$getter()];
                    }
                }
                foreach (['firstname', 'lastname', 'birthday', 'gender', 'email', 'mobile', 'phone', 'street', 'postalCode', 'city'] as $field) {
                    $getter = 'get'.ucfirst($field);
                    if (method_exists($entityInstance->getPerson(), $getter)) {
                        $changes['person.'.$field] = ['from' => $personOriginal[$field] ?? null, 'to' => $entityInstance->getPerson()->$getter()];
                    }
                }
                $this->history->record($entityInstance, $this->getUser()->getUserIdentifier(), 'administrative_update', $changes);
                $this->memberships->record($entityInstance, 'administrative_update', $this->getUser()->getUserIdentifier(), 'Verwaltungspflichtige Mitglieds- oder Stammdaten geändert.');
                parent::updateEntity($entityManager, $entityInstance);
            });
            return;
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function assertAccountAssignment(EntityManagerInterface $em, Member $member, ?int $oldUserId): void
    {
        $connection = $em->getConnection();
        $lock = $connection->getDatabasePlatform() instanceof AbstractMySQLPlatform ? ' FOR UPDATE' : '';
        $newUserId = $member->getUser()?->getId();
        $ids = array_values(array_unique(array_filter([$oldUserId, $newUserId])));
        sort($ids);
        foreach ($ids as $id) {
            $storedRoles = $connection->fetchOne('SELECT roles FROM `user` WHERE id = ?'.$lock, [$id]);
            if ($storedRoles === false) {
                throw new ConflictHttpException('Benutzerkonto nicht mehr vorhanden.');
            }
            $functions = array_diff(json_decode((string) $storedRoles, true, 512, JSON_THROW_ON_ERROR), [User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_SUPER_ADMIN]);
            $hasAssignments = $this->functionModelEnabled && (int) $connection->fetchOne('SELECT COUNT(*) FROM club_function_assignment WHERE user_id = ?', [$id]) > 0;
            if (($oldUserId !== $newUserId || ($id === $newUserId && !$member->isActiveMember())) && ($functions !== [] || $hasAssignments)) {
                throw new ConflictHttpException('Kontozuordnungen und inaktive Mitgliedschaften mit Funktionsrollen müssen zuerst gesondert geklärt werden.');
            }
        }
        if ($newUserId !== null) {
            $other = $connection->fetchOne('SELECT id FROM member WHERE user_id = ? AND id <> ?'.$lock, [$newUserId, $member->getId() ?? 0]);
            if ($other !== false) {
                throw new ConflictHttpException('Das Benutzerkonto ist bereits einer anderen Mitgliedschaft zugeordnet.');
            }
        }
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
        return $actions->add(Crud::PAGE_INDEX, $center)->disable(Action::DELETE);
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
        $responseParameters->set('memberCenterCharges', $em->getRepository(MembershipCharge::class)->findBy(['member' => $member], ['id' => 'DESC']));
        $responseParameters->set('memberCenterEvents', $em->getRepository(MembershipEvent::class)->findBy(['member' => $member], ['id' => 'DESC'], 20));
        $responseParameters->set('memberCenterForms', array_map(
            static fn (FormInterface $form) => $form->createView(),
            $this->memberActionForms($member),
        ));
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
            'membershipActions' => $this->router->generate('membership_member', ['id' => $member->getId()]),
        ]);
        $responseParameters->set('memberCenterQualificationEditUrls', $this->editLinks(MemberQualificationCrudController::class, $responseParameters->get('memberCenterQualifications')));
        $responseParameters->set('memberCenterCourseEditUrls', $this->editLinks(CourseParticipantCrudController::class, $responseParameters->get('memberCenterCourses')));
        $responseParameters->set('memberCenterTeamEditUrls', $this->editLinks(TeamMemberCrudController::class, $responseParameters->get('memberCenterTeams')));
        return $responseParameters;
    }

    private function memberActionForms(Member $member): array
    {
        return [
            'termination' => $this->forms->createNamedBuilder('membership_termination')->getForm(),
            'status' => $this->forms->createNamedBuilder('membership_status', F\FormType::class, [
                    'status' => $member->getMembershipStatus(),
                ])
                ->add('status', F\ChoiceType::class, [
                    'label' => 'Mitgliedschaftsstatus',
                    'choices' => array_filter(MembershipStatus::choices(), static fn ($status) => $status !== MembershipStatus::FORMER),
                ])
                ->getForm(),
            'annual' => $this->forms->createNamedBuilder('annual_charge', F\FormType::class, [
                    'year' => (int) date('Y'),
                    'due' => new \DateTimeImmutable('today'),
                ])
                ->add('year', F\IntegerType::class, [
                    'label' => 'Beitragsjahr',
                    'constraints' => [new Assert\Range(min: 2000, max: 2200)],
                ])
                ->add('due', F\DateType::class, [
                    'label' => 'Fälligkeit',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                ])
                ->getForm(),
            'special' => $this->forms->createNamedBuilder('special_charge')
                ->add('description', F\TextType::class, [
                    'label' => 'Bezeichnung',
                    'constraints' => [new Assert\NotBlank(), new Assert\Length(max: 180)],
                ])
                ->add('amount', F\MoneyType::class, [
                    'label' => 'Sonderbeitrag',
                    'currency' => 'EUR',
                    'divisor' => 100,
                    'input' => 'integer',
                    'constraints' => [new Assert\Range(min: 1, max: 100000000)],
                ])
                ->add('due', F\DateType::class, [
                    'label' => 'Fälligkeit',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                ])
                ->getForm(),
            'archive' => $this->forms->createNamedBuilder('membership_archive')->getForm(),
        ];
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
            ->setFormOptions(['csrf_token_id' => 'member_crud_'.($this->clubContext->getClub()?->getId() ?? 'none')])
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
            ->setFormTypeOption('choices', array_filter([$this->clubContext->getClub()]))
            ->setFormTypeOption('disabled', !$this->isGranted('ROLE_SUPER_ADMIN') || ($pageName !== Crud::PAGE_NEW && $this->getContext()?->getEntity()?->getInstance()?->getClub() !== null))
            ->setHelp('Bestehende Vereinsmitgliedschaften nicht verschieben; Aufnahme in einen anderen Verein separat erfassen.')
            ->setRequired(true)
            ->setColumns(4)
            ->hideOnIndex();

        yield TextField::new('memberNumber', 'Mitgliedsnummer')
            ->setColumns(3)
            ->hideOnIndex();

        yield ChoiceField::new('membershipStatus', 'Status')
            ->setChoices(MembershipStatus::choices())
            ->setFormTypeOption('disabled', $pageName !== Crud::PAGE_NEW)
            ->setHelp('Statuswechsel, Kündigung und Archivierung erfolgen in der Mitgliederverwaltung.')
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
            ->onlyOnDetail();

        yield TextField::new('clubFunctions', 'Vereinsfunktionen (keine Berechtigungsvergabe)')
            ->setColumns(3)
            ->hideOnIndex();

        if ($pageName === Crud::PAGE_NEW || $pageName === Crud::PAGE_EDIT) {
            yield FormField::addFieldset('Beiträge und Abrechnung')->collapsible();

            yield AssociationField::new('contributionClass', 'Beitragsklasse')
                ->setFormTypeOption('choices', $this->contributionChoices())
                ->setRequired(false)
                ->setColumns(4)
                ->setHelp('Direkte Beitragszuordnung des Mitglieds; Familienmitglieder bleiben hier ohne eigene Klasse.')
                ->onlyOnForms();

            yield AssociationField::new('familyPayer', 'Familienzahler')
                ->setFormTypeOption('choices', $this->memberChoices())
                ->setRequired(false)
                ->setColumns(4)
                ->setHelp('Nur für ein Familienmitglied ohne eigene Beitragsklasse und Ermäßigung.')
                ->onlyOnForms();

            yield MoneyField::new('contributionDiscountCents', 'Jährliche Ermäßigung')
                ->setStoredAsCents()
                ->setCurrency('EUR')
                ->setColumns(2)
                ->onlyOnForms();

            yield TextareaField::new('contributionDiscountReason', 'Ermäßigungsgrund')
                ->setColumns(6)
                ->onlyOnForms();
        }

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

        yield ChoiceField::new('gender', 'Geschlecht')
            ->setChoices(['Keine Angabe' => null, 'Weiblich' => 'female', 'Männlich' => 'male', 'Divers' => 'diverse', 'Nicht spezifiziert' => 'unspecified'])
            ->setRequired(false)
            ->setColumns(3)
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
         * Notfallkontakt
         */
        yield FormField::addFieldset('Notfallkontakt')
            ->collapsible();
        yield TextField::new('emergencyContactName', 'Notfallkontakt: Name')
            ->setColumns(3)
            ->hideOnIndex();
        yield TelephoneField::new('emergencyContactPhone', 'Notfallkontakt: Telefon')
            ->setColumns(3)
            ->hideOnIndex();
        yield TextField::new('emergencyContactRelation', 'Notfallkontakt: Beziehung')
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
            ->setFormTypeOption('entry_options', ['club' => $this->clubContext->getClub()])
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
                'Optionales Benutzerkonto für den Zugang zum Mitgliederportal. '
                . 'Konten mit Funktionsrollen müssen vor einer Zuordnungsänderung gesondert geklärt werden.'
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

    private function memberChoices(): array
    {
        $club = $this->clubContext->getClub();
        if ($club === null) {
            return [];
        }

        return $this->entityManager->getRepository(Member::class)->createQueryBuilder('m')
            ->join('m.person', 'p')
            ->where('m.club = :club')
            ->andWhere('m.archivedAt IS NULL')
            ->setParameter('club', $club)
            ->orderBy('p.lastname', 'ASC')
            ->addOrderBy('p.firstname', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function contributionChoices(): array
    {
        $club = $this->clubContext->getClub();
        if ($club === null) {
            return [];
        }

        $currentId = $this->getContext()?->getEntity()?->getInstance()?->getContributionClass()?->getId();
        $query = $this->entityManager->getRepository(ContributionClass::class)->createQueryBuilder('contribution')
            ->where('contribution.club = :club')
            ->andWhere('contribution.active = :active OR contribution.id = :currentId')
            ->setParameter('club', $club)
            ->setParameter('active', true)
            ->setParameter('currentId', $currentId ?? 0)
            ->orderBy('contribution.name', 'ASC');

        return $query->getQuery()->getResult();
    }
}