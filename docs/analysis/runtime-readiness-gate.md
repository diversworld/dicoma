# Phase 1 – reproduzierbare Laufzeitfreigabe und Testabdeckung

Stand: 22.10.2026. Quelle: unverändertes Originalarchiv `dicoma2110.zip`, `composer.json`, `composer.lock`, `phpunit.dist.xml`, `tests/`, lokale PHP-CLI. Keine produktiven Änderungen.

## Tatsächlich überprüfte Voraussetzungen

- PHP-CLI: 8.4.24; `composer.json` verlangt PHP >=8.2.
- Composer ausführbar: **nein**. `vendor/autoload.php` im ZIP: **nein**.
- Composer verlangt die PHP-Erweiterungen: ext-ctype, ext-iconv, ext-intl. Lokal fehlend: **ext-intl**.
- PDO vorhanden, aber **kein PDO-Datenbanktreiber** geladen. Keine isolierte Testdatenbank bereitgestellt.
- PHPUnit-Konfiguration: `phpunit.dist.xml`, Bootstrap `tests/bootstrap.php`, Test-Suite `tests/`; 9 Dateien mit `*Test.php` im ZIP.
- Composer-Skripte führen beim Installieren `cache:clear` und `assets:install` aus. Installation deshalb erst in isolierter Kopie mit geeigneter Konfiguration durchführen.

## Konsequenz

Symfony-Kernel, Container, Doctrine-Mapping, Migrationen, HTTP-Autorisierung und PHPUnit wurden **nicht ausgeführt**. Das ist eine Umgebungsblockade und kein Nachweis für funktionale Fehler. Die bestehende PHP-Lint-Prüfung ersetzt diese Tests nicht.

## Reproduzierbare Freigabereihenfolge (nur isolierte Kopie)

1. PHP-Erweiterung `intl`, einen passenden PDO-Datenbanktreiber und Composer bereitstellen; PHP-Version und Composer-Lock unverändert dokumentieren.
2. Isolierte Arbeitskopie des Archivs anlegen. Separate `APP_ENV=test`, `APP_SECRET`, `DATABASE_URL` und Testdatenbank bereitstellen; niemals produktive Zugangsdaten verwenden.
3. Zunächst `composer validate --strict`, dann `composer install --no-interaction --prefer-dist` **in der isolierten Kopie**. Vor der Installation Auswirkungen der Composer-Auto-Skripte berücksichtigen.
4. `php bin/console lint:container --env=test` und `php bin/console debug:router --env=test` ausführen; Ausgaben archivieren.
5. `php bin/console doctrine:schema:validate --env=test` ausführen. Migrationen nur auf einer entbehrlichen Testdatenbank mit geprüftem Migrationsplan. Kein `schema:update --force` gegen Bestandsdaten.
6. `php bin/phpunit` ausführen und Fehlermeldungen/Testabdeckung dokumentieren; danach die 19 geplanten Regressionstests implementierungsunabhängig als Abnahmekriterien prüfen.

## Vorhandene Tests und Abdeckungslücke

9 vorhandene `*Test.php`-Dateien sind inventarisiert (`runtime-test-coverage-inventory.csv`). Die Zahl ist **keine** Aussage über erfolgreiche Tests oder Codeabdeckung. Die 19 geplanten Sicherheits-/Regressionsfälle sind noch nicht als ausgeführte Tests nachgewiesen.

## Freigabeentscheidung

**Gate nicht bestanden (Umgebung nicht ausführbar).** Phase 1 bleibt offen. Es wurden weder Abhängigkeiten installiert noch Migrationen ausgeführt oder produktive Dateien verändert.

Dokumentationsgrundlagen: https://symfony.com/doc/current/testing.html ; https://symfony.com/doc/current/setup.html ; https://symfony.com/doc/current/doctrine.html
