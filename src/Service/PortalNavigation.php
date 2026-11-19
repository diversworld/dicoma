<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Security\LoginDestination;
use App\Security\EditorialAccess;
use App\Enum\PermissionAction;
use Symfony\Bundle\SecurityBundle\Security;

final class PortalNavigation
{
    public function __construct(
        private readonly Security $security,
        private readonly PublicWebsite $website,
        private readonly LoginDestination $loginDestination,
        private readonly EditorialAccess $editorialAccess,
    ) {}

    /**
     * @return array{home: array{label: string, route: string}, groups: array<string, list<array{label: string, route: string}>>, pages: list<array{label: string, route: string, parameters: array{id: int}}>}
     */
    public function getPublicMenu(): array
    {
        $clubLinks = [];
        if ($this->website->publicClub() !== null) {
            $clubLinks[] = ['label' => 'Unser Verein', 'route' => 'website_club'];
            $clubLinks[] = ['label' => 'Kontakt', 'route' => 'website_contact'];
        }
        if (!$this->security->isGranted(User::ROLE_MEMBER)) {
            $clubLinks[] = ['label' => 'Mitglied werden', 'route' => 'membership_application_clubs'];
        }
        $activityLinks = [
            ['label' => 'Aktuelles', 'route' => 'website_articles'],
            ['label' => 'Veranstaltungen', 'route' => 'website_events'],
            ['label' => 'Kursangebot', 'route' => 'app_courses_index'],
            ['label' => 'Sparten', 'route' => 'website_divisions'],
        ];
        $pages = [];
        $allPages = $this->website->publications('page');
        foreach ($allPages as $page) {
            if ($page->getParent() !== null || !$page->isMenuVisible()) { continue; }
            $pages[] = [
                'label' => $page->getTitle(),
                'route' => 'website_page',
                'parameters' => ['id' => $page->getId()],
                'children' => array_values(array_map(
                    static fn (\App\Entity\WebsitePublication $child): array => [
                        'label' => $child->getTitle(), 'route' => 'website_page',
                        'parameters' => ['id' => $child->getId()],
                    ],
                    array_filter($allPages, static fn (\App\Entity\WebsitePublication $candidate): bool =>
                        $candidate->getParent()?->getId() === $page->getId() && $candidate->isMenuVisible()
                    ),
                )),
            ];
        }

        return [
            'home' => ['label' => 'Startseite', 'route' => 'app_home'],
            'groups' => array_filter(['Verein' => $clubLinks, 'Angebote' => $activityLinks]),
            'pages' => $pages,
        ];
    }

    /** @return list<array{label: string, route: string}> */
    public function getPublicLinks(): array
    {
        $menu = $this->getPublicMenu();
        $links = [$menu['home']];
        foreach ($menu['groups'] as $group) {
            array_push($links, ...$group);
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

        if ($this->editorialAccess->functionModelEnabled()) {
            if ($this->editorialAccess->can(PermissionAction::READ)) {
                $roles['Website-Redaktion'] = [['label' => 'Seiten, Artikel und Veranstaltungen', 'route' => 'admin_website_editorial']];
                $roles['Website-Redaktion'][] = ['label' => 'Veranstaltungsbuchungen', 'route' => 'admin_club_events'];
            } elseif ($this->editorialAccess->can(PermissionAction::CREATE)) {
                $roles['Website-Redaktion'] = [['label' => 'Seiten, Artikel und Veranstaltungen erstellen', 'route' => 'admin_website_editorial_new']];
                $roles['Website-Redaktion'][] = ['label' => 'Veranstaltung anlegen', 'route' => 'admin_club_event_new'];
            }
            if ($this->editorialAccess->canViewSettings()) {
                $roles['Website-Redaktion'][] = ['label' => 'Website-Anzeige', 'route' => 'admin_settings_website'];
            }
        }
        if ($isMember) {
            $personal[] = ['label' => 'Mein Dashboard', 'route' => 'member_portal_dashboard'];
            $personal[] = ['label' => 'Mein Kalender', 'route' => 'member_personal_calendar'];
            $personal[] = ['label' => 'Interne Nachrichten', 'route' => 'member_messages'];
            $personal[] = ['label' => 'Meine Daten', 'route' => 'app_profile'];
            $personal[] = ['label' => 'Meine Mitgliedschaft', 'route' => 'membership_self'];
            $personal[] = ['label' => 'Vereinsdokumente', 'route' => 'member_club_documents'];
            $personal[] = ['label' => 'Meine Ausrüstung', 'route' => 'member_equipment_index'];
            $personal[] = ['label' => 'Meine Buchungen', 'route' => 'member_bookings'];
            $personal[] = ['label' => 'Ausrüstung reservieren', 'route' => 'member_reservations'];
        }
        if ($user->getPerson()?->getId() !== null) {
            $personal[] = ['label' => 'Ausbildungsportal', 'route' => 'training_portal'];
            $personal[] = ['label' => 'Mein Fortschritt', 'route' => 'app_my_training_progress'];
            $personal[] = ['label' => 'Meine Anwesenheit', 'route' => 'app_my_training_attendance'];
            $personal[] = ['label' => 'Meine Kurse', 'route' => 'frontend_course_registration_index'];
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
