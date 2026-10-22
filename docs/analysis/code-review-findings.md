# DICOMA – Vertiefte Quellcodeprüfung (21.10.2026)

**Methode:** Read-only-Sichtung direkt aus `dicoma2110.zip`. Keine Laufzeit-, DB- oder Penetrationstests. Befunde sind nachweisbare Codebeobachtungen; Risiken sind nicht mit nachgewiesenen Exploits gleichzusetzen.

## Befund S-01 – Installationszugang: Schutz vorhanden, Konfiguration prüfen (hoch)
- `config/packages/security.yaml`: Firewall `install` mit `security: false` für `^/(install|save_db_config)`; `access_control` erlaubt beide Pfade öffentlich.
- `src/Controller/InstallController.php`: Beide Actions rufen `InstallationGuard::denyUnlessExplicitlyEnabled()` auf.
- `src/Security/InstallationGuard.php`: Verweigert Zugriff per 404, sofern `DICOMA_INSTALL_ENABLED` nicht exakt `1` ist.
- **Bewertung:** Frühere Vermutung einer *ungeschützten* Installationsfunktion wäre falsch: ein expliziter Guard ist vorhanden. Das Restrisiko entsteht bei aktivierter Umgebungsvariable und fehlender zusätzlicher Zugangskontrolle.
- **Prüfung:** Produktions-Environment, alle Installer-Routen, Negativtests mit aktivierter/deaktivierter Variable, Installation nach Abschluss.

## Befund S-02 – Installationsaktion führt Schemaänderung direkt aus (hoch)
- `src/Controller/InstallController.php`, Methode `saveDbConfig()`: Schreibt `.env.local`, führt `doctrine:database:create` und `doctrine:schema:update --force` aus.
- **Risiko:** Bei explizit aktivierter Installation können HTTP-Anfragen strukturelle Datenbankänderungen auslösen; `schema:update --force` umgeht den migrationsbasierten Freigabeprozess.
- **Prüfung/Entwurf:** Installationszustandsautomat, einmalige Freigabe, keine produktive Schemaänderung über HTTP; Migrationsverfahren planen, bevor Änderungen umgesetzt werden.

## Befund S-03 – Vereinsbezogene Objektprüfung bei Kursbearbeitung zu prüfen (hoch)
- `src/Controller/CoursesController.php`: `index()` filtert nach `currentClub()`, aber `edit()` nimmt `Courses $course` per ID entgegen und prüft im gezeigten Methodenrumpf nur `ROLE_ADMIN`, keine Zugehörigkeit des Kurses zum aktuellen Verein. `delete()` ebenso.
- **Risiko:** Falls Benutzer verschiedener Vereine diese Rollen besitzen, sind vereinsfremde Datensätze potenziell änderbar. Die tatsächliche Ausnutzbarkeit hängt von weiteren Security-/Doctrine-Regeln ab.
- **Prüfung:** Integrations-/Controller-Test mit zwei Clubs und zwei Administratoren; alle ID-basierten Actions auf Voter/Club-Scoping untersuchen.

## Befund F-01 – Gastanmeldung ist nicht durch vorhandenen Mitglieder-Flow erfüllt (hoch)
- `src/Controller/Frontend/CourseRegistrationController.php`: Klassenattribut `#[IsGranted('ROLE_MEMBER')]`; `currentMember()` verlangt `User` mit `Member`-Zuordnung.
- **Bewertung:** Dieser konkrete Registrierungsweg unterstützt keine Nichtmitglieder. Es ist separat zu prüfen, ob ein anderer öffentlicher Gast-Registrierungsweg existiert; kein pauschaler Schluss auf das Gesamtmodul.

## Befund S-04 – Bilddatei-Upload und Dateilöschung untersuchen (mittel/hoch)
- `src/Controller/CoursesController.php`: `new()` nutzt den Original-Dateinamen mit angehängter Extension; `delete_image()` löscht anhand übergebener Dateinamen nach Zeichenfilter und Rollenprüfung, aber ohne sichtbare fachliche Objektzuordnung.
- **Risiko:** Namenskollisionen und fehlende objektbezogene Löschberechtigung. Upload-Konfiguration und CSRF-/HTTP-Methode müssen geprüft werden.

## Vor Implementierungsfreigabe zwingend
1. `php bin/console debug:router`, `lint:container`, Doctrine-Mapping und Migrationsstatus in isolierter Testumgebung ausführen.
2. Security-Voter, globale Filter, Berechtigungsprüfungen und Club-Zuordnungen für die Befunde vollständig verfolgen.
3. Zwei-Vereine-Tests sowie Installations-Negativtests definieren.
4. Eine rückwärtskompatible Änderungsliste und Rollback-Strategie erstellen.

**Änderungsstand:** Keine produktiven Codeänderungen; nur dieses Analysedokument ergänzt.
