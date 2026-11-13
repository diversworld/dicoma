<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\Admin\DashboardController;
use App\Http\EasyAdminRequest;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AreaRouteBoundaryTest extends KernelTestCase
{
    public function testPortalAndAdminRoutePairsKeepTheirPublicContracts(): void
    {
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        self::bootKernel(['environment' => 'test']);
        $routes = self::getContainer()->get(RouterInterface::class)->getRouteCollection();

        $portalRoutes = [
            'training_portal', 'training_course_new', 'training_course_edit', 'training_course',
            'training_course_unit', 'training_unit_type_new', 'training_course_session',
            'training_participant', 'training_cancel', 'training_payment',
            'training_complete_participant', 'training_complete_course', 'training_certificate',
            'training_assess', 'bar_duty', 'bar_duty_cash', 'bar_duty_plan', 'bar_duty_settings',
            'bar_duty_report', 'bar_duty_report_csv', 'bar_duty_shift', 'bar_duty_cash_csv',
            'bar_duty_action',
        ];
        $pairs = [
            'member_calendar' => 'club_calendar',
            'account_profile' => 'admin_account_profile',
            'account_password' => 'admin_account_password',
        ];
        foreach ($portalRoutes as $route) {
            $pairs[$route] = 'admin_portal_'.$route;
        }

        foreach ($pairs as $portalName => $adminName) {
            $portal = $routes->get($portalName);
            $admin = $routes->get($adminName);
            self::assertNotNull($portal, $portalName);
            self::assertNotNull($admin, $adminName);
            self::assertSame('/admin'.$portal->getPath(), $admin->getPath(), $adminName);
            self::assertSame($portal->getMethods(), $admin->getMethods(), $adminName);
            self::assertSame($portal->getRequirements(), $admin->getRequirements(), $adminName);
            self::assertFalse(EasyAdminRequest::matches(new Request(attributes: $portal->getDefaults())), $portalName);
            self::assertTrue(EasyAdminRequest::matches(new Request(attributes: $admin->getDefaults())), $adminName);
            self::assertSame(DashboardController::class, $admin->getDefault('dashboardControllerFqcn'), $adminName);
        }
    }
}
