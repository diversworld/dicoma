<?php

declare(strict_types=1);

use App\Controller\Admin as Admin;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $groups = [
        'member' => ['/member', Admin\MemberCrudController::class, 'ROLE_SUPER_ADMIN'],
        'student' => ['/student', Admin\MemberCrudController::class, 'ROLE_SUPER_ADMIN'],
        'instructor' => ['/instructor', Admin\MemberCrudController::class, 'ROLE_SUPER_ADMIN'],
        'vendor' => ['/vendor', Admin\VendorCrudController::class, 'ROLE_SUPER_ADMIN'],
        'tank_check_article' => ['/tank/check/article', Admin\TankCheckArticleCrudController::class, 'ROLE_SUPER_ADMIN'],
        'tank_check_detail' => ['/tank/check/detail', Admin\TankCheckDetailCrudController::class, 'ROLE_ADMIN'],
        'brevets' => ['/brevets', Admin\BrevetsCrudController::class, 'ROLE_INSTRUCTOR'],
        'booking' => ['/booking', Admin\BookingCrudController::class, 'ROLE_ADMIN'],
        'courses' => ['/courses', Admin\CoursesCrudController::class, 'ROLE_ADMIN'],
        'schedule' => ['/schedule', Admin\ScheduleCrudController::class, 'ROLE_ADMIN'],
        'tank' => ['/tank', Admin\TankCrudController::class, 'ROLE_ADMIN'],
        'tank_check' => ['/tankcheck', Admin\TankCheckCrudController::class, 'ROLE_ADMIN'],
    ];
    foreach ($groups as $name => [$prefix, $crud, $permission]) {
        $actions = ['index', 'new', 'show', 'edit', 'delete'];
        if (in_array($name, ['booking', 'courses', 'schedule', 'tank', 'tank_check'], true)) {
            $actions = ['new', 'edit', 'delete'];
        }
        foreach ($actions as $action) {
            if ($name === 'member' && $action === 'delete') { continue; }
            $path = match ($action) {
                'index' => '/',
                'new' => $name === 'tank_check_article' ? '/new/{tank_check_id}' : '/new',
                'show' => $name === 'tank_check_detail' ? '/show/{id}' : '/{id}',
                'edit' => '/{id}/edit',
                'delete' => '/{id}',
            };
            if ($name === 'tank_check_detail' && $action === 'index') { $path = '/{id}'; }
            $canonicalPermission = in_array($name, ['member', 'student', 'instructor', 'courses'], true)
                ? 'ROLE_ADMIN' : 'ROLE_SUPER_ADMIN';
            $fallback = in_array($name, ['tank', 'tank_check', 'tank_check_detail'], true)
                ? 'equipment_manager_tuv' : 'admin_portal_training_portal';
            $defaults = Admin\DashboardController::ROUTE_DEFAULTS + [
                'legacy_crud' => $crud,
                'legacy_action' => match ($action) {
                    'show' => Action::DETAIL,
                    'delete' => Action::INDEX,
                    default => $action,
                },
                'legacy_permission' => $name === 'brevets' && !in_array($action, ['index', 'show'], true) ? 'ROLE_ADMIN' : $permission,
                'legacy_canonical_permission' => $canonicalPermission,
                'legacy_fallback' => $fallback,
            ];
            if ($name === 'tank_check_detail' && $action === 'index') { $defaults['id'] = 0; }
            $routes->add('app_' . $name . '_' . $action, $prefix . $path)
                ->controller(Admin\LegacyAdministrationController::class)
                ->methods($action === 'delete' ? ['POST'] : (in_array($action, ['new', 'edit'], true) ? ['GET', 'POST'] : ['GET']))
                ->requirements(['id' => '\d+', 'tank_check_id' => '\d+'])
                ->defaults($defaults);
        }
    }
    foreach ([
        'app_files_index' => ['/files/', ['GET', 'POST'], 'admin_courses_index'],
        'app_files_list' => ['/files/list', ['GET', 'POST'], 'admin_courses_index'],
        'app_files_choose' => ['/files/choose', ['POST'], 'admin_courses_index'],
        'app_file_upload' => ['/files/upload', ['POST'], 'admin_courses_index'],
        'app_settings' => ['/settings', ['GET', 'POST'], 'admin'],
    ] as $name => [$path, $methods, $target]) {
        $routes->add($name, $path)->controller(Admin\LegacyAdministrationController::class)->methods($methods)
            ->defaults(Admin\DashboardController::ROUTE_DEFAULTS + [
                'legacy_permission' => 'ROLE_ADMIN',
                'legacy_target' => $target,
            ]);
    }
};
