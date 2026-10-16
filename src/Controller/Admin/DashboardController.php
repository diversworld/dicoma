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
use App\Controller\Admin\EquipmentReservationCrudController;
use App\Controller\Admin\MemberQualificationCrudController;
use App\Controller\Admin\EquipmentBookingCrudController;
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
use App\Service\TankInspectionOverview;

use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\IconSet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;


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

    public function configureMenuItems(): iterable
    {
        return [
            MenuItem::linkToDashboard('Dashboard', 'fa fa-house'),

            MenuItem::subMenu('Ausbildung & Kurse', 'fa fa-graduation-cap')->setSubItems([
                MenuItem::linkTo(CoursesCrudController::class, 'Kurse verwalten', 'fa fa-book-open'),
                MenuItem::linkTo(CourseParticipantCrudController::class, 'Kursteilnehmer', 'fa fa-users'),
                MenuItem::linkTo(ScheduleCrudController::class, 'Kurstermine', 'fa fa-calendar-days'),
                MenuItem::linkTo(BookingCrudController::class, 'Terminbuchungen', 'fa fa-ticket')->setPermission('ROLE_ADMIN'),
                MenuItem::linkTo(TrainingUnitResultCrudController::class, 'Ausbildungsnachweise', 'fa fa-clipboard-check'),
                MenuItem::linkToRoute('Kurskalender', 'fa fa-calendar', 'admin_course_calendar'),
                MenuItem::linkTo(ScheduleTemplateCrudController::class, 'Planungsvorlagen', 'fa fa-layer-group'),
                MenuItem::linkTo(TrainingUnitTypeCrudController::class, 'Ausbildungseinheiten', 'fa fa-list-check'),
				MenuItem::linkToRoute('Ausbildungsaufgaben', 'fa fa-list-check', 'admin_instructor_tasks'),
            ]),

            MenuItem::subMenu('Qualifikationen', 'fa fa-award')->setSubItems([
                MenuItem::linkTo(QualificationCrudController::class, 'Qualifikationskatalog', 'fa fa-certificate'),
                MenuItem::linkTo(MemberQualificationCrudController::class, 'Mitgliedsqualifikationen', 'fa fa-id-card'),
            ]),

            MenuItem::subMenu('Vereinsverwaltung', 'fa fa-building')->setSubItems([
                MenuItem::linkTo(ClubCrudController::class, 'Vereine', 'fa fa-building'),
                MenuItem::linkTo(MemberCrudController::class, 'Mitglieder', 'fa fa-users'),
                MenuItem::linkTo(SportCrudController::class, 'Sportarten', 'fa fa-person-swimming'),
                MenuItem::linkTo(TeamCrudController::class, 'Gruppen & Mannschaften', 'fa fa-people-group'),
                MenuItem::linkTo(TeamMemberCrudController::class, 'Gruppenzuordnungen', 'fa fa-user-group'),
                MenuItem::linkTo(MemberSportCrudController::class, 'Sportzuordnungen', 'fa fa-link'),
            ]),

            MenuItem::subMenu('Ausrüstung & Prüfungen', 'fa fa-toolbox')->setSubItems([
                MenuItem::linkTo(EquipmentItemCrudController::class, 'Ausrüstungsinventar', 'fa fa-boxes-stacked'),
                MenuItem::linkTo(RegulatorCrudController::class, 'Atemregler', 'fa fa-gauge'),
				MenuItem::linkTo(EquipmentReservationCrudController::class,'Ausrüstungsverleih','fa fa-hand-holding'),
				MenuItem::linkTo(EquipmentBookingCrudController::class,'Sammelreservierungen','fa fa-layer-group'),
                MenuItem::linkToRoute('Flaschenprüfungszentrale', 'fa fa-gauge-high', 'admin_equipment_center'),
                MenuItem::linkTo(TankCrudController::class, 'Flaschen', 'fa fa-gauge'),
                MenuItem::linkTo(TankCheckCrudController::class, 'Prüfungen', 'fa fa-clipboard-check'),
                MenuItem::linkTo(TankCheckDetailCrudController::class, 'Prüfungsdetails', 'fa fa-list'),
                MenuItem::linkTo(TankCheckArticleCrudController::class, 'TÜV-Preise', 'fa fa-tags'),
            ]),

            MenuItem::subMenu('Einstellungen', 'fa fa-sliders')->setSubItems([
                MenuItem::linkTo(EquipmentCategoryCrudController::class, 'Ausrüstungsarten', 'fa fa-list'),
                MenuItem::linkTo(EquipmentSubTypeCrudController::class, 'Ausrüstungsunterarten', 'fa fa-sitemap'),
                MenuItem::linkTo(EquipmentManufacturerCrudController::class, 'Hersteller je Verein', 'fa fa-industry'),
                MenuItem::linkTo(EquipmentSizeCrudController::class, 'Größen je Hersteller', 'fa fa-ruler'),
                MenuItem::linkTo(EquipmentModelCrudController::class, 'Ausrüstungsmodelle', 'fa fa-tags'),
                MenuItem::linkTo(RegulatorModelCrudController::class, 'Atemreglermodelle', 'fa fa-gears'),
                MenuItem::linkTo(EquipmentAttributeCrudController::class, 'Kategorie-Merkmale', 'fa fa-sliders'),
            ]),

            MenuItem::subMenu('Partner', 'fa fa-handshake')->setSubItems([
                MenuItem::linkTo(VendorCrudController::class, 'Lieferanten', 'fa fa-truck'),
            ]),
			
			MenuItem::subMenu('Administration','fa fa-gear')->setSubItems([
					MenuItem::linkTo(UserCrudController::class, 'Benutzerverwaltung', 'fa fa-users-gear')
						->setPermission('ROLE_ADMIN'),
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
