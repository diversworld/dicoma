# Phase-2-Prüfbericht

Stand: 23.10.2026. Umgebung: DDEV, PHP 8.4.24, Symfony 7.4.21, Doctrine ORM 2, PHPUnit 13.4.3, MariaDB 11.8.

## Nachweise und Geltungsbereich

| Prüfung | Nachweis |
|---|---|
| Composer | Manifest validiert, installierte Plattformanforderungen erfüllt; Lockfile aktualisiert ohne Paketversionswechsel. |
| Symfony | Container, YAML, Router und Registrierungs-Twig geprüft. |
| ORM | Mapping geprüft; Schemaabgleich zusätzlich in neu erzeugten temporären MariaDB-Datenbanken, nicht gegen Produktion. |
| Installation | Persistenter Abschlussmarker und Bootstrap-Lock mit vorhandenen Standalone-Tests geprüft. |
| Personenmodell | Gast ohne Mitgliedschaft, zentrale Identität bei mehreren Mitgliedschaften, Rollen je Mitgliedsstatus und konsistente bidirektionale Kontozuordnung. |
| Persistenz | Identität bei Gastaufnahme unverändert; Konto- und Mitgliedschaftslöschung erhalten die jeweils anderen Datensätze. |
| Isolierte HTTP-Tests | Registrierung mit gültigem CSRF, ungültiger Registrierungs-CSRF, signierte Bestätigung ohne Login, manipulierte Bestätigung, Gastanmeldung, verweigerte Mitglieds-/Adminrechte, normale Adminlisten und Suche, fremde Bearbeitung, zentrale Personenverwaltung, Gastaufnahme und Bearbeitung, Dashboard-Isolation, inaktive Ausbilder, Reset-Einmaligkeit/Ablauf und Logout. |
| Historische Migrationen | Frische Installation, bestehende Upgradefälle, historische Rollbacks/Reapply, exakte Datenübernahme und Abbruch vor Spaltenentfernung bei Abweichungen. |

## Finale Ergebnisse

| Befehl / Prüfung | Ergebnis |
|---|---|
| Technisches Basis-Gate | Erfolgreich; alle ausgewählten Prüfungen bestanden. |
| `--testsuite unit,isolated` innerhalb des Gates | **65 Tests, 204 Assertions**, keine übersprungenen Tests, Fehler oder gemeldeten Deprecations. |
| `--testsuite migrations` | **10 Tests, 90 Assertions**, erfolgreich auf temporären MariaDB-Datenbanken; Laufzeit ca. 6 Minuten. |
| Tatsächliche Testverbindung | `app:test-database:check` bestätigt den realen Namen `db_test` auf dem lokalen separaten DDEV-Testdienst. |
| Fehlerhafter Staging-Schalter | Wird vor Testausführung mit Exit-Code 2 abgewiesen. |
| Ergänzende Syntaxprüfungen | 23 YAML-Dateien einschließlich Workflow und 3 Registrierungs-Twig-Dateien gültig; beide neuen Migrationen PHP-syntaktisch gültig; `git diff --check` ohne Befund. |

Die beiden PHPUnit-Läufe umfassen damit **75 Tests und 294 Assertions**. Sie enthalten nicht die separat freizugebende fixturebasierte Staging-HTTP-Suite.

Reproduzierbare Befehle:

```bash
ddev exec bash scripts/phase2_acceptance_gate.sh
ddev exec "MIGRATION_TEST_DATABASE_URL='mysql://root:root@db_test:3306/db_test?serverVersion=11.8.3-MariaDB' php vendor/bin/phpunit --no-progress --testsuite migrations"
```

Die generischen Zugangsdaten des zweiten Befehls gehören ausschließlich zum vorhandenen lokalen DDEV-Testdienst. Für andere Umgebungen einen ausdrücklich isolierten Testserver mit berechtigtem Testkonto verwenden. Die Migrationssuite legt zufällig benannte Testdatenbanken an und entfernt diese anschließend.

## Noch keine Nachweise

- Keine ausgeführte GitHub-Actions-Matrix für PHP 8.4/8.5; die Workflow-Konfiguration allein ist kein Testergebnis.
- Keine vollständige Staging-HTTP-Abnahme aller alten CRUD-, Kassen-, TÜV-, Ausrüstungs- und Dokumentaktionen; diese Suite benötigt ausdrücklich bereitgestellte Fixtures und Schreibfreigaben.
- Keine tatsächliche Backup-/Restore-Probe eines produktionsnahen Snapshots.
- Keine Prüfung produktiver Mailzustellung, Infrastruktur-Härtung, Datenschutzprozesse oder Last-/Konkurrenzfälle aller Services.
- Keine vollständige Gast-Kursanmeldung ohne Member, keine Umsetzung der Folgephasen allein durch die neue Personenstruktur.

Die isolierten Tests beweisen die aufgeführten Fälle auf SQLite; sie ersetzen weder die MariaDB-Migrationssuite noch die vollständige Staging-/Betriebsabnahme. Phase 2 erhält damit keine automatische Produktivfreigabe.
