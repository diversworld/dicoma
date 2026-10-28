<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

final class PortalNavigation
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicWebsite $website,
        private readonly CompetitionAccess $competitions,
    ) {}

    /** @return list<array{label: string, route: string}> */
    public function getPublicLinks(): array
    {
        $links = [['label' => 'Startseite', 'route' => 'app_home']];
        if ($this->website->club()?->isActive() === true) {
            $links[] = ['label' => 'Unser Verein', 'route' => 'website_club'];
            $links[] = ['label' => 'Kontakt', 'route' => 'website_contact'];
        }

        return [
            ...$links,
            ['label' => 'Aktuelles', 'route' => 'website_articles'],
            ['label' => 'Veranstaltungen', 'route' => 'website_events'],
            ['label' => 'Kursangebot', 'route' => 'frontend_course_registration_index'],
            ['label' => 'Mitglied werden', 'route' => 'membership_application_clubs'],
        ];
    }

    public function getLoginRoute(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return 'app_login';
        }
        $stored = $user->getStoredRoles();
        if (array_intersect($stored, [User::ROLE_INSTRUCTOR, User::ROLE_EQUIPMENT_MANAGER, User::ROLE_COMPRESSOR_OPERATOR]) === []) {
            if ($this->security->isGranted(User::ROLE_ADMIN)) {
                return 'admin';
            }
            if ($this->security->isGranted(User::ROLE_TRAINING_MANAGER)) {
                return 'admin_training_overview';
            }
            if ($this->security->isGranted(User::ROLE_COURSE_ADMIN)) {
                return 'admin_portal_training_portal';
            }
            if ($this->security->isGranted(User::ROLE_BAR_MANAGER)) {
                return 'admin_portal_bar_duty';
            }
            if ($this->website->canManageEditorial()) {
                return 'admin_website_editorial';
            }
            if ($this->competitions->isStaff()) {
                return 'competition_teams';
            }
        }

        return $this->security->isGranted(User::ROLE_MEMBER) ? 'member_portal_dashboard' : 'training_portal';
    }

    /** @return array{personal: list<array{label: string, route: string}>, management: list<array{label: string, route: string}>, roles: array<string, list<array{label: string, route: string}>>} */
    public function getLinks(): array
    {
        $personal = [];
        $management = [];
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return ['personal' => [], 'management' => [], 'roles' => []];
        }

        $member = $user->getMember();
        $hasClub = $member?->getClub()?->getId() !== null;
        $isMember = $this->security->isGranted(User::ROLE_MEMBER);
        $roles = [];

        if ($isMember) {
            $personal[] = ['label' => 'Mein Dashboard', 'route' => 'member_portal_dashboard'];
            $personal[] = ['label' => 'Meine Daten', 'route' => 'app_profile'];
            $personal[] = ['label' => 'Meine Mitgliedschaft', 'route' => 'membership_self'];
            $personal[] = ['label' => 'Meine Ausrüstung', 'route' => 'member_equipment_index'];
            $personal[] = ['label' => 'Meine Buchungen', 'route' => 'member_bookings'];
            $personal[] = ['label' => 'Ausrüstung reservieren', 'route' => 'member_reservations'];
        }
        if ($user->getPerson()?->getId() !== null) {
            $personal[] = ['label' => 'Meine Kurse', 'route' => 'training_portal'];
            $personal[] = ['label' => 'Mein Fortschritt', 'route' => 'app_my_training_progress'];
            $personal[] = ['label' => 'Meine Anwesenheit', 'route' => 'app_my_training_attendance'];
            $personal[] = ['label' => 'Meine Kursanmeldungen', 'route' => 'frontend_course_registrations'];
        }
        if ($hasClub && $isMember) {
            $roles['Thekendienst'] = [
                ['label' => 'Dienstkalender und mein Konto', 'route' => 'bar_duty'],
                ['label' => 'Thekenkasse', 'route' => 'bar_duty_cash'],
                ['label' => 'Gemeinsamer Kalender', 'route' => 'member_calendar'],
            ];
            $personal[] = ['label' => 'Vereinskalender', 'route' => 'member_calendar'];
        }
        if ($hasClub && $this->security->isGranted(User::ROLE_INSTRUCTOR)) {
            $roles['Ausbilder'] = [
                ['label' => 'Meine Kurse', 'route' => 'instructor_portal_courses'],
                ['label' => 'Ausbilder Dashboard', 'route' => 'instructor_portal_dashboard'],
                ['label' => 'Meine Aufgaben', 'route' => 'instructor_portal_tasks'],
            ];
        }
        if ($hasClub && $this->security->isGranted(User::ROLE_EQUIPMENT_MANAGER)) {
            $roles['Gerätewart'] = [
                ['label' => 'Übersicht', 'route' => 'equipment_manager_dashboard'],
                ['label' => 'TÜV Prüfung', 'route' => 'equipment_manager_tuv'],
                ['label' => 'Equipmentverleih (Ausleihe)', 'route' => 'equipment_manager_loans'],
                ['label' => 'Inventar und Wartung', 'route' => 'equipment_manager_inventory'],
                ['label' => 'Kasse', 'route' => 'equipment_manager_cashbook'],
                ['label' => 'Kompressorlogbuch', 'route' => 'equipment_manager_compressor_evenings'],
                ['label' => 'Füllgebühren', 'route' => 'equipment_manager_fill_billing'],
            ];
        }

        return ['personal' => $personal, 'management' => $management, 'roles' => $roles];
    }
}
