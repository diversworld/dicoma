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
use App\Controller\Admin\MemberCrudController;
use App\Security\Voter\MemberManagementVoter;
use \App\Entity\TuvInvoiceSettings;
use App\Controller\Admin\EquipmentReservationCrudController;
use App\Controller\Admin\MemberQualificationCrudController;
use App\Controller\Admin\MemberTankCheckBooking;
use App\Controller\Admin\EquipmentBookingCrudController;
use App\Controller\Admin\MemberTankCheckBookingCrudController;
use App\Service\TankInspectionOverview;
use App\Repository\MemberQualificationRepository;
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
use App\Service\TuvMessageRenderer;
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

    #[AdminRoute(path: '/equipment-center', name: 'equipment_center')]
    public function equipmentCenter(TankInspectionOverview $overview): Response
    {
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
		private readonly AdminUrlGenerator $adminUrlGenerator
	) {
	}
	
	public function index(): Response
	{
		$expiredUrl = $this->adminUrlGenerator
			->setController(MemberQualificationCrudController::class)
			->setAction(Crud::PAGE_INDEX)
			->set('validity', 'expired')
			->generateUrl();

		$expiringUrl = $this->adminUrlGenerator
			->setController(MemberQualificationCrudController::class)
			->setAction(Crud::PAGE_INDEX)
			->set('validity', 'expiring')
			->generateUrl();

		$allQualificationsUrl = $this->adminUrlGenerator
			->setController(MemberQualificationCrudController::class)
			->setAction(Crud::PAGE_INDEX)
			->generateUrl();

		return $this->render('admin/dashboard/dashboard.html.twig', [
			'expiredQualifications' =>
				$this->memberQualificationRepository
					->findExpired(),

			'expiringQualifications' =>
				$this->memberQualificationRepository
					->findExpiringWithinDays(30),

			'expiredQualificationCount' =>
				$this->memberQualificationRepository
					->countExpired(),

			'expiringQualificationCount' =>
				$this->memberQualificationRepository
					->countExpiringWithinDays(30),
			
			'expiredQualificationsUrl' =>
				$expiredUrl,

			'expiringQualificationsUrl' =>
				$expiringUrl,

			'allQualificationsUrl' =>
				$allQualificationsUrl,
		]);
	}

    #[AdminRoute(path: '/settings/tuv-notifications', name: 'settings_tuv_notifications', options: ['methods' => ['GET']])]
    public function tuvNotificationSettings(Request $request, TuvMessageRenderer $renderer): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
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
		   ->addCssFile('css/admin.css')
			->addCssFile('css/admin-logout.css')
			->addCssFile('css/member-center.css?v=3');
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
        string $step,
        \Doctrine\ORM\EntityManagerInterface $em,
        \Symfony\Component\HttpFoundation\Request $request,
        CoursePlanActionController $planActions,
        CoursesCrudController $coursesCrud,
        \App\Repository\ScheduleRepository $scheduleRepository,
        \App\Service\ScheduleConflictService $conflictService,
        \App\Repository\ScheduleTemplateRepository $templateRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $course = $em->getRepository(\App\Entity\Courses::class)->find($id);
        if (!$course) {
            throw $this->createNotFoundException('Kurs nicht gefunden.');
        }

        // Kein Symfony forward(): Subrequests verlieren EasyAdmin-Requestdaten.
        // Den bestehenden Controller direkt im urspruenglichen Admin-Request
        // ausfuehren, damit ea und die URL-Parameter erhalten bleiben.
        $request->attributes->set('ea_workflow_embedded', true);
        $request->attributes->set('id', $id);

        return match ($step) {
            'import' => $planActions->import($id, $request),
            'generate' => $planActions->generate($id, $request),
            'plan' => $coursesCrud->planSchedules(
                $request, $scheduleRepository, $em, $conflictService, $templateRepository
            ),
            default => throw $this->createNotFoundException('Unbekannte Kursaktion.'),
        };
    }

    #[AdminRoute(path: '/training-overview', name: 'training_overview')]
    public function trainingOverview(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $courses = $em->getRepository(Courses::class)->findAll();
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
        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-house'),

            MenuItem::subMenu('Ausbildung & Kurse', 'fa fa-graduation-cap')
                ->setSubItems([
                MenuItem::linkToRoute('Ausbildungsdashboard', 'fa fa-chart-line', 'admin_training_overview'),
                MenuItem::linkTo(CoursesCrudController::class, 'Kurse verwalten', 'fa fa-book-open'),
                MenuItem::linkTo(CourseParticipantCrudController::class, 'Kursteilnehmer', 'fa fa-users'),
                MenuItem::linkTo(ScheduleCrudController::class, 'Kurstermine', 'fa fa-calendar-days')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(BookingCrudController::class, 'Terminbuchungen', 'fa fa-ticket')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(TrainingUnitResultCrudController::class, 'Ausbildungsnachweise', 'fa fa-clipboard-check')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkToRoute('Kurskalender', 'fa fa-calendar', 'admin_course_calendar'),
                MenuItem::linkTo(ScheduleTemplateCrudController::class, 'Planungsvorlagen', 'fa fa-layer-group')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(TrainingUnitTypeCrudController::class, 'Ausbildungseinheiten', 'fa fa-list-check')
                    ->setPermission('ROLE_SUPER_ADMIN'),
				MenuItem::linkToRoute('Ausbildungsaufgaben', 'fa fa-list-check', 'admin_instructor_tasks'),
            ]),

            MenuItem::subMenu('Qualifikationen', 'fa fa-award')->setSubItems([
                MenuItem::linkTo(QualificationCrudController::class, 'Qualifikationskatalog', 'fa fa-certificate')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(MemberQualificationCrudController::class, 'Mitgliedsqualifikationen', 'fa fa-id-card')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Vereinsverwaltung', 'fa fa-building')->setSubItems([
                MenuItem::linkTo(ClubCrudController::class, 'Vereine', 'fa fa-building')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(MemberCrudController::class, 'Mitglieder', 'fa fa-users'),
                MenuItem::linkTo(SportCrudController::class, 'Sportarten', 'fa fa-person-swimming')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(TeamCrudController::class, 'Gruppen & Mannschaften', 'fa fa-people-group')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(TeamMemberCrudController::class, 'Gruppenzuordnungen', 'fa fa-user-group')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(MemberSportCrudController::class, 'Sportzuordnungen', 'fa fa-link')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Ausrüstung & Verleih', 'fa fa-toolbox')->setSubItems([
                MenuItem::linkTo(EquipmentItemCrudController::class, 'Inventar', 'fa fa-boxes-stacked')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(RegulatorCrudController::class, 'Atemregler', 'fa fa-gauge')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentReservationCrudController::class, 'Einzelreservierungen', 'fa fa-hand-holding')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkTo(EquipmentBookingCrudController::class, 'Sammelreservierungen', 'fa fa-layer-group')
                    ->setPermission('ROLE_SUPER_ADMIN'),
				MenuItem::linkTo(TankCrudController::class, 'Vereinsflaschen', 'fa fa-gauge')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('TÜV-Verwaltung', 'fa fa-clipboard-check')->setSubItems([
                MenuItem::linkToRoute('Flaschenprüfungszentrale','fa fa-gauge-high','admin_equipment_center'),
                MenuItem::linkTo(MemberTankCheckBookingCrudController::class,'TÜV-Anmeldungen','fa fa-clipboard-check')
                    ->setAction(Action::INDEX)
                    ->setPermission('ROLE_SUPER_ADMIN'),

                MenuItem::linkTo(TankCheckCrudController::class,'TÜV-Termine','fa fa-calendar-check')
                    ->setPermission('ROLE_SUPER_ADMIN'),

                MenuItem::linkTo(TankCheckArticleCrudController::class,'TÜV-Preise','fa fa-tags')
                    ->setPermission('ROLE_SUPER_ADMIN'),

                MenuItem::linkTo(TuvNotificationCrudController::class,'TÜV-Nachrichten / Postausgang','fa fa-envelope-open-text')
                    ->setPermission('ROLE_SUPER_ADMIN'),
            ]),

            MenuItem::subMenu('Einstellungen', 'fa fa-sliders')->setSubItems([
				MenuItem::linkToRoute('Rechnungseinstellungen','fa fa-file-invoice','admin_invoice_settings_overview')
                    ->setPermission('ROLE_SUPER_ADMIN'),
                MenuItem::linkToRoute('TÜV-Benachrichtigungstexte', 'fa fa-envelope', 'admin_settings_tuv_notifications')
                    ->setPermission('ROLE_SUPER_ADMIN'),
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
            ]),

            MenuItem::subMenu('Partner', 'fa fa-handshake')->setSubItems([
                MenuItem::linkTo(VendorCrudController::class, 'Lieferanten', 'fa fa-truck'),
            ]),
			
			MenuItem::subMenu('Administration','fa fa-gear')->setSubItems([
					MenuItem::linkTo(UserCrudController::class, 'Benutzerverwaltung', 'fa fa-users-gear')
						->setPermission('ROLE_SUPER_ADMIN'),
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
