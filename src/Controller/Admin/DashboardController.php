<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
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
use App\Repository\MemberQualificationRepository;
use App\Controller\Admin\MemberQualificationCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AbstractDashboardController
{
	public function __construct(
		private readonly MemberQualificationRepository $memberQualificationRepository, 
		private readonly AdminUrlGenerator $adminUrlGenerator
	) {
	}
	
   	#[Route('/admin', name: 'admin')]
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
            ->setLocales(['de', 'en'])
            // to customize the labels of locales, pass a key => value array
            // (e.g. to display flags; although it's not a recommended practice,
            // because many languages/locales are not associated to a single country)
            ->setLocales([
                'de' => '🇩🇪 Deutsch',
                'en' => '🇬🇧 English',
            ])
            ;
    }

    public function configureMenuItems(): iterable
    {
		yield MenuItem::linkToDashboard('Dashboard','fa fa-home');

		yield MenuItem::section('Verein');
		yield MenuItem::linkToCrud('Vereine','fa fa-building',Club::class);
		yield MenuItem::linkToCrud('Mitglieder','fa fa-users',Member::class);
		yield MenuItem::linkToCrud('Sportarten','fa fa-person-swimming',Sport::class);

		yield MenuItem::linkToCrud('Gruppen & Mannschaften','fa fa-people-group',Team::class);
		yield MenuItem::linkToCrud('Gruppenzuordnungen','fa fa-user-group',TeamMember::class);
		yield MenuItem::linkToCrud('Sportzuordnungen','fa fa-link',MemberSport::class);
        
        yield MenuItem::section('Kursverwaltung');
        yield MenuItem::linkToCrud('Kurse', 'fa fa-school', Courses::class);
		yield MenuItem::linkToCrud('Kursteilnehmer','fa fa-user-graduate',CourseParticipant::class);
        yield MenuItem::linkToCrud('Kurstermine', 'fa fa-calendar-day', Schedule::class);
		yield MenuItem::linkToCrud('Ausbildungseinheiten','fa fa-list-check',TrainingUnitType::class);
        yield MenuItem::linkToCrud('Planungsvorlagen', 'fa fa-calendar-week', ScheduleTemplate::class);
        yield MenuItem::linkToRoute('Kurskalender','fa fa-calendar','admin_course_calendar');
		yield MenuItem::linkToCrud('Ausbildungsnachweise','fa fa-clipboard-check',TrainingUnitResult::class);
		
		yield MenuItem::section('Ausbildung');
		yield MenuItem::linkToCrud('Qualifikationskatalog','fa fa-certificate',Qualification::class);
		yield MenuItem::linkToCrud('Mitgliedsqualifikationen','fa fa-id-card',MemberQualification::class);

        yield MenuItem::section('Equipment');
        yield MenuItem::linkToCrud('Flaschen', 'fa fa-user', Tank::class);
        yield MenuItem::linkToCrud('Prüfungen', 'fa fa-user', TankCheck::class);
        yield MenuItem::linkToCrud('Prüfungsdetails', 'fa fa-user', TankCheckDetail::class);
        yield MenuItem::linkToCrud('TÜV Preise', 'fa fa-user', TankCheckArticle::class);

        yield MenuItem::section('Partner');
        yield MenuItem::linkToCrud('Lieferanten', 'fa fa-user', Vendor::class);

    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
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
                MenuItem::linkToCrud('My Profile', 'fa fa-id-card', User::class)
                    ->setController(UserCrudController::class)
                    ->setAction('detail')
                    ->setQueryParameter('entityId', $user->getId()),
                MenuItem::linkToCrud('Settings', 'fa fa-user-cog', User::class)
                    ->setController(UserCrudController::class)
                    ->setAction('edit')
                    ->setEntityId($user->getId()),
            ]);
    }

}
