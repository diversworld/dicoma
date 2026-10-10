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
use App\Repository\MemberQualificationRepository;
use App\Controller\Admin\MemberQualificationCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Locale;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
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
        return Assets::new()->addCssFile('css/admin.css');
    }

    public function configureMenuItems(): iterable
    {
		return [
			MenuItem::linkToDashboard('Dashboard','fa fa-home'),

			//MenuItem::section('Verein'),
			MenuItem::subMenu('Verein', 'fa fa-building')->setSubItems([
				MenuItem::linkTo(ClubCrudController::class,'Vereine','fa fa-building'),
				MenuItem::linkTo(MemberCrudController::class,'Mitglieder','fa fa-users'),
				MenuItem::linkTo(SportCrudController::class,'Sportarten','fa fa-person-swimming'),


				MenuItem::linkTo(TeamCrudController::class,'Gruppen & Mannschaften','fa fa-people-group'),
				MenuItem::linkTo(TeamMemberCrudController::class,'Gruppenzuordnungen','fa fa-user-group'),
				MenuItem::linkTo(MemberSportCrudController::class,'Sportzuordnungen','fa fa-link'),
        	]),
			//MenuItem::section('Kursverwaltung'),	
			MenuItem::subMenu('Kursverwaltung', 'fa fa-school')->setSubItems([
				MenuItem::linkTo(CoursesCrudController::class, 'Kurse', 'fa fa-school'),
				MenuItem::linkTo(CourseParticipantCrudController::class,'Kursteilnehmer','fa fa-user-graduate'),
				MenuItem::linkTo(ScheduleCrudController::class, 'Kurstermine', 'fa fa-calendar-day'),
				MenuItem::linkTo(TrainingUnitTypeCrudController::class,'Ausbildungseinheiten','fa fa-list-check'),
				MenuItem::linkTo(ScheduleTemplateCrudController::class, 'Planungsvorlagen', 'fa fa-calendar-week'),
				MenuItem::linkToRoute('Kurskalender','fa fa-calendar','admin_course_calendar'),
				MenuItem::linkTo(TrainingUnitResultCrudController::class,'Ausbildungsnachweise','fa fa-clipboard-check'),
			]),
		
		//yield MenuItem::section('Ausbildung');
 
			MenuItem::subMenu('Ausbildung', 'fa fa-graduation-cap')->setSubItems([
				MenuItem::linkTo(QualificationCrudController::class,'Qualifikationskatalog','fa fa-certificate'),
				MenuItem::linkTo(MemberQualificationCrudController::class,'Mitgliedsqualifikationen','fa fa-id-card'),
			]),

			MenuItem::section('Equipment'),
			MenuItem::linkTo(TankCrudController::class, 'Flaschen', 'fa fa-user'),
			MenuItem::linkTo(TankCheckCrudController::class, 'Prüfungen', 'fa fa-user'),
			MenuItem::linkTo(TankCheckDetailCrudController::class, 'Prüfungsdetails', 'fa fa-user'),
			MenuItem::linkTo(TankCheckArticleCrudController::class, 'TÜV Preise', 'fa fa-user'),

			MenuItem::section('Partner'),
			MenuItem::linkTo(VendorCrudController::class, 'Lieferanten', 'fa fa-user'),
		];

    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        if (!$user instanceof User || null === $user->getId()) {
            throw new \LogicException('Das Benutzermenü benötigt einen gespeicherten Benutzer.');
        }

        // Usually it's better to call the parent method because that gives you a
        // user menu with some menu items already created ("sign out", "exit impersonation", etc.)
        // if you prefer to create the user menu from scratch, use: return UserMenu::new()->...
        return parent::configureUserMenu($user)
            // use the given $user object to get the username
            ->setName($user->getUserIdentifier())
            // use this method if you don't want to display the name of the user
            ->displayUserName(false)
            // use this method if you don't want to display the user image
            ->displayUserAvatar(false)
            // you can also pass an email address to use gravatar's service
            // you can use any type of menu item, except submenus
            ->addMenuItems([
//                MenuItem::linkToRoute('My Profile', 'fa fa-id-card', 'app_profile'),
                MenuItem::linkTo(UserCrudController::class, 'My Profile', 'fa fa-id-card')
                    ->setAction('detail')
                    ->setEntityId($user->getId()),
                MenuItem::linkTo(UserCrudController::class, 'Settings', 'fa fa-user-cog')
                    ->setAction('edit')
                    ->setEntityId($user->getId()),
            ]);
    }

}
