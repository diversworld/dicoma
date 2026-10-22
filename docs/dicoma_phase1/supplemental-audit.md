# Ergänzende statische Prüfung – DICOMA

Datum: 21.10.2026. Grundlage: unverändertes `dicoma2110.zip`. Keine Laufzeit- oder Datenbanktests.

## Umfang
- Geprüfte PHP-Dateien unter `src/`, `config/`, `tests/`: 329
- Controller-PHP-Dateien: 122
- Entity-PHP-Dateien: 66
- Gefundene `#[Route(...)]`-Attributanfänge in Controller-Dateien: 216

## Sicherheitskonfiguration
- `config/packages/security.yaml`: Die Installations-Firewall ist deaktiviert (`security: false`), während der `InstallationGuard` die Installation über `DICOMA_INSTALL_ENABLED` kontrolliert. Beide Mechanismen gemeinsam prüfen.
- `^/courses` ist im `access_control` öffentlich. Die Controller müssen schreibende Aktionen eigenständig und objektbezogen absichern. Die Konfiguration allein ist kein Nachweis einer Lücke.
- Die letzte `access_control`-Regel fordert `ROLE_MEMBER`; dadurch sind zusätzliche öffentliche Endpunkte nur über explizite Ausnahmen bzw. andere Firewalls erreichbar.

## CSRF-Prüfkandidaten
Controller mit `remove()`/`flush()` ohne textuell erkennbare CSRF-Token-Prüfung in derselben Datei: 3. Dies ist ein Suchindikator, **kein bestätigter Fehler** (Form-Komponente, HTTP-Methoden, Service oder übergreifende Guards können schützen).

- `src/Controller/ResetPasswordController.php`
- `src/Controller/RegistrationController.php`
- `src/Controller/CheckController.php`

## PHP-Syntaxprüfung (isolierte temporäre Kopien)
- `src/Controller/CoursesController.php`: PASS – No syntax errors detected in /tmp/dicoma_lint_CoursesController.php
- `src/Controller/InstallController.php`: PASS – No syntax errors detected in /tmp/dicoma_lint_InstallController.php
- `src/Controller/Frontend/CourseRegistrationController.php`: PASS – No syntax errors detected in /tmp/dicoma_lint_CourseRegistrationController.php
- `src/Security/InstallationGuard.php`: PASS – No syntax errors detected in /tmp/dicoma_lint_InstallationGuard.php

## Begrenzungen und nächste Prüfungen
- Ein vollständiger Symfony-Kernelstart, `debug:router`, `lint:container`, PHPUnit und Doctrine-Migrationen wurden hier nicht ausgeführt.
- Routenattribute wurden nur textuell inventarisiert; YAML-/PHP-Konfigurationsrouten und dynamische Routen müssen mit `debug:router` abgeglichen werden.
- Als erstes: Zwei-Vereine-Zugriffstests für Kurs-Edit/Delete und objektbezogene Dateioperationen; Installations-Negativtests; Gastanmeldung end-to-end.
- Produktive Dateien unverändert.
