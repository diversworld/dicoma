<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Security\LoginDestination;
use Symfony\Bundle\SecurityBundle\Security;

final class PortalNavigation
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicWebsite $website,
        private readonly LoginDestination $loginDestination,
    ) {}

    /** @return list<array{label: string, route: string}> */
    public function getPublicLinks(): array
    {
        $links = [['label' => 'Startseite', 'route' => 'app_home']];
        if ($this->website->club()?->isActive() === true) {
            $links[] = ['label' => 'Unser Verein', 'route' => 'website_club'];
            $links[] = ['label' => 'Kontakt', 'route' => 'website_contact'];
        }

        $links = [
            ...$links,
            ['label' => 'Aktuelles', 'route' => 'website_articles'],
            ['label' => 'Veranstaltungen', 'route' => 'website_events'],
            ['label' => 'Kursangebot', 'route' => 'frontend_course_registration_index'],
        ];
        if (!$this->security->isGranted(User::ROLE_MEMBER)) {
            $links[] = ['label' => 'Mitglied werden', 'route' => 'membership_application_clubs'];
        }

        return $links;
    }

    public function getLoginRoute(): string
    {
        return $this->loginDestination->route();
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
            $personal[] = ['label' => 'Meine Veranstaltungsanmeldungen', 'route' => 'club_event_registrations'];
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
                ...($this->security->isGranted(User::ROLE_EQUIPMENT_CASH) ? [['label' => 'Kasse', 'route' => 'equipment_manager_cashbook']] : []),
                ['label' => 'Kompressorlogbuch', 'route' => 'equipment_manager_compressor_evenings'],
                ...($this->security->isGranted(User::ROLE_EQUIPMENT_CASH) ? [['label' => 'Füllgebühren', 'route' => 'equipment_manager_fill_billing']] : []),
            ];
        } elseif ($hasClub && $this->security->isGranted(User::ROLE_COMPRESSOR_OPERATOR)) {
            $roles['Kompressor'] = [
                ['label' => 'Kompressor – Füllabende', 'route' => 'equipment_manager_compressor_evenings'],
                ['label' => 'Kompressor-Fülllogbuch', 'route' => 'equipment_manager_compressor_log'],
            ];
        }

        return ['personal' => $personal, 'management' => $management, 'roles' => $roles];
    }
}
