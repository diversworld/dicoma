# Phase 1 – Statische Nachprüfung und ausführbarer Testplan

Stand: 22.10.2026. Ausschließlich lesender Zugriff auf `dicoma2110.zip`. **Keine Änderungen am Anwendungscode, keine HTTP-/Datenbanktests.**

## Statische Nachprüfung

9 von 9 gezielten Quellcodeaussagen konnten im Originalarchiv erneut nachgewiesen werden. Die Datei `critical-findings-static-recheck.csv` enthält je Befund Quelldatei und tatsächliche Zeile. Dies sind **Quellcodebelege, keine Laufzeit- oder Exploit-Nachweise**.

Besonders wichtig: `RegistrationController.php` verwendet `setPostal()`; in `Member.php` ist diese Methode nicht definiert. Der Verifizierungsendpunkt fordert `IS_AUTHENTICATED_FULLY`, während `UserChecker::checkPreAuth()` unverifizierte Benutzer abweist. Die Reset-Konfiguration verweist auf das Fake-Repository. Diese Widersprüche sind vorrangige Kandidaten für reproduzierbare Regressionstests.

## Testplan und Abhängigkeiten

`phase1-regression-test-plan.csv` enthält 19 konkret beschriebene Fälle mit Test-ID, Bezug zur Maßnahmenmatrix, Testeingabe, Soll-Ergebnis, Fixture-Anforderungen und Status `nicht ausgeführt`. Es sind **Testvorschläge**, keine vorhandenen PHPUnit-Testklassen.

Die Autorisierungstests AUTHZ-01 bis AUTHZ-03 setzen eine verbindliche fachliche Entscheidung voraus, ob `ROLE_ADMIN` global oder vereinsgebunden ist. Ohne diese Entscheidung wäre eine Soll-Aussage spekulativ.

## Reproduzierbare Testumgebung (Gate B)

1. Originalarchiv in isoliertes Arbeitsverzeichnis extrahieren und Produktionsdaten ausschließen.
2. PHP-Version und Erweiterungen mit `php -v` und `php -m` erfassen; `intl` und benötigte Extensions ergänzen.
3. Composer-Abhängigkeiten passend zu `composer.lock` in der isolierten Umgebung installieren; keine automatische Ausführung von produktiven Datenbankmigrationen.
4. `APP_ENV=test`, Test-Datenbank, Mailer-Sandbox und `DICOMA_INSTALL_ENABLED=0` festlegen.
5. `php bin/console lint:container --env=test`, `php bin/console debug:router --env=test`, `php bin/console doctrine:schema:validate --env=test` und `php bin/phpunit` erst nach erfolgreichem Boot ausführen.
6. Für Setup-Tests gesonderte isolierte Instanz ohne echte Zugangsdaten verwenden; Setup-Flag nur dort aktivieren.
7. Für konkurrierende Buchungen eine transaktionsfähige Testdatenbank mit mindestens zwei Verbindungen verwenden.

**Derzeitige Sperren:** Im Archiv fehlt `vendor/autoload.php`; Composer und PHP-`intl` waren in den bisherigen Laufzeitprüfungen nicht verfügbar. Es liegt keine Testdatenbank vor. Die hier beschriebenen Symfony-/Doctrine-Kommandos wurden in diesem Schritt nicht ausgeführt.

## Phase-1-Gate

Die 20 priorisierten Maßnahmen und 291 Anforderungen bleiben bestehen. Dieser Schritt erhöht die Nachweisqualität für neun statische Aussagen und spezifiziert 19 noch auszuführende Regressionstests; er ändert **keinen** Erfüllungsstatus der Anforderungen. Phase 1 ist weiterhin nicht abnahmebereit.

## Referenzen

- https://symfony.com/doc/current/testing.html
- https://symfony.com/doc/current/security.html
- https://symfony.com/doc/current/security/voters.html
- https://symfony.com/doc/current/doctrine.html
