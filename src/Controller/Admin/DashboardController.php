<?php

namespace App\Controller\Admin;

use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Schedule;
use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use App\Entity\TankCheckDetail;
use App\Entity\User;
use App\Entity\Club;
use App\Entity\Sport;
use App\Entity\MemberSport;
use App\Entity\Vendor;
use App\Entity\Team;
use App\Entity\Qualification;
use App\Entity\TeamMember;
use App\Entity\MemberQualification;
use App\Entity\CourseParticipant;
use App\Entity\TrainingUnitType;
use App\Entity\ScheduleTemplate;
use App\Entity\TrainingUnitResult;
use App\Entity\MembershipApplication;
use App\Entity\WebsitePublication;
use App\Enum\CourseStatus;
use App\Controller\Admin\MemberCrudController;
use App\Security\Voter\MemberManagementVoter;
use \App\Entity\TuvInvoiceSettings;
use App\Controller\Admin\EquipmentReservationCrudController;
use App\Controller\Admin\MemberQualificationCrudController;
use App\Controller\Admin\MemberTankCheckBooking;
use App\Controller\Admin\EquipmentBookingCrudController;
use App\Controller\Admin\MemberTankCheckBookingCrudController;
use App\Service\TankInspectionOverview;
use App\Service\PublicWebsite;
use App\Repository\MemberQualificationRepository;
use App\Form\WebsitePublicationType;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Locale;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconSet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\FormError;
use App\Service\TuvMessageRenderer;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use \Doctrine\ORM\EntityManagerInterface;


#[AdminDashboard(
	routePath: '/admin/{_locale}', 
	routeName: 'admin', 
	routeOptions: [
		'requirements' => ['_locale' => 'de|en|fr|es'], 
		'defaults' => ['_locale' => 'de'],
		'methods' => ['GET'],],
	)
]
class DashboardController extends AbstractDashboardController
{
    use EquipmentBookingBasketTrait;

    public const ROUTE_DEFAULTS = [
        'routeCreatedByEasyAdmin' => true,
        'dashboardControllerFqcn' => self::class,
    ];

    #[AdminRoute(path: '/equipment-center', name: 'equipment_center')]
    public function equipmentCenter(TankInspectionOverview $overview): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $tankUrl = (clone $this->adminUrlGenerator)
            ->unsetAll()->setController(TankCrudController::class)
            ->setAction(Crud::PAGE_INDEX)->generateUrl();
        $checkUrl = (clone $this->adminUrlGenerator)
            ->unsetAll()->setController(TankCheckCrudController::class)
            ->setAction(Crud::PAGE_INDEX)->generateUrl();

