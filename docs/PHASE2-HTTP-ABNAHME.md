# DICOMA – Phase 2: HTTP-Mandantentrennung (Abnahmepatch)

## Erneute lokale Abnahme vom 24.10.2026

Die vollständige vorhandene Staging-Suite wurde nach ausdrücklicher Schreibfreigabe auf dem getrennten DDEV-Dienst `db_test` ausgeführt: **30 Tests, 889 Assertions**, keine übersprungenen oder unvollständigen Tests. Vorher wurden Verbindung, gekennzeichnete Wegwerf-Fixtures und deren Vereinszuordnung geprüft. Der fehlende Person-/Verein-Unique-Index wurde mit `Version20261024100000` ergänzt; Schema und Mapping stimmen anschließend überein und keine Migration ist offen.

Reproduktion ausschließlich mit genau diesen zuvor geprüften lokalen Fixtures (IDs sind keine universellen Standardwerte):

```bash
ddev exec env APP_ENV=test MAILER_DSN=null://null \
  DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED=1 \
  DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED=1 \
  DICOMA_TEST_EXPECTED_DATABASE=db_test \
  DICOMA_TEST_CLUB_A_ADMIN_EMAIL=phase2-isolation-admin-a@example.invalid \
  DICOMA_TEST_CLUB_B_ADMIN_EMAIL=phase2-isolation-admin-b@example.invalid \
  DICOMA_TEST_SUPER_ADMIN_EMAIL=phase2-isolation-super-admin@example.invalid \
  DICOMA_TEST_ROLE_USER_EMAIL=phase2-isolation-normal-user@example.invalid \
  DICOMA_TEST_CLUB_A_COURSE_ID=1 DICOMA_TEST_CLUB_B_COURSE_ID=2 \
  DICOMA_TEST_CLUB_A_PARTICIPANT_ID=1 DICOMA_TEST_CLUB_B_PARTICIPANT_ID=2 \
  DICOMA_TEST_DISPOSABLE_MEMBER_A_ID=5 DICOMA_TEST_DISPOSABLE_MEMBER_B_ID=6 \
  php vendor/bin/phpunit --testsuite staging --no-progress \
  --fail-on-skipped --fail-on-incomplete
```

Die Mitgliedschaften 5/6 sind als `PHASE2-DISPOSABLE-A/B` markiert und haben kein Benutzerkonto. Die Suite prüft unter anderem gültige CSRF-Schreibversuche auf fremde Mitglieder, Buchungen und Kursteilnahmen sowie manipulierte Formbeziehungen und die bestehenden Superadmin-Beschränkungen für Lieferanten, Rechnungs-/TÜV-Einstellungen und Benachrichtigungen. Echte Mailzustellung wird bewusst durch den Null-Mailer ausgeschlossen.

Dieses Ergebnis ersetzt nicht die fachliche Regression sämtlicher Dokument-, Kassen-, Ausrüstungs- und Ausbildungsabläufe auf einem produktionsnahen Zielbestand. Die nachstehende frühere Drei-Test-Anleitung bleibt ein engerer Teilnachweis.

## Aktualisierung vom 23.10.2026

Die nachstehenden drei Kurs-Historientests sind ein älterer, enger Teilnachweis. Zusätzlich existieren jetzt datenbankunabhängig provisionierte HTTP-Regressionsfälle in `tests/Integration/Phase2FoundationHttpTest.php`: Sie verwenden ausschließlich SQLite im Speicher und einen Null-Mailer, nicht die unten beschriebenen Staging-Fixtures.

```bash
ddev exec php vendor/bin/phpunit --testsuite unit,isolated --fail-on-skipped
```

Diese Fälle prüfen unter anderem Gastregistrierung/-aufnahme, Verifizierung, Login, Mitgliedsrollen, vereinsbezogene Listen und Suche, Dashboard-Daten sowie Passwort-Reset und Logout. Sie ersetzen die vollständige vorhandene Staging-Suite nicht. Für deren kontrollierte Ausführung siehe [aktuelle Abnahme-Gates](../PHASE2-ABSCHLUSS-GATES.md) und [Prüfbericht](testing/test-report.md).

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
- Die beiden `ClubMembershipTest`-Basisfälle sind im Unit-Gate enthalten und bestanden; die vollständige fachliche Aufnahmematrix bleibt gesondert abzunehmen.
- Fachliche Freigabe der Superadmin-only-Übergangssperren; Backup-/Restore-Test.

Referenz: https://symfony.com/doc/current/testing.html
