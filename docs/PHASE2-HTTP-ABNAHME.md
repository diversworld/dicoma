# DICOMA – Phase 2: HTTP-Mandantentrennung (Abnahmepatch)

## Umfang

Drei echte Symfony-WebTestCase-Tests mit HTTP-Requests an `GET /admin/course/{id}/history`:

1. Vereinsadmin A/B erhält bei Kurs des jeweils anderen Vereins HTTP 403.
2. Vereinsadmin A/B kann den eigenen Kurs aufrufen (HTTP 2xx).
3. Superadmin kann beide Kurse aufrufen (HTTP 2xx), einfacher Benutzer erhält HTTP 403.

Die Tests **lesen** vorhandene Staging-Fixtures. Sie legen keine Datensätze an und führen keine Schreib-HTTP-Requests aus. Sie testen **nicht** sämtliche EasyAdmin-CRUD-Aktionen, Mitglieder-Dokumentdownloads oder alle 100 Schritte. Ein grüner Testlauf ist daher **keine vollständige Phase-2-Freigabe**.

## Voraussetzungen

- Symfony `symfony/browser-kit`, `symfony/css-selector` soweit benötigt und `symfony/framework-bundle` mit Test-Client (`WebTestCase`); Composer-Abhängigkeiten auf dem Server prüfen.
- Eine **eigenständige, wiederherstellbare Testdatenbank**, niemals `h20292_dicoma`.
- Zwei bereits angelegte Vereine A und B mit je einem Kurs, zwei Benutzer mit `ROLE_ADMIN` und zugehörigem Mitglied im jeweiligen Verein, ein Superadmin und ein einfacher Benutzer.
- Auf Staging den `APP_ENV=test` und die Testdatenbank-Verbindung vorab überprüfen.

## Ausführung (nur Staging)

Die folgenden Werte sind **Platzhalter**. Keine produktiven E-Mail-Adressen verwenden.

```bash
export APP_ENV=test
export DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED=1
export DICOMA_TEST_EXPECTED_DATABASE='dicoma_staging_test'
export DICOMA_TEST_CLUB_A_ADMIN_EMAIL='admin-a@example.invalid'
export DICOMA_TEST_CLUB_B_ADMIN_EMAIL='admin-b@example.invalid'
export DICOMA_TEST_SUPER_ADMIN_EMAIL='super@example.invalid'
export DICOMA_TEST_ROLE_USER_EMAIL='user@example.invalid'
export DICOMA_TEST_CLUB_A_COURSE_ID='101'
export DICOMA_TEST_CLUB_B_COURSE_ID='202'
/opt/plesk/php/8.4/bin/php vendor/bin/phpunit --no-progress --testdox tests/Http/Phase2ClubIsolationHttpTest.php
```

Wenn eine Variable fehlt, werden die Tests **übersprungen** (nicht als bestanden werten). Wenn Fixtures nicht zusammenpassen, schlagen sie fehl. Vor der Datenabfrage wird die tatsächlich verbundene Datenbank mit `DICOMA_TEST_EXPECTED_DATABASE` verglichen; die bekannte Produktivdatenbank `h20292_dicoma` wird ausdrücklich abgewiesen. Das ist eine zusätzliche Schutzmaßnahme, keine Ersatzmaßnahme für Datenbankzugriffsbeschränkungen.

## Interpretation

- **3 Tests bestanden:** Nachweis für die sechs geprüften Rollen-/Kurs-Konstellationen auf der Kurs-Historienroute.
- **Skipped:** Keine Aussage zur Mandantentrennung; Staging-Konfiguration vervollständigen.
- **403 statt 2xx für eigene Kurse:** Benutzerrollen, Vereinszuordnung oder Voter prüfen.
- **200 statt 403 für fremde Kurse:** Sicherheitslücke, nicht produktiv freigeben.
- **500:** Fehler im Symfony-Stack oder Test-Fixtures; Stacktrace prüfen.

## Noch offen für eine vollständige Abnahme

- Mitgliederdetails, Bearbeiten, Löschen und Dokumentdownloads mit zwei Vereinen.
- EasyAdmin-Listenfilter und manipulierte POST-Requests auf Staging.
- Die beiden zuvor ausgelagerten `ClubMembershipTest`-Fälle fachlich entscheiden.
- Fachliche Freigabe der Superadmin-only-Übergangssperren; Backup-/Restore-Test.

Referenz: https://symfony.com/doc/current/testing.html
