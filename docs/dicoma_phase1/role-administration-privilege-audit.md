# DICOMA – Phase 1: Rollenvergabe und mögliche Privilegienausweitung

**Grundlage:** statische Prüfung des unveränderten `dicoma2110.zip`, 21.10.2026. Kein HTTP-Test, keine Änderung am Produktivcode.

## Zentrale Feststellung

Im `UserCrudController` genügt `ROLE_ADMIN` für den Zugang zur Benutzerverwaltung (Zeilen 21–22). Das bearbeitbare Rollenfeld (106–115) bietet über `User::getAvailableRoles()` auch `ROLE_SUPER_ADMIN` an (`User.php` 271–282). In `persistEntity()` und `updateEntity()` (127–155) sind keine zusätzlichen Prüfungen zur Rollenvergabe erkennbar. `User::setRoles()` (122–135) filtert nur nach dem Präfix `ROLE_`, nicht nach Befugnissen des Bearbeiters.

**Sicherheitsrelevante Konsequenz:** Ein Benutzer mit `ROLE_ADMIN` kann nach dem sichtbaren Formular- und Controller-Code grundsätzlich eine höhere Rolle (`ROLE_SUPER_ADMIN`) zuweisen, auch sich selbst, sofern keine außerhalb der untersuchten Stellen wirksamen Schutzmechanismen eingreifen. Ob dies dem fachlichen Rollenvertrag entspricht, ist ungeklärt. Dies ist eine **konkret belegte mögliche Privilegienausweitung**, keine durch HTTP-Test bestätigte Ausnutzung.

## Weitere Ergebnisse

- Die Rollen-Hierarchie in `config/packages/security.yaml` (17–22) gibt `ROLE_SUPER_ADMIN` die Rechte von `ROLE_ADMIN`; eine Vereinsdimension ist nicht enthalten.
- Die CLI-Anlage (`CreateAdminCommand.php` 181–199) weist ausdrücklich `ROLE_SUPER_ADMIN` zu. Das ist ein separater, interaktiver Verwaltungsweg, nicht automatisch ein öffentlicher Angriffspfad.
- Die Installationsregistrierung (`RegistrationController.php` 92–115) kann `ROLE_ADMIN` vergeben, ist aber mit `InstallationGuard::denyUnlessExplicitlyEnabled()` geschützt. Dessen tatsächliche Konfiguration muss gesondert geprüft werden.
- `User::getRoles()` (108–116) ergänzt stets `ROLE_MEMBER`. Diese Rolle allein beweist daher keine echte Mitgliedschaft.

## Bewertung und Abnahmekriterien

**Hoch, bedingt:** Soll `ROLE_SUPER_ADMIN` eine exklusive übergeordnete Rolle sein, ist die Rollenvergabe über das gewöhnliche `ROLE_ADMIN`-Benutzerformular fachlich nicht ausreichend eingeschränkt. Auch die Erstellung neuer Benutzer mit privilegierten Rollen muss entsprechend kontrolliert werden.

**Zu klären:** Darf `ROLE_ADMIN` Benutzer global verwalten und Super-Admins ernennen? Gibt es externe Beschränkungen durch EasyAdmin-Konfiguration, Events oder Firewall? Der Befund darf nicht ohne Laufzeittest als nachgewiesener Angriff bezeichnet werden.

**Empfohlene Tests (Phase 2 nach Freigabe):** Zwei normale Administratoren verschiedener Vereine, ein Super-Admin und ein unprivilegierter Benutzer. Prüfen: (1) Admin weist sich `ROLE_SUPER_ADMIN` zu, (2) Admin legt neuen Super-Admin an, (3) Admin verändert Rollen eines anderen Vereins, (4) Super-Admin darf legitime Rollen vergeben, (5) ungültige Rollenstrings werden abgelehnt. Assertions sowohl für HTTP-Status als auch persistierte Rollen.

**Symfony-orientierter Lösungsansatz:** Die Vergabe privilegierter Rollen über dedizierte Berechtigungen/`Voter` absichern und serverseitig in Schreibpfaden prüfen; Formularoptionen allein sind keine Autorisierung. Objektbezogene Club-Prüfungen getrennt von Rollenprüfungen behandeln. Vor Umsetzung Rollenvertrag fachlich abnehmen.

## Nachweismatrix

| ID | Quelle | Zeilen | Befund |
|---|---|---|---|
| ROLE-01 | `config/packages/security.yaml` | 17–22 | ROLE_SUPER_ADMIN erbt ROLE_ADMIN; ROLE_ADMIN erbt Vorstand/Kassenwart |
| ROLE-02 | `src/Controller/Admin/UserCrudController.php` | 21–22, 104–115 | ROLE_ADMIN kann Benutzer-CRUD öffnen; Rollenfeld bietet ROLE_ADMIN und ROLE_SUPER_ADMIN |
| ROLE-03 | `src/Controller/Admin/UserCrudController.php` | 127–155 | Persist/Update überschreiben Passwort-Hashing, aber keine Rollen-Eskalationsprüfung sichtbar |
| ROLE-04 | `src/Entity/User.php` | 120–135, 271–282 | setRoles akzeptiert beliebige ROLE_-Strings; angebotene Rollen enthalten Super-Admin |
| ROLE-05 | `src/Controller/RegistrationController.php` | 92–115 | Installationsroute setzt bei nicht-null adminUser ROLE_ADMIN, abhängig vom InstallationGuard |
| ROLE-06 | `src/Command/CreateAdminCommand.php` | 181–199 | CLI-Adminanlage vergibt ROLE_SUPER_ADMIN |
| ROLE-07 | `src/Entity/User.php` | 108–116 | ROLE_MEMBER wird getRoles unabhängig von Mitgliedschaft ergänzt |
