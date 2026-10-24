# DICOMA – Phase 2: Abschluss- und Abnahme-Gates

Stand: 24.10.2026. Die Umsetzung umfasst ein normalisiertes Personenmodell, **zwei Datenmigrationen und eine additive Indexreparaturmigration**. Dieses Dokument ist kein Beleg für eine Produktivfreigabe.

## Abschließende technische Abnahme der lokalen Umgebung

Nach ausdrücklicher Freigabe wurde nun auch der echte lokale Bestand aus Dienst `db`, Datenbank `db`, geprüft und aktualisiert:

- Vorher-Backup von Datenbank, Anwendung einschließlich Abhängigkeiten und vorhandener Konfiguration/Dateien geschützt aufbewahrt; vollständiges Archiv und Datenbank auf getrennten temporären Zielen wiederhergestellt und verglichen.
- **24 Zielmigrationen** auf der Wiederherstellungskopie erfolgreich; ursprüngliche Zeilen aller **56 Anwendungstabellen**, sämtliche Personenfelder und Kontoverknüpfungen erhalten.
- Dabei die bereits kanonischen historischen Ausrüstungsindizes in `Version20261022110559` berücksichtigt; bei fehlenden, mehrdeutigen oder fachlich falschen Indexdefinitionen weiterhin Abbruch vor Änderungen.
- Anschließend mit gesonderter ausdrücklicher Freigabe dieselben Migrationen auf lokalem `db` angewendet, Prod-Verbindung korrigiert und Prod-Cache neu aufgebaut. Mapping synchron, keine offenen Migrationen, jeweils zwei Personen/Mitgliedschaften/Konten erhalten.
- Persistenter Installationsabschlussmarker vorhanden; reale HTTPS-Prüfung: Login **200**, Installation **404**.
- Finales kombiniertes Gate: **97 Tests, 1.191 Assertions**; vollständige Migrationssuite: **14 Tests, 112 Assertions**. Keine übersprungenen/unvollständigen Tests.

**Die technische lokale Phase-2-Abnahme ist damit abgeschlossen.** Der Auftraggeber hat den Beginn von Phase 3 auf dieser Basis ausdrücklich freigegeben. Die nachstehende Checkliste bleibt eine weitergehende externe Produktiv-/Betriebsfreigabe, nicht mehr die Sperre für lokale Phase-3-Entwicklung.

Der tatsächliche Ausgangsbestand enthält keine privaten Dokumentdatensätze, Ausrüstungsbuchungen oder Kurse. Dokumentabläufe sind deshalb zusätzlich mit synthetischen HTTP-Fixtures getestet; eine fachliche Abnahme sämtlicher Kassen-/Leih-/Ausbildungsabläufe wird daraus nicht abgeleitet. Es wurde kein externer Produktivserver verändert.

## Aktueller lokaler Nachweis

Das kombinierte Gate mit Schema-/Migrationsprüfung und vollständiger vorhandener Staging-HTTP-Suite besteht auf DDEV: **95 Tests, 1.093 Assertions**. `db_test` ist nach der Indexreparatur synchron und migrationsaktuell. Die lokale Backup-/Restore-Probe umfasst 70 Tabellen mit byteidentischem Schema-/Daten-Dump; ein produktionsnaher Komplett-Restore ist damit nicht nachgewiesen. Die Rollenänderungen wurden am 24.10.2026 fachlich bestätigt.

Die erweiterte Migrationssuite und die gezielte Wiederholung ihres korrigierten historischen Rollbacktests sind im [Prüfbericht](docs/testing/test-report.md) getrennt ausgewiesen. Kein vollständiger grüner Wiederholungslauf dieser Suite wird daraus abgeleitet.

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
- [x] Technisches Basis-Gate auf dem aktuellen lokalen Arbeitsstand erfolgreich; eingefrorenes Release bleibt gesondert offen.
- [ ] Verlustfreie Personenmigration auf isolierter Kopie des Zielbestands geprüft; alle Kopierprüfungen erfolgreich.
- [ ] Zielmapping und Schema stimmen überein; keine offenen Migrationen.
- [x] Vollständige vorhandene Staging-HTTP-Suite auf lokalem DDEV-Testdienst ohne übersprungene Tests erfolgreich.
- [ ] Rollenmatrix anonym / Gast / aktives und inaktives Mitglied / Vereinsadmin A / Vereinsadmin B / Superadmin abgenommen.
- [x] Direkte CRUD-URLs, manipulierte Formulare und gültige schreibende Versuche mit fremden Vereins-IDs im Umfang der vorhandenen lokalen HTTP-Suite getestet.
- [ ] Dokumente, TÜV, Kasse, Ausrüstung und bestehende Kursprozesse auf produktionsnahem Staging regressionsgetestet.
- [x] Bewusster Entzug impliziter Mitgliedsrechte und die Superadmin-only-Grenzen fachlich freigegeben (24.10.2026, laufender Auftrag).
- [ ] Backup und Wiederherstellung tatsächlich durchgeführt und nachgewiesen; Wartungsfenster und Rückfallstrategie vereinbart.
- [ ] Verantwortliche, Datum, Release-Version und Testergebnisse festgehalten.

Die noch offenen fachlichen und betrieblichen Freigaben betreffen das externe Produktivdeployment. Die lokale Zielbestands-/Restore-Prüfung und ausdrücklich freigegebene Aktualisierung sind im obigen Abschluss dokumentiert; Phase-3-Entwicklung ist darauf freigegeben.

## Weiterführende Dokumentation

- [Aktueller Umsetzungsstand](docs/phase2-progress.md)
- [Prüfbericht](docs/testing/test-report.md)
- [Betrieb, Migration und Wiederherstellung](docs/deployment/operations.md)
- [Vorhandene fixturebasierte HTTP-Abnahme](docs/PHASE2-HTTP-ABNAHME.md)
