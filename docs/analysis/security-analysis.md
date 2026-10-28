# DICOMA – Sicherheitsanalyse (vorläufig)

## Aktueller Berechtigungsvertrag, 28.10.2026

Die folgende Konfigurationskopie ist historisch und darf nicht zur aktuellen
Rollenbewertung verwendet werden. Maßgeblich ist `config/packages/security.yaml`.
Funktionsrollen erben **keine** aktive Mitgliedschaft. `User::getRoles()` leitet
`ROLE_MEMBER` aus der tatsächlichen Mitgliedschaft ab. Gäste behalten `ROLE_USER`.
Vorhandene Fachservices/Voter prüfen zusätzlich Vereins- und Objektzuordnungen.
`InstallationGuard` und der persistente Installationsabschluss schützen das
Setup; der historische Kommentar „später absichern“ ist kein aktueller Befund.

In der Konsolidierung sind insbesondere zu prüfen:

- `/portal` nur für angemeldete Konten, ohne Daten fremder Personen oder Vereine.
- Kein Verwaltungslink allein aufgrund einer Mitgliedschaft; keine versteckte
  Voraussetzung `ROLE_MEMBER` für unabhängig vergebene Funktionsrollen.
- Redaktion nur für Berechtigte des konfigurierten Website-Vereins.
- Sportverwaltung weiterhin durch `CompetitionAccess`, Ausbildung weiterhin
  durch Kursvoter/Zuordnungen geschützt.
- Rückwege in Website und persönliche Bereiche ohne zusätzliche Zugriffsrechte.
- Login behält geschützte Zielpfade bei; Logout bleibt CSRF-geschütztes POST.
- Nichtberechtigte Direktaufrufe müssen auch bei fehlendem Menü abgewiesen werden.

Dies ist eine Architektur-/Berechtigungsprüfung im Rahmen des Soll-Ist-Abgleichs,
kein vollständiger Penetrationstest oder Nachweis der DSGVO-/Produktionsreife.

## Historischer Ausgangsbefund

Statische Sichtung; kein Penetrationstest und keine Laufzeitprüfung.

## Security-Konfiguration
```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    role_hierarchy:
        ROLE_INSTRUCTOR: ROLE_MEMBER
        ROLE_EQUIPMENT_MANAGER: ROLE_MEMBER
        ROLE_COMPRESSOR_OPERATOR: ROLE_MEMBER
        ROLE_TREASURER: ROLE_MEMBER

        ROLE_BOARD:
            - ROLE_MEMBER
            - ROLE_INSTRUCTOR
            - ROLE_EQUIPMENT_MANAGER
            - ROLE_COMPRESSOR_OPERATOR

        ROLE_ADMIN:
            - ROLE_BOARD
            - ROLE_TREASURER

        ROLE_SUPER_ADMIN:
            - ROLE_ADMIN

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: user

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        install:
            pattern: ^/(install|save_db_config)
            security: false

        main:
            lazy: true
            provider: app_user_provider
            custom_authenticator: App\Security\AppCustomAuthenticator
            user_checker: App\Security\UserChecker

            entry_point: form_login

            form_login:
                login_path: app_login
                check_path: app_login
                username_parameter: _username
                password_parameter: _password
                default_target_path: app_schedule_index

            logout:
                path: app_logout
                target: app_login
                enable_csrf: true
                csrf_token_id: logout

#            remember_me:                           # --- 
#                secret: '%kernel.secret%'          # |
#                lifetime: 604800                   # | Löschen wenn die Entwickung abgeschlossen ist 
#                path: /                            # |
#                always_remember_me: true           # ---

    access_control:
        # ---------------------------------------------------------
        # Öffentlich erreichbare Bereiche
        # ---------------------------------------------------------

        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/register, roles: PUBLIC_ACCESS }
        - { path: ^/reset-password, roles: PUBLIC_ACCESS }

        # Installation muss ohne Login möglich sein.
        # Der InstallController muss später zusätzlich abgesichert
        # werden, damit nach erfolgter Installation kein erneuter
        # Zugriff möglich ist.
        - { path: ^/install, roles: PUBLIC_ACCESS }
        - { path: ^/save_db_config, roles: PUBLIC_ACCESS }

        # Kursübersicht bleibt zunächst öffentlich.
        # Schreibende Kursoperationen sichern wir später zusätzlich
        # direkt über Controller/Voter ab.
        - { path: ^/courses, roles: PUBLIC_ACCESS }

        # ---------------------------------------------------------
        # Administration
        # ---------------------------------------------------------

        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/settings, roles: ROLE_ADMIN }

        # ---------------------------------------------------------
        # Personenverwaltung
        # ---------------------------------------------------------

        - { path: ^/member, roles: ROLE_ADMIN }
        - { path: ^/student, roles: ROLE_INSTRUCTOR }
        - { path: ^/instructor, roles: ROLE_ADMIN }

        # ---------------------------------------------------------
        # Ausbildungsverwaltung
        # ---------------------------------------------------------

        - { path: ^/brevets, roles: ROLE_INSTRUCTOR }
        - { path: ^/booking, roles: ROLE_MEMBER }

        # ---------------------------------------------------------
        # Technik / Flaschen
        # ---------------------------------------------------------

        - { path: ^/tank, roles: ROLE_EQUIPMENT_MANAGER }
        - { path: ^/tankcheck, roles: ROLE_EQUIPMENT_MANAGER }
        - { path: ^/tank/check, roles: ROLE_EQUIPMENT_MANAGER }
        - { path: ^/vendor, roles: ROLE_EQUIPMENT_MANAGER }

        # ---------------------------------------------------------
        # Dateien
        # ---------------------------------------------------------

        - { path: ^/files, roles: ROLE_MEMBER }

        # ---------------------------------------------------------
        # Persönlicher Bereich
        # ---------------------------------------------------------

        - { path: ^/profile, roles: ROLE_MEMBER }

        # ---------------------------------------------------------
        # Vereinskalender
        # ---------------------------------------------------------

        - { path: ^/schedule, roles: ROLE_MEMBER }

        # ---------------------------------------------------------
        # Logout
        # ---------------------------------------------------------

        - { path: ^/logout, roles: ROLE_MEMBER }

        # ---------------------------------------------------------
        # Alles Weitere erfordert zumindest Vereinsmitgliedschaft.
        # ---------------------------------------------------------

        - { path: ^/, roles: ROLE_MEMBER }


when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4
                time_cost: 3
                memory_cost: 10
```

## Indikatoren
- `access_control`: in security.yaml erwähnt
- `role_hierarchy`: in security.yaml erwähnt
- `firewalls`: in security.yaml erwähnt
- `password_hashers`: in security.yaml erwähnt
- `PUBLIC_ACCESS`: in security.yaml erwähnt
- `remember_me`: in security.yaml erwähnt

## Kritische Prüfungen
- Installationsrouten und Installationsabschluss: src/Controller/InstallController.php
- Öffentliche und geschützte Routen samt Controller- und Objektberechtigungen.
- Mandanten-/Club-Isolation in Queries und Dokumentenzugriff.
- CSRF-Schutz bei schreibenden Aktionen und Zugriff auf private Dateien.
- Kassenabschluss, Storno, Doppelzahlungen, Reservierungskonkurrenz.
- Tauchtauglichkeit: Datenminimierung, restriktive Berechtigungen, Löschkonzept.

**Bewertung:** Noch keine belastbare Aussage zur Ausnutzbarkeit oder zur Produktionssicherheit möglich.
