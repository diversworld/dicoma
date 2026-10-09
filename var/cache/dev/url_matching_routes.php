<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/_profiler' => [[['_route' => '_profiler_home', '_controller' => 'web_profiler.controller.profiler::homeAction'], null, null, null, true, false, null]],
        '/_profiler/search' => [[['_route' => '_profiler_search', '_controller' => 'web_profiler.controller.profiler::searchAction'], null, null, null, false, false, null]],
        '/_profiler/search_bar' => [[['_route' => '_profiler_search_bar', '_controller' => 'web_profiler.controller.profiler::searchBarAction'], null, null, null, false, false, null]],
        '/_profiler/phpinfo' => [[['_route' => '_profiler_phpinfo', '_controller' => 'web_profiler.controller.profiler::phpinfoAction'], null, null, null, false, false, null]],
        '/_profiler/xdebug' => [[['_route' => '_profiler_xdebug', '_controller' => 'web_profiler.controller.profiler::xdebugAction'], null, null, null, false, false, null]],
        '/_profiler/open' => [[['_route' => '_profiler_open_file', '_controller' => 'web_profiler.controller.profiler::openAction'], null, null, null, false, false, null]],
        '/admin' => [[['_route' => 'admin', '_controller' => 'App\\Controller\\Admin\\DashboardController::index'], null, null, null, false, false, null]],
        '/booking' => [[['_route' => 'app_booking_index', '_controller' => 'App\\Controller\\BookingController::index'], null, ['GET' => 0], null, true, false, null]],
        '/booking/new' => [[['_route' => 'app_booking_new', '_controller' => 'App\\Controller\\BookingController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/brevets' => [[['_route' => 'app_brevets_index', '_controller' => 'App\\Controller\\BrevetsController::index'], null, ['GET' => 0], null, true, false, null]],
        '/brevets/new' => [[['_route' => 'app_brevets_new', '_controller' => 'App\\Controller\\BrevetsController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/courses' => [[['_route' => 'app_courses_index', '_controller' => 'App\\Controller\\CoursesController::index'], null, ['GET' => 0], null, true, false, null]],
        '/courses/new' => [[['_route' => 'app_courses_new', '_controller' => 'App\\Controller\\CoursesController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/courses/upload' => [[['_route' => 'upload', '_controller' => 'App\\Controller\\CoursesController::upload'], null, ['POST' => 0], null, false, false, null]],
        '/courses/image/delete' => [[['_route' => 'delete_image', '_controller' => 'App\\Controller\\CoursesController::delete_image'], null, ['DELETE' => 0], null, false, false, null]],
        '/files' => [[['_route' => 'app_files_index', '_controller' => 'App\\Controller\\FileController::index'], null, null, null, true, false, null]],
        '/files/list' => [[['_route' => 'app_files_list', '_controller' => 'App\\Controller\\FileController::listFiles'], null, null, null, false, false, null]],
        '/files/choose' => [[['_route' => 'app_files_choose', '_controller' => 'App\\Controller\\FileController::chooseFile'], null, ['POST' => 0], null, false, false, null]],
        '/files/upload' => [[['_route' => 'app_file_upload', '_controller' => 'App\\Controller\\FileController::uploadFile'], null, ['POST' => 0], null, false, false, null]],
        '/' => [[['_route' => 'app_home', '_controller' => 'App\\Controller\\HomeController::index'], null, null, null, false, false, null]],
        '/install' => [[['_route' => 'app_install', '_controller' => 'App\\Controller\\InstallController::install'], null, ['GET' => 0], null, false, false, null]],
        '/save_db_config' => [[['_route' => 'save_db_config', '_controller' => 'App\\Controller\\InstallController::saveDbConfig'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/instructor' => [[['_route' => 'app_instructor_index', '_controller' => 'App\\Controller\\InstructorController::index'], null, ['GET' => 0], null, true, false, null]],
        '/instructor/new' => [[['_route' => 'app_instructor_new', '_controller' => 'App\\Controller\\InstructorController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/member' => [[['_route' => 'app_member_index', '_controller' => 'App\\Controller\\MemberController::index'], null, ['GET' => 0], null, true, false, null]],
        '/member/new' => [[['_route' => 'app_member_new', '_controller' => 'App\\Controller\\MemberController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/profile' => [[['_route' => 'app_profile', '_controller' => 'App\\Controller\\ProfileController::index'], null, null, null, false, false, null]],
        '/register' => [[['_route' => 'app_register', '_controller' => 'App\\Controller\\RegistrationController::register'], null, null, null, false, false, null]],
        '/verify/email' => [[['_route' => 'app_verify_email', '_controller' => 'App\\Controller\\RegistrationController::verifyUserEmail'], null, null, null, false, false, null]],
        '/reset-password' => [[['_route' => 'app_forgot_password_request', '_controller' => 'App\\Controller\\ResetPasswordController::request'], null, null, null, false, false, null]],
        '/reset-password/check-email' => [[['_route' => 'app_check_email', '_controller' => 'App\\Controller\\ResetPasswordController::checkEmail'], null, null, null, false, false, null]],
        '/schedule' => [[['_route' => 'app_schedule_index', '_controller' => 'App\\Controller\\ScheduleController::index'], null, ['GET' => 0], null, true, false, null]],
        '/schedule/new' => [[['_route' => 'app_schedule_new', '_controller' => 'App\\Controller\\ScheduleController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/login' => [[['_route' => 'app_login', '_controller' => 'App\\Controller\\SecurityController::login'], null, null, null, false, false, null]],
        '/logout' => [[['_route' => 'app_logout', '_controller' => 'App\\Controller\\SecurityController::logout'], null, null, null, false, false, null]],
        '/settings' => [[['_route' => 'app_settings', '_controller' => 'App\\Controller\\SettingsController::index'], null, null, null, false, false, null]],
        '/student' => [[['_route' => 'app_student_index', '_controller' => 'App\\Controller\\StudentController::index'], null, ['GET' => 0], null, true, false, null]],
        '/student/new' => [[['_route' => 'app_student_new', '_controller' => 'App\\Controller\\StudentController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/tank/check/article' => [[['_route' => 'app_tank_check_article_index', '_controller' => 'App\\Controller\\TankCheckArticleController::index'], null, ['GET' => 0], null, true, false, null]],
        '/tankcheck' => [[['_route' => 'app_tank_check_index', '_controller' => 'App\\Controller\\TankCheckController::index'], null, ['GET' => 0], null, true, false, null]],
        '/tankcheck/new' => [[['_route' => 'app_tank_check_new', '_controller' => 'App\\Controller\\TankCheckController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/tank' => [[['_route' => 'app_tank_index', '_controller' => 'App\\Controller\\TankController::index'], null, ['GET' => 0], null, true, false, null]],
        '/tank/new' => [[['_route' => 'app_tank_new', '_controller' => 'App\\Controller\\TankController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
        '/vendor' => [[['_route' => 'app_vendor_index', '_controller' => 'App\\Controller\\VendorController::index'], null, ['GET' => 0], null, true, false, null]],
        '/vendor/new' => [[['_route' => 'app_vendor_new', '_controller' => 'App\\Controller\\VendorController::new'], null, ['GET' => 0, 'POST' => 1], null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/_(?'
                    .'|error/(\\d+)(?:\\.([^/]++))?(*:38)'
                    .'|wdt/([^/]++)(*:57)'
                    .'|profiler/(?'
                        .'|font/([^/\\.]++)\\.woff2(*:98)'
                        .'|([^/]++)(?'
                            .'|/(?'
                                .'|search/results(*:134)'
                                .'|router(*:148)'
                                .'|exception(?'
                                    .'|(*:168)'
                                    .'|\\.css(*:181)'
                                .')'
                            .')'
                            .'|(*:191)'
                        .')'
                    .')'
                .')'
                .'|/b(?'
                    .'|ooking/(?'
                        .'|book/([^/]++)/([^/]++)(*:239)'
                        .'|([^/]++)(?'
                            .'|(*:258)'
                            .'|/edit(*:271)'
                            .'|(*:279)'
                        .')'
                    .')'
                    .'|revets/([^/]++)(?'
                        .'|(*:307)'
                        .'|/edit(*:320)'
                        .'|(*:328)'
                    .')'
                .')'
                .'|/courses/(?'
                    .'|(\\d+)(*:355)'
                    .'|([^/]++)(?'
                        .'|/edit(*:379)'
                        .'|(*:387)'
                    .')'
                .')'
                .'|/instructor/([^/]++)(?'
                    .'|(*:420)'
                    .'|/edit(*:433)'
                    .'|(*:441)'
                .')'
                .'|/member/([^/]++)(?'
                    .'|(*:469)'
                    .'|/edit(*:482)'
                    .'|(*:490)'
                .')'
                .'|/re(?'
                    .'|gister/install/([^/]++)(*:528)'
                    .'|set\\-password/reset(?:/([^/]++))?(*:569)'
                .')'
                .'|/s(?'
                    .'|chedule/(?'
                        .'|calendar(?:/([^/]++))?(*:616)'
                        .'|([^/]++)(?'
                            .'|(*:635)'
                            .'|/edit(*:648)'
                            .'|(*:656)'
                        .')'
                    .')'
                    .'|tudent/([^/]++)(?'
                        .'|(*:684)'
                        .'|/edit(*:697)'
                        .'|(*:705)'
                    .')'
                .')'
                .'|/tank(?'
                    .'|/(?'
                        .'|check/(?'
                            .'|article/(?'
                                .'|new/([^/]++)(*:759)'
                                .'|([^/]++)(?'
                                    .'|(*:778)'
                                    .'|/edit(*:791)'
                                    .'|(*:799)'
                                .')'
                            .')'
                            .'|detail(?'
                                .'|(?:/([^/]++))?(*:832)'
                                .'|/(?'
                                    .'|new(*:847)'
                                    .'|([^/]++)(?'
                                        .'|(*:866)'
                                        .'|/edit(*:879)'
                                        .'|(*:887)'
                                    .')'
                                .')'
                            .')'
                        .')'
                        .'|([^/]++)(?'
                            .'|(*:910)'
                            .'|/edit(*:923)'
                            .'|(*:931)'
                        .')'
                    .')'
                    .'|check/([^/]++)(?'
                        .'|(*:958)'
                        .'|/edit(*:971)'
                        .'|(*:979)'
                    .')'
                .')'
                .'|/vendor/([^/]++)(?'
                    .'|(*:1008)'
                    .'|/edit(*:1022)'
                    .'|(*:1031)'
                .')'
            .')/?$}sDu',
    ],
    [ // $dynamicRoutes
        38 => [[['_route' => '_preview_error', '_controller' => 'error_controller::preview', '_format' => 'html'], ['code', '_format'], null, null, false, true, null]],
        57 => [[['_route' => '_wdt', '_controller' => 'web_profiler.controller.profiler::toolbarAction'], ['token'], null, null, false, true, null]],
        98 => [[['_route' => '_profiler_font', '_controller' => 'web_profiler.controller.profiler::fontAction'], ['fontName'], null, null, false, false, null]],
        134 => [[['_route' => '_profiler_search_results', '_controller' => 'web_profiler.controller.profiler::searchResultsAction'], ['token'], null, null, false, false, null]],
        148 => [[['_route' => '_profiler_router', '_controller' => 'web_profiler.controller.router::panelAction'], ['token'], null, null, false, false, null]],
        168 => [[['_route' => '_profiler_exception', '_controller' => 'web_profiler.controller.exception_panel::body'], ['token'], null, null, false, false, null]],
        181 => [[['_route' => '_profiler_exception_css', '_controller' => 'web_profiler.controller.exception_panel::stylesheet'], ['token'], null, null, false, false, null]],
        191 => [[['_route' => '_profiler', '_controller' => 'web_profiler.controller.profiler::panelAction'], ['token'], null, null, false, true, null]],
        239 => [[['_route' => 'app_booking_book', '_controller' => 'App\\Controller\\BookingController::book_course'], ['id', 'user'], null, null, false, true, null]],
        258 => [[['_route' => 'app_booking_show', '_controller' => 'App\\Controller\\BookingController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        271 => [[['_route' => 'app_booking_edit', '_controller' => 'App\\Controller\\BookingController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        279 => [[['_route' => 'app_booking_delete', '_controller' => 'App\\Controller\\BookingController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        307 => [[['_route' => 'app_brevets_show', '_controller' => 'App\\Controller\\BrevetsController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        320 => [[['_route' => 'app_brevets_edit', '_controller' => 'App\\Controller\\BrevetsController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        328 => [[['_route' => 'app_brevets_delete', '_controller' => 'App\\Controller\\BrevetsController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        355 => [[['_route' => 'app_courses_show', '_controller' => 'App\\Controller\\CoursesController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        379 => [[['_route' => 'app_courses_edit', '_controller' => 'App\\Controller\\CoursesController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        387 => [[['_route' => 'app_courses_delete', '_controller' => 'App\\Controller\\CoursesController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        420 => [[['_route' => 'app_instructor_show', '_controller' => 'App\\Controller\\InstructorController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        433 => [[['_route' => 'app_instructor_edit', '_controller' => 'App\\Controller\\InstructorController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        441 => [[['_route' => 'app_instructor_delete', '_controller' => 'App\\Controller\\InstructorController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        469 => [[['_route' => 'app_member_show', '_controller' => 'App\\Controller\\MemberController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        482 => [[['_route' => 'app_member_edit', '_controller' => 'App\\Controller\\MemberController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        490 => [[['_route' => 'app_member_delete', '_controller' => 'App\\Controller\\MemberController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        528 => [[['_route' => 'app_register_install', '_controller' => 'App\\Controller\\RegistrationController::install'], ['adminUser'], null, null, false, true, null]],
        569 => [[['_route' => 'app_reset_password', 'token' => null, '_controller' => 'App\\Controller\\ResetPasswordController::reset'], ['token'], null, null, false, true, null]],
        616 => [[['_route' => 'app_schedule_calendar', 'month' => null, '_controller' => 'App\\Controller\\ScheduleController::calendar'], ['month'], ['GET' => 0], null, false, true, null]],
        635 => [[['_route' => 'app_schedule_show', '_controller' => 'App\\Controller\\ScheduleController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        648 => [[['_route' => 'app_schedule_edit', '_controller' => 'App\\Controller\\ScheduleController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        656 => [[['_route' => 'app_schedule_delete', '_controller' => 'App\\Controller\\ScheduleController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        684 => [[['_route' => 'app_student_show', '_controller' => 'App\\Controller\\StudentController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        697 => [[['_route' => 'app_student_edit', '_controller' => 'App\\Controller\\StudentController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        705 => [[['_route' => 'app_student_delete', '_controller' => 'App\\Controller\\StudentController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        759 => [[['_route' => 'app_tank_check_article_new', '_controller' => 'App\\Controller\\TankCheckArticleController::new'], ['tank_check_id'], ['GET' => 0, 'POST' => 1], null, false, true, null]],
        778 => [[['_route' => 'app_tank_check_article_show', '_controller' => 'App\\Controller\\TankCheckArticleController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        791 => [[['_route' => 'app_tank_check_article_edit', '_controller' => 'App\\Controller\\TankCheckArticleController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        799 => [[['_route' => 'app_tank_check_article_delete', '_controller' => 'App\\Controller\\TankCheckArticleController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        832 => [[['_route' => 'app_tank_check_detail_index', 'id' => 0, '_controller' => 'App\\Controller\\TankCheckDetailController::index'], ['id'], ['GET' => 0], null, false, true, null]],
        847 => [[['_route' => 'app_tank_check_detail_new', '_controller' => 'App\\Controller\\TankCheckDetailController::new'], [], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        866 => [[['_route' => 'app_tank_check_detail_show', '_controller' => 'App\\Controller\\TankCheckDetailController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        879 => [[['_route' => 'app_tank_check_detail_edit', '_controller' => 'App\\Controller\\TankCheckDetailController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        887 => [[['_route' => 'app_tank_check_detail_delete', '_controller' => 'App\\Controller\\TankCheckDetailController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        910 => [[['_route' => 'app_tank_show', '_controller' => 'App\\Controller\\TankController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        923 => [[['_route' => 'app_tank_edit', '_controller' => 'App\\Controller\\TankController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        931 => [[['_route' => 'app_tank_delete', '_controller' => 'App\\Controller\\TankController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        958 => [[['_route' => 'app_tank_check_show', '_controller' => 'App\\Controller\\TankCheckController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        971 => [[['_route' => 'app_tank_check_edit', '_controller' => 'App\\Controller\\TankCheckController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        979 => [[['_route' => 'app_tank_check_delete', '_controller' => 'App\\Controller\\TankCheckController::delete'], ['id'], ['POST' => 0], null, false, true, null]],
        1008 => [[['_route' => 'app_vendor_show', '_controller' => 'App\\Controller\\VendorController::show'], ['id'], ['GET' => 0], null, false, true, null]],
        1022 => [[['_route' => 'app_vendor_edit', '_controller' => 'App\\Controller\\VendorController::edit'], ['id'], ['GET' => 0, 'POST' => 1], null, false, false, null]],
        1031 => [
            [['_route' => 'app_vendor_delete', '_controller' => 'App\\Controller\\VendorController::delete'], ['id'], ['POST' => 0], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
