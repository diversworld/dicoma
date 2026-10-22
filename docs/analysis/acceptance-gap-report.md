# DICOMA – Abnahme-Gap-Bericht Phase 1

Stand: 21.10.2026. Grundlage: unverändertes Archiv `dicoma2110.zip`, bestehende Phase-1-Analysen. **Keine Änderungen am produktiven Quellcode.**

## Verifizierte Fakten aus dem Archiv

- `composer.json`: PHP >=8.2; Symfony ^7.0; Doctrine ORM ^2.17; PHPUnit ^13.4 (require-dev).
- `composer.lock`: `symfony/framework-bundle` v7.4.20, `doctrine/orm` 2.20.13, `easycorp/easyadmin-bundle` v4.29.16, `phpunit/phpunit` 13.4.1 (packages-dev). Lockversionen sind **keine** Laufzeitbestätigung.
- `vendor/` und `vendor/autoload_runtime.php` fehlen im Archiv. `bin/console` benötigt diese Datei explizit.
- `config/routes.yaml` bindet Attributrouten aus `src/Controller/` und EasyAdmin-Routen ein. Die statische Erfassung von 216 Attributanfängen ersetzt `debug:router` nicht.
- `config/packages/security.yaml` definiert für `^/courses` `PUBLIC_ACCESS`, während `CoursesController` u. a. `#[IsGranted('ROLE_MEMBER')]` bzw. `#[IsGranted('ROLE_ADMIN')]` verwendet. Daher ist die pauschale Behauptung, sämtliche Kursaktionen seien öffentlich, **nicht** belegt; einzelne Objektzugriffe müssen dennoch geprüft werden.
- `config/packages/security.yaml` definiert eine Firewall `install` mit `security: false`; Schutzmaßnahmen auf Controller-/Guard-Ebene müssen für sämtliche Installationspfade überprüft werden.
- Bisheriger Syntaxbericht: 447 geprüfte PHP-Dateien, keine Syntaxfehler (PHP 8.4.24). Keine Funktionsgarantie.

## Reproduzierbare Umgebungsblockaden

- In der aktuellen Umgebung ist `php` verfügbar (8.4.24), jedoch kein ausführbares `composer` im PATH.
- Die PHP-Erweiterung `intl` ist in der CLI-Ausgabe von `php -m` nicht vorhanden, obwohl `composer.json` `ext-intl` voraussetzt.
- Das bisherige Syntax-Arbeitsverzeichnis enthält keinen vollständigen Projektbaum (`bin/console` fehlt). Es ist **keine** geeignete Laufzeit-Testinstallation.
- Ohne isoliert installierte Abhängigkeiten, erforderliche PHP-Erweiterungen und Testdatenbank sind `lint:container`, `debug:router`, Doctrine-Mapping- und Migrationstests sowie PHPUnit derzeit nicht seriös als erfolgreich zu melden.

## Abnahmeentscheidende Restarbeiten

| ID | Arbeit | Akzeptanznachweis | Status |
|---|---|---|---|
| G01 | Isolierte Laufzeitumgebung (Composer, PHP-Erweiterungen, dev-Abhängigkeiten) herstellen | dokumentierte Versionen und `composer install`-Protokoll | blockiert |
| G02 | Symfony-Container und effektive Routen validieren | `lint:container`, `debug:router`, Fehlerliste | offen |
| G03 | Doctrine-Mapping und Migrationskette prüfen | Mapping-Check, Migration-Status und Probe auf Testdatenbank | offen |
| G04 | Objektberechtigungen und CSRF anhand relevanter Controller prüfen | Pfad/Methoden-Matrix mit Nachweisen, Risikoklassen | teilweise |
| G05 | Master-Anforderungen einzeln erfassen | vollständige Matrix, je Funktion Nachweis/Defizit/Test | offen |
| G06 | Testbestand ausführen und klassifizieren | PHPUnit-Protokoll inkl. Fehler/Blockaden | blockiert |
| G07 | Phase-2-Pakete datei- und risikobezogen konkretisieren | Roadmap mit Abhängigkeiten, Akzeptanzkriterien und Rollback | teilweise |

## Empfehlung zur formalen Abnahme

Phase 1 derzeit **nicht freigeben**. Ein dokumentierter Umgebungsblocker ist zulässig, wenn Ursachen und Folgen klar beschrieben sind; für eine belastbare technische Abnahme sollten zumindest G01–G05 erledigt und G06/G07 nachvollziehbar behandelt werden. Erst nach expliziter Nutzerfreigabe produktiven Code ändern.

## Offizielle technische Referenz

Symfony: https://symfony.com/doc/current/ ; Security: https://symfony.com/doc/current/security.html ; Testing: https://symfony.com/doc/current/testing.html
