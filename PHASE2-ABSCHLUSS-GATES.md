# DICOMA – Phase 2: Abschluss- und Abnahme-Gates

Stand: 23.10.2026. Die Umsetzung umfasst jetzt ein normalisiertes Personenmodell und **zwei neue Datenmigrationen**. Dieses Dokument ist kein Beleg für eine Produktivfreigabe.

## Reproduzierbares technisches Basis-Gate

```bash
ddev exec bash scripts/phase2_acceptance_gate.sh
```

Alternativ auf einer gleichwertigen PHP-Umgebung mit installierten Composer-Abhängigkeiten:

```bash
bash scripts/phase2_acceptance_gate.sh
```

Das Gate prüft Composer/Plattform, Symfony-Konfiguration, Routing, ORM-Mapping, Installationssperren sowie Unit- und isolierte SQLite-Tests. Übersprungene oder unvollständige PHPUnit-Tests lassen es fehlschlagen. Es migriert nicht und führt standardmäßig keine Schreibtests gegen die konfigurierte Anwendungsdatenbank aus.

Die eigenständige Prüfung `php tests/Security/phase2_route_gate.php` prüft nur Quellcodeattribute von 30 bestehenden Endpunkten. Sie ist **kein** vollständiger Nachweis von Objektberechtigungen, CSRF oder Vereinsgrenzen.

## Optionale Staging-Prüfungen

Zuerst die tatsächliche Testverbindung bestätigen:

```bash
APP_ENV=test php bin/console app:test-database:check --expected=db_test --env=test
```

Danach kann das Basis-Gate zusätzlich Schema und ausstehende Migrationen prüfen:

```bash
APP_ENV=test \
DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED=1 \
DICOMA_TEST_EXPECTED_DATABASE=db_test \
DICOMA_RUN_STAGING_DB_CHECKS=1 \
bash scripts/phase2_acceptance_gate.sh
```

Für die vollständige vorhandene Staging-HTTP-Suite zusätzlich `DICOMA_RUN_STAGING_HTTP_TESTS=1` und `DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED=1` setzen. Alle von den Tests benötigten Rollen-/Vereins-/Wegwerf-Fixtures müssen vorher bereitgestellt sein. Fehlende Fixtures ergeben keine grüne Abnahme.

`APP_ENV=test` allein genügt nicht: Doctrine kann einen Namenssuffix anhängen, während Host oder Basisdatenbank weiterhin falsch konfiguriert sind. Der neue Prüfcommand vergleicht den tatsächlichen Datenbanknamen. Zusätzlich sind getrennte Server, Testkonten und Backups nötig.

## Zwingende Freigabekriterien

- [ ] Release, PHP-Version, Composer-Lockfile und tatsächlich verwendete Konfiguration dokumentiert.
- [ ] Technisches Basis-Gate auf dem finalen Release erfolgreich.
- [ ] Verlustfreie Personenmigration auf isolierter Kopie des Zielbestands geprüft; alle Kopierprüfungen erfolgreich.
- [ ] Zielmapping und Schema stimmen überein; keine offenen Migrationen.
- [ ] Vollständige Staging-HTTP-Suite ohne übersprungene Tests erfolgreich.
- [ ] Rollenmatrix anonym / Gast / aktives und inaktives Mitglied / Vereinsadmin A / Vereinsadmin B / Superadmin abgenommen.
- [ ] Direkte CRUD-URLs, manipulierte Formulare und gültige schreibende Aktionen mit fremden Vereins-IDs getestet.
- [ ] Dokumente, TÜV, Kasse, Ausrüstung und bestehende Kursprozesse auf produktionsnahem Staging regressionsgetestet.
- [ ] Bewusster Entzug impliziter Mitgliedsrechte und die Superadmin-only-Grenzen fachlich freigegeben.
- [ ] Backup und Wiederherstellung tatsächlich durchgeführt und nachgewiesen; Wartungsfenster und Rückfallstrategie vereinbart.
- [ ] Verantwortliche, Datum, Release-Version und Testergebnisse festgehalten.

## Weiterführende Dokumentation

- [Aktueller Umsetzungsstand](docs/phase2-progress.md)
- [Prüfbericht](docs/testing/test-report.md)
- [Betrieb, Migration und Wiederherstellung](docs/deployment/operations.md)
- [Vorhandene fixturebasierte HTTP-Abnahme](docs/PHASE2-HTTP-ABNAHME.md)