        return $this->render('admin/equipment/center.html.twig', [
            'overview' => $overview->getOverview(),
            'tankUrl' => $tankUrl,
            'checkUrl' => $checkUrl,
        ]);
    }

	public function __construct(
		private readonly MemberQualificationRepository $memberQualificationRepository, 
		private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly PublicWebsite $publicWebsite
	) {
	}
	
	public function index(): Response
	{
        $club = null;
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            $user = $this->getUser();
            $club = $user instanceof User ? $user->getMember()?->getClub() : null;
            if ($club === null || $club->getId() === null) {
                throw $this->createAccessDeniedException('Keine Vereinszuordnung vorhanden.');
            }
        }
        $qualificationUrl = (clone $this->adminUrlGenerator)->unsetAll()
			->setController(MemberQualificationCrudController::class)
			->setAction(Crud::PAGE_INDEX);
        $canManageQualifications = $this->isGranted('ROLE_SUPER_ADMIN');
        $now = new \DateTimeImmutable();
        $activeStatuses = [CourseStatus::OPEN->value, CourseStatus::RUNNING->value];
        $courses = $this->em->getRepository(Courses::class)->createQueryBuilder('c')
            ->select('COUNT(c.id)')->where('c.status IN (:statuses)')
            ->setParameter('statuses', $activeStatuses);
        $schedules = $this->em->getRepository(Schedule::class)->createQueryBuilder('s')
            ->addSelect('c')->innerJoin('s.courses', 'c')
            ->where('c.status IN (:statuses)')
            ->andWhere('s.startDate > :today OR (s.startDate = :today AND (s.startTime IS NULL OR s.startTime >= :now))')
            ->setParameter('statuses', $activeStatuses)
            ->setParameter('today', $now->format('Y-m-d'))
            ->setParameter('now', $now->format('H:i:s'))
            ->orderBy('s.startDate', 'ASC')->addOrderBy('s.startTime', 'ASC')
            ->addOrderBy('s.id', 'ASC')->setMaxResults(5);
        $applicationCriteria = ['status' => 'pending'];
        if ($club !== null) {
            $courses->andWhere('c.club = :club')->setParameter('club', $club);
            $schedules->andWhere('c.club = :club')->setParameter('club', $club);
            $applicationCriteria['club'] = $club;
        }
        $applications = $this->em->getRepository(MembershipApplication::class);

        $membersUrl = (clone $this->adminUrlGenerator)->unsetAll()
            ->setController(MemberCrudController::class)->setAction(Crud::PAGE_INDEX)->generateUrl();

		return $this->render('admin/dashboard/dashboard.html.twig', [
            'dashboardScope' => $club?->getName(),
            'activeCourseCount' => (int) $courses->getQuery()->getSingleScalarResult(),
            'upcomingSchedules' => $schedules->getQuery()->getResult(),
            'pendingApplicationCount' => $applications->count($applicationCriteria),
            'pendingApplications' => $applications->findBy($applicationCriteria, ['submittedAt' => 'ASC', 'id' => 'ASC'], 5),
            'quickLinks' => [
                ['label' => 'Mitglieder verwalten', 'icon' => 'fa-users', 'url' => $membersUrl],
                ['label' => 'Mitgliedschaften und Beiträge', 'icon' => 'fa-address-card', 'url' => $this->generateUrl('membership_admin')],
                ['label' => 'Ausbildungsportal', 'icon' => 'fa-graduation-cap', 'url' => $this->generateUrl('admin_portal_training_portal')],
                ['label' => 'Kurskalender', 'icon' => 'fa-calendar', 'url' => $this->generateUrl('admin_course_calendar')],
            ],
			'expiredQualifications' =>
				$this->memberQualificationRepository
					->findExpired($club, 5),

			'expiringQualifications' =>
				$this->memberQualificationRepository
					->findExpiringWithinDays(30, $club, 5),

			'expiredQualificationCount' =>
				$this->memberQualificationRepository
					->countExpired($club),

			'expiringQualificationCount' =>
				$this->memberQualificationRepository
					->countExpiringWithinDays(30, $club),
			
			'expiredQualificationsUrl' =>
				$canManageQualifications ? (clone $qualificationUrl)->set('validity', 'expired')->generateUrl() : null,

			'expiringQualificationsUrl' =>
				$canManageQualifications ? (clone $qualificationUrl)->set('validity', 'expiring')->generateUrl() : null,

			'allQualificationsUrl' =>
				$canManageQualifications ? (clone $qualificationUrl)->generateUrl() : null,
		]);
	}

    #[AdminRoute(path: '/settings/tuv-notifications', name: 'settings_tuv_notifications', options: ['methods' => ['GET']])]
    public function tuvNotificationSettings(Request $request, TuvMessageRenderer $renderer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $kind = (string) $request->query->get('kind', 'confirmation');
        if (!isset(TuvMessageRenderer::LABELS[$kind])) {
            throw $this->createNotFoundException();
        }
        return $this->render('admin/tuv/message_templates.html.twig', [
            'kinds' => TuvMessageRenderer::LABELS,
            'kind' => $kind,
            'template' => $renderer->getTemplate($kind),
        ]);
    }

    #[AdminRoute(path: '/settings/tuv-notifications/save', name: 'settings_tuv_notifications_save', options: ['methods' => ['POST']])]
    public function saveTuvNotificationSettings(Request $request, TuvMessageRenderer $renderer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        if (!$this->isCsrfTokenValid('tuv_message_template', (string) $request->request->get('_token', ''))) {
            throw $this->createAccessDeniedException();
        }
        $kind = (string) $request->request->get('kind', '');
        try {
            $renderer->save($kind, trim((string) $request->request->get('subject', '')), (string) $request->request->get('body', ''), $request->request->has('enabled'));
            $this->addFlash('success', 'Benachrichtigungsvorlage gespeichert.');
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('admin_settings_tuv_notifications', [
            'kind' => isset(TuvMessageRenderer::LABELS[$kind]) ? $kind : 'confirmation',
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<h4>DiveClubManager</h4>')
            ->setFaviconPath('images/favicon.ico')
            // to customize the labels of locales, pass a key => value array
            // (e.g. to display flags; although it's not a recommended practice,
            // because many languages/locales are not associated to a single country)
            ->setLocales([
                Locale::new('de', 'DE Deutsch', 'locale-flag locale-flag-de'), // locale without custom options
                Locale::new('en', 'GB English', 'locale-flag locale-flag-gb'), // custom label and icon
				Locale::new('fr', 'FR Français', 'locale-flag locale-flag-fr'), // custom label and icon
                Locale::new('es', 'ES Español', 'locale-flag locale-flag-es') // custom label and icon
            ])
            ;
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
           ->addCssFile('css/theme-variables.css')
		   ->addCssFile('css/admin.css')
			->addCssFile('css/admin-logout.css')
			->addCssFile('css/member-center.css?v=3')
            ->addCssFile('css/utilities.css');
	}

    #[AdminRoute(path: '/editorial', name: 'website_editorial', options: ['methods' => ['GET']])]
    public function websiteEditorial(PublicWebsite $website): Response
    {
        $this->denyAccessUnlessGranted(User::ROLE_PRESS_OFFICER);

        return $this->render('admin/editorial/index.html.twig', [
            'publications' => $this->em->getRepository(WebsitePublication::class)
                ->findBy(['club' => $website->editorialClub()], ['id' => 'DESC']),
        ]);
    }

    #[AdminRoute(path: '/editorial/new', name: 'website_editorial_new', options: ['methods' => ['GET', 'POST']])]
    public function newWebsitePublication(Request $request, PublicWebsite $website): Response
    {
        return $this->renderWebsitePublicationForm($request, $website);
    }

    #[AdminRoute(path: '/editorial/{entityId}/edit', name: 'website_editorial_edit', options: ['methods' => ['GET', 'POST'], 'requirements' => ['entityId' => '\d+']])]
    public function editWebsitePublication(
        Request $request,
        PublicWebsite $website,
        int $entityId
    ): Response {
        return $this->renderWebsitePublicationForm($request, $website, $entityId);
    }

    private function renderWebsitePublicationForm(
        Request $request,
        PublicWebsite $website,
        ?int $entityId = null
    ): Response {
        $this->denyAccessUnlessGranted(User::ROLE_PRESS_OFFICER);
        $club = $website->editorialClub();
        $publication = $entityId === null
            ? new WebsitePublication($club)
            : $this->em->getRepository(WebsitePublication::class)->findOneBy(['id' => $entityId, 'club' => $club]);
        if ($publication === null) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(WebsitePublicationType::class, $publication, ['club' => $club]);
        $form->handleRequest($request);
        $conflict = false;
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($entityId !== null && (string) $form->get('version')->getData() !== (string) $publication->getVersion()) {
                    throw new \DomainException('Der Beitrag wurde inzwischen geändert. Bitte neu laden.');
                }
                $publication->record($this->getUser()->getUserIdentifier());
                $this->em->persist($publication);
                $this->em->flush();
                $this->addFlash('success', $publication->isPublished()
                    ? 'Beitrag freigegeben. Sichtbar ab dem Veröffentlichungszeitpunkt, sofern die verknüpfte Veranstaltung freigegeben ist.'
                    : 'Entwurf gespeichert; nicht öffentlich sichtbar.');

                return $this->redirectToRoute('admin_website_editorial', ['_locale' => $request->getLocale()], Response::HTTP_SEE_OTHER);
            } catch (OptimisticLockException|\DomainException) {
                $form->addError(new FormError('Gleichzeitige Änderung: Bitte den Beitrag neu laden. Nicht gespeichert.'));
                $conflict = true;
            }
        }

        return $this->render('admin/editorial/edit.html.twig', [
            'form' => $form,
            'publication' => $publication,
        ], new Response(status: $conflict ? 409 : ($form->isSubmitted() ? 422 : 200)));
    }

    // DICOMA invoice settings EasyAdmin integration v1
    #[AdminRoute(path: '/invoice-settings', name: 'invoice_settings_overview')]
    public function invoiceSettingsOverview(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->render('admin/tuv_invoice_settings_overview_ea.html.twig', [
            'clubs' => $em->getRepository(Club::class)->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[AdminRoute(path: '/invoice-settings/{id}', name: 'invoice_settings_edit')]
    public function invoiceSettingsEdit(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $club = $em->getRepository(Club::class)->find($id);
        if (!$club) { throw $this->createNotFoundException(); }
        $settings = $em->getRepository(TuvInvoiceSettings::class)->find($id);
        if (!$settings) { $settings = new TuvInvoiceSettings($club); }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('invoice_settings_'.$id, (string) $request->request->get('_token', ''))) {
                throw $this->createAccessDeniedException('Ungueltiges CSRF-Token.');
            }
            try {
                $settings->configure(
                    (string) $request->request->get('tax_mode', ''),
                    (string) $request->request->get('tax_number', ''),
                    (string) $request->request->get('vat_id', ''),
                    (string) $request->request->get('invoice_note', '')
                );
                $settings->setMemberAgencyEnabled($request->request->get('member_agency_enabled') === '1');
                $em->persist($settings);
                $em->flush();
                $this->addFlash('success', 'Rechnungseinstellungen gespeichert.');
                return $this->redirectToRoute('admin_invoice_settings_edit', ['id' => $id]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }
        return $this->render('admin/tuv_invoice_settings_ea.html.twig', [
            'club' => $club, 'settings' => $settings,
        ]);
    }

    #[AdminRoute(path: '/invoice-agency-confirmations', name: 'invoice_agency_confirmations')]
    public function invoiceAgencyConfirmations(\Doctrine\ORM\EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        $groups = $em->getRepository(\App\Entity\TuvBookingGroup::class)->createQueryBuilder('g')
            ->where('g.agencyConfirmedAt IS NOT NULL')
            ->orderBy('g.agencyConfirmedAt', 'DESC')
            ->setMaxResults(200)->getQuery()->getResult();
        return $this->render('admin/tuv_agency_confirmations_ea.html.twig', ['groups' => $groups]);
    }

    #[AdminRoute(path: '/training-workflow/{id}/{step}', name: 'training_workflow', options: ['methods' => ['GET']])]
    public function trainingWorkflow(
        int $id,
        string $step
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $route = match ($step) {
            'import' => 'admin_course_import_confirm',
            'generate' => 'admin_course_generate_confirm',
            'plan' => 'admin_course_plan',
            default => throw $this->createNotFoundException('Unbekannte Kursaktion.'),
        };
        return $this->redirectToRoute($route, ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[AdminRoute(path: '/training-overview', name: 'training_overview')]
    public function trainingOverview(EntityManagerInterface $em): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_TRAINING_MANAGER')) {
            throw $this->createAccessDeniedException();
        }
        $courses = array_filter($em->getRepository(Courses::class)->findAll(),
            fn (Courses $course): bool => $this->isGranted(\App\Security\Voter\CourseManagementVoter::MANAGE, $course));
        $rows = [];
        $statuses = [];
        $instructorLoad = [];
        $now = new \DateTimeImmutable('today');
        $active = 0;
        foreach ($courses as $course) {
            $status = $course->getStatus()->value;
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $participants = $course->getParticipants()->count();
            $capacity = $course->getMaxParticipants();
            $appointments = 0;
            foreach ($course->getSchedule() as $appointment) {
                if ($appointment->getStartDate() && $appointment->getStartDate()->format('Y-m-d') >= $now->format('Y-m-d')) {
                    ++$appointments;
                }
                $instructor = $appointment->getInstructor();
                if ($instructor !== null) {
                    $id = $instructor->getId();
                    if ($id !== null) {
                        if (!isset($instructorLoad[$id])) {
                            $instructorLoad[$id] = ['name' => (string) $instructor, 'appointments' => 0, 'hours' => 0.0];
                        }
                        ++$instructorLoad[$id]['appointments'];
                        $instructorLoad[$id]['hours'] += $appointment->getDurationHours() ?? 0.0;
                    }
                }
            }
            if ($appointments > 0) ++$active;
            $rows[] = ['id' => $course->getId(), 'title' => (string) $course,
                'status' => $status, 'participants' => $participants,
                'capacity' => $capacity, 'upcoming' => $appointments,
                'occupancy' => $capacity && $capacity > 0 ? round(100 * $participants / $capacity) : null];
        }
        usort($rows, static fn (array $a, array $b): int => $b['upcoming'] <=> $a['upcoming']);
        uasort($instructorLoad, static fn (array $a, array $b): int => $b['hours'] <=> $a['hours']);
        return $this->render('admin/training/overview.html.twig', [
            'courses' => $rows, 'statuses' => $statuses,
            'instructors' => $instructorLoad, 'active' => $active,
            'total' => count($rows),
        ]);
    }

    public function configureMenuItems(): iterable
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return [
                MenuItem::section('Website & Redaktion'),
                MenuItem::linkToRoute('Artikel und Veranstaltungen', 'fa fa-newspaper', 'admin_website_editorial')
                    ->setPermission('ROLE_PRESS_OFFICER'),
            ];
        }

        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-house'),
            MenuItem::linkToRoute('Veranstaltungskalender', 'fa fa-calendar-days', 'club_calendar'),
            ...($this->publicWebsite->canManageEditorial() ? [
                MenuItem::section('Website & Redaktion'),
                MenuItem::linkToRoute('Artikel und Veranstaltungen', 'fa fa-newspaper', 'admin_website_editorial')
                    ->setPermission('ROLE_PRESS_OFFICER'),
            ] : []),

            MenuItem::section('Tägliche Verwaltung'),
            MenuItem::linkToRoute('Veranstaltungsbuchungen', 'fa fa-calendar-check', 'admin_club_events'),
            MenuItem::linkToRoute('Thekendienste und Thekenkasse', 'fa fa-mug-hot', 'admin_portal_bar_duty')->setPermission('ROLE_BAR_MANAGER'),

            MenuItem::subMenu('Mitglieder & Verein', 'fa fa-building')->setSubItems([
                MenuItem::linkTo(MemberCrudController::class, 'Mitglieder', 'fa fa-users'),
                MenuItem::linkToRoute('Mitgliedschaften und Beiträge', 'fa fa-address-card', 'membership_admin'),
                MenuItem::linkTo(PersonCrudController::class, 'Nichtmitglieder und Gäste', 'fa fa-address-book')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(MemberQualificationCrudController::class, 'Mitgliedsqualifikationen', 'fa fa-id-card')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Mannschaften & Turniere', 'fa fa-trophy')->setSubItems([
                MenuItem::linkToRoute('Mannschaften und Kader', 'fa fa-people-group', 'competition_teams'),
                MenuItem::linkToRoute('Turniere und Historie', 'fa fa-trophy', 'competition_tournaments'),
            ]),

            MenuItem::subMenu('Ausbildung & Kurse', 'fa fa-graduation-cap')
                ->setSubItems([
                MenuItem::linkToRoute('Ausbildungsportal', 'fa fa-graduation-cap', 'admin_portal_training_portal'),
                MenuItem::linkTo(CoursesCrudController::class, 'Kurse verwalten', 'fa fa-book-open'),
                MenuItem::linkTo(CourseParticipantCrudController::class, 'Kursteilnehmer', 'fa fa-users'),
                MenuItem::linkToRoute('Kurskalender', 'fa fa-calendar', 'admin_course_calendar'),
                MenuItem::linkToRoute('Ausbildungsübersicht', 'fa fa-chart-line', 'admin_training_overview'),
                MenuItem::linkToRoute('Ausbildungsaufgaben', 'fa fa-list-check', 'admin_instructor_tasks')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(ScheduleCrudController::class, 'Kurstermine', 'fa fa-calendar-days')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(BookingCrudController::class, 'Terminbuchungen', 'fa fa-ticket')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(TrainingUnitResultCrudController::class, 'Ausbildungsnachweise', 'fa fa-clipboard-check')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Ausrüstung & Verleih', 'fa fa-toolbox')->setPermission('ROLE_SUPER_ADMIN')->setSubItems([
                MenuItem::linkTo(EquipmentItemCrudController::class, 'Equipment', 'fa fa-boxes-stacked')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(RegulatorCrudController::class, 'Atemregler', 'fa fa-gauge')
                    ->setPermission('ROLE_SUPER_ADMIN'),
				MenuItem::linkTo(TankCrudController::class, 'Vereinsflaschen', 'fa fa-gauge')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentBookingCrudController::class, 'Sammelreservierungen', 'fa fa-layer-group')
                    ->setPermission('ROLE_SUPER_ADMIN'),

            ]),

            MenuItem::subMenu('Flaschenprüfungen (TÜV)', 'fa fa-clipboard-check')->setPermission('ROLE_SUPER_ADMIN')->setSubItems([
                MenuItem::linkToRoute('Prüfungsübersicht','fa fa-gauge-high','admin_equipment_center')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(MemberTankCheckBookingCrudController::class,'TÜV-Anmeldungen','fa fa-clipboard-check')
                    ->setAction(Action::INDEX)
                    ->setPermission('ROLE_SUPER_ADMIN'),

                MenuItem::linkTo(TankCheckCrudController::class,'TÜV-Termine','fa fa-calendar-check')
                    ->setPermission('ROLE_SUPER_ADMIN'),

                MenuItem::linkTo(TuvNotificationCrudController::class,'TÜV-Nachrichten / Postausgang','fa fa-envelope-open-text')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::section('Stammdaten & Einstellungen')->setPermission('ROLE_SUPER_ADMIN'),

            MenuItem::subMenu('Ausbildungsgrundlagen', 'fa fa-layer-group')->setPermission('ROLE_SUPER_ADMIN')->setSubItems([
                MenuItem::linkTo(ScheduleTemplateCrudController::class, 'Planungsvorlagen', 'fa fa-layer-group'),
                MenuItem::linkTo(TrainingUnitTypeCrudController::class, 'Ausbildungseinheiten', 'fa fa-list-check'),
                MenuItem::linkTo(QualificationCrudController::class, 'Qualifikationskatalog', 'fa fa-certificate'),
            ]),

            MenuItem::subMenu('Ausrüstungsstammdaten', 'fa fa-boxes-stacked')->setPermission('ROLE_SUPER_ADMIN')->setSubItems([
                MenuItem::linkTo(EquipmentCategoryCrudController::class, 'Ausrüstungsarten', 'fa fa-list')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentSubTypeCrudController::class, 'Ausrüstungsunterarten', 'fa fa-sitemap')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentManufacturerCrudController::class, 'Hersteller je Verein', 'fa fa-industry')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentSizeCrudController::class, 'Größen je Hersteller', 'fa fa-ruler')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentModelCrudController::class, 'Ausrüstungsmodelle', 'fa fa-tags')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(RegulatorModelCrudController::class, 'Atemreglermodelle', 'fa fa-gears')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentAttributeCrudController::class, 'Kategorie-Merkmale', 'fa fa-sliders')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(TankCheckArticleCrudController::class, 'TÜV-Preise', 'fa fa-tags')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(VendorCrudController::class, 'Lieferanten', 'fa fa-truck')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),
			
			MenuItem::subMenu('System & Einstellungen','fa fa-gear')->setPermission('ROLE_SUPER_ADMIN')->setSubItems([
                MenuItem::linkTo(ClubCrudController::class, 'Vereine', 'fa fa-building'),
                MenuItem::linkTo(SportCrudController::class, 'Sportarten', 'fa fa-person-swimming'),
                MenuItem::linkTo(MemberSportCrudController::class, 'Sportzuordnungen', 'fa fa-link'),
                MenuItem::linkTo(UserCrudController::class, 'Benutzerverwaltung', 'fa fa-users-gear'),
                MenuItem::linkToRoute('Rechnungseinstellungen', 'fa fa-file-invoice', 'admin_invoice_settings_overview'),
                MenuItem::linkToRoute('TÜV-Benachrichtigungstexte', 'fa fa-envelope', 'admin_settings_tuv_notifications'),
			]),
        ];
    }

    // Benutzerverwaltung ist ausschliesslich fuer Administratoren zugaenglich.
    // Keine Profil-Links auf administrative CRUD-Masken anbieten:
    // diese erlauben unter anderem die Verwaltung von Rollen.
    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return UserMenu::new()
            ->setName($user->getUserIdentifier())
            ->displayUserName(false)
            ->displayUserAvatar(false);
    }

	public function configureCrud(): Crud
	{
		return Crud::new()
			->overrideTemplate(
				'layout',
				'admin/layout.html.twig'
			);
	}
}
