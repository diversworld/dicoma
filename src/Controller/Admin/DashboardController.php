<?php

namespace App\Controller\Admin;

use App\Entity\Booking;
use App\Entity\Brevets;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Schedule;
use App\Entity\Tank;
use App\Entity\TankCheck;
use App\Entity\TankCheckArticle;
use App\Entity\TankCheckDetail;
use App\Entity\User;
use App\Entity\Vendor;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin', name: 'admin')]
    public function index(): Response
    {
        //return parent::index();

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        //$adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        //return $this->redirect($adminUrlGenerator->setController(CoursesCrudController::class)->generateUrl());

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUser()) {
        //     return $this->redirect('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        return $this->render('admin/dashboard/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<h4>DiveCourseManager</h4>')
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
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Kursverwaltung');
        yield MenuItem::linkToCrud('Kurse', 'fa fa-school', Courses::class);
        yield MenuItem::linkToCrud('Kurstermine', 'fa fa-calendar-day', Schedule::class);
        yield MenuItem::linkToCrud('Buchungen', 'fa fa-calendar-check', Booking::class);
        yield MenuItem::linkToCrud('Brevets', 'fa fa-id-card', Brevets::class);

        yield MenuItem::section('Equipment');
        yield MenuItem::linkToCrud('Flaschen', 'fa fa-user', Tank::class);
        yield MenuItem::linkToCrud('Prüfungen', 'fa fa-user', TankCheck::class);
        yield MenuItem::linkToCrud('Prüfungsdetails', 'fa fa-user', TankCheckDetail::class);
        yield MenuItem::linkToCrud('TÜV Preise', 'fa fa-user', TankCheckArticle::class);

        yield MenuItem::section('Partner');
        yield MenuItem::linkToCrud('Lieferanten', 'fa fa-user', Vendor::class);

        yield MenuItem::section('Benutzer');
        yield MenuItem::linkToCrud('Benutzer', 'fa fa-user', User::class);
        yield MenuItem::linkToCrud('Mitglieder', 'fa fa-user', Member::class);

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
