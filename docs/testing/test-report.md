# Phase-2-Prüfbericht

## Korrektur: EasyAdmin-Mitgliederverwaltung vom 24.10.2026

Der Menüeintrag „Mitgliedschaften und Beiträge“ führte zunächst zwar auf eine geschützte Verwaltungsroute, deren Templates erbten jedoch vom öffentlichen Frontend. Direkte Folgelinks hatten außerdem keinen EasyAdmin-Dashboard-Kontext. Diese Darstellung wurde durch die bisherigen reinen Formular-/Statusprüfungen nicht erkannt.

Die Verwaltungsrouten behalten ihre URLs und erhalten explizit den Dashboard-Kontext. Ein eigenes Backend-Layout verwendet die EasyAdmin-Sidebar, native Flashmeldungen und administrativ formatierte Formulare. Vereinsübersicht, Regeln, Beitragsklassen, Antragsentscheidungen, Mitgliedschaftsvorgänge und Zahlungen bleiben im Backend. Öffentliche Anträge und eigener Self-Service bleiben auch für angemeldete Admins im Frontend.

Erneut ausgeführt: **18 Phase-3-HTTP-/Integrationstests, 193 Assertions**, erfolgreich ohne übersprungene/unvollständige Tests. Der zusätzliche Ende-zu-Ende-Test beginnt beim echten EasyAdmin-Dashboard-Menü, öffnet die Vereinskonfiguration, legt eine Beitragsklasse an, bearbeitet ihren Betrag und prüft die Sidebar sowie die Abwesenheit der Frontend-Navigation auf jedem Schritt einschließlich Redirects. Öffentliche Seiten und Self-Service werden im selben angemeldeten Admin-Kontext ausdrücklich auf ihr Frontend-Layout geprüft. Bestehende Vereinsgrenzen, Zahlungen, SEPA und Mitgliedschaftsvorgänge bleiben mit abgedeckt.

Alle acht betroffenen Twig-Templates, Test-/Prod-Container und `git diff --check` sind erfolgreich geprüft. Der lokale Prod-Cache wurde erneuert und die Dashboard-Metadaten der tatsächlichen Prod-Route kontrolliert. Keine Datenbankmigration und keine produktiven Beitragssätze wurden für diese Korrektur angelegt oder geändert.

## Phase-3-Erweiterung und lokale Regression vom 24.10.2026

Phase 2 war vor Entwicklungsbeginn lokal technisch abgenommen; die vollständige Phase-3-Entwicklung einschließlich Beiträgen/SEPA sowie die lokale Aktivierung nach erfolgreichen Tests wurden ausdrücklich freigegeben. Die früheren Phase-2-Abschnitte unten sind historische Nachweise, keine aktuellen Blocker.

| Prüfung | Ausgeführtes Ergebnis |
|---|---|
| Vollständiges kombiniertes Gate | **88 Unit-/isolierte Tests, 337 Assertions** sowie **32 Staging-HTTP-Tests, 987 Assertions**: zusammen **120 Tests, 1.324 Assertions**, erfolgreich ohne übersprungene/unvollständige Tests. |
| Komplette MariaDB-Migrationssuite | **16 Tests, 125 Assertions**, erfolgreich; einschließlich Phase-3-Bestandserhalt und Abbruch vor DDL bei doppelten Mitgliedsnummern. |
| Ergänzender Legacy-Löschschutz | **1 Test, 7 Assertions**, erfolgreich: gültiger CSRF-POST führt zur Archivierungsverwaltung statt Löschung; ungültiger Token ergibt 403, Mitgliedschaft bleibt erhalten. Nach dem kombinierten Gate ergänzt und separat gezielt geprüft. |
| Weitere technische Prüfungen | Composer-Konfiguration/Plattform, YAML, Container, Routing, Mapping, Installationssperren sowie 15 betroffene Twig-Dateien und `git diff --check` erfolgreich. |
| Testdatenbank | Getrennter Dienst `db_test`, Datenbank `db_test`; Schema synchron und keine offenen Migrationen. HTTP-Schreibtests ausschließlich mit bestätigten lokalen Wegwerf-Fixtures, neue Phase-3-HTTP-Tests mit In-Memory-SQLite. Null-Mailer. |
| Tatsächlicher lokaler Zielstand | Direkt geprüft: Dienst `db`, Datenbank `db`, Hostname `dicoma-db`. Bereits beim Snapshot war `Version20261024102048` verbucht; deshalb **kein ausstehendes Phase-3-DDL** in diesem Aktivierungsschritt. Der vorherige Zwischenstand „nur Testdatenbank migriert“ traf zum Zeitpunkt dieser Zielprüfung nicht mehr zu. |
| Aktueller Snapshot/Restore | Anwendung, Abhängigkeiten, Konfiguration, öffentliche/private Dateien und Installationsmarker gesichert. Wiederhergestelltes Archiv einschließlich Rechten geprüft, Datenbank-Dumps byteidentisch. Snapshot 5 Sekunden, Restore/Dateivergleich 8 Sekunden. |
| Prüfung der wiederhergestellten Kopie | Alle ursprünglichen Zeilen/Spalten in **77 Anwendungstabellen** unverändert; zwei Mitglieder, Personen-/Kontozuordnungen erhalten. **0 ausstehende Migrationen**; frischer Prod-Container, Schema und Migrationshistorie erfolgreich. Dies ist ein Nachweis des bereits aktuellen Bestands, kein vorgetäuschter Vorher-/Nachher-Migrationslauf. |
| Lokale Aktivierung | Mit Freigabe Prod-Cache erneuert, tatsächliches Prod-Schema synchron und Historie aktuell. Je zwei Personen/Mitglieder/Konten erhalten; keine Anträge, Forderungen oder Nachrichten im lokalen Bestand erzeugt. |
| HTTPS/Browser | `/login` und `/membership/apply`: 200; anonyme Zugriffe auf Verwaltung/Self-Service: 302 zur Anmeldung; `/install`: 404. Öffentliche neue Navigation und Hinweis auf noch nicht aktivierte Antragsannahme im integrierten Browser geprüft. |
| Schutz/Bereinigung | Geschützter aktueller Snapshot außerhalb Git: Verzeichnis 0700, Dateien 0600. Keine temporären Migrations-/Snapshot-/Restore-Datenbanken mehr vorhanden. Keine externen Mails, Bankeinzüge oder Änderungen an externer Produktion. |

Im ersten kombinierten Unit-/SQLite-Lauf fehlte in der bewusst reduzierten `PersonModelTest`-Testdatenbank die neue FK-Zieltabelle `contribution_class`. Der Test baut jetzt das vollständige Entity-Schema auf; Personen-/Konto-Löschsemantik bleibt unverändert. Ein neuer Test für gemeinsam genutzte Kontaktadressen hatte zunächst eine vom Antrag abweichende Fixture-Adresse; die Fixture wurde korrigiert. Der oben genannte vollständige grüne Lauf erfolgte nach beiden Korrekturen.

Bei der nachträglichen Absicherung des alten Löschendpunkts fiel außerdem ein vorbestehender Fehler der alten `/member/{id}`-Anzeige auf: Das Template greift auf das nicht vorhandene Feld `postal` statt `postalCode` zu. Diese nicht mehr regulär verwendete Legacy-Anzeige wurde nicht nebenbei umgebaut. Der Löschschutz wurde mit gültigem Sitzungstoken direkt gegen den alten POST-Endpunkt und über die funktionierende neue Mitgliederverwaltung geprüft. Die neue Mitgliederverwaltung verwendet die korrekten Personen-/Adressfelder.

Funktionale Beschreibung, Einrichtungsbedarf und Grenzen: [Phase-3-Mitgliederverwaltung](../phase3-membership.md). Die technische lokale Prüfung ersetzt keine externe Produktivfreigabe oder fachliche Bestätigung tatsächlich beschlossener Beitragssätze, Satzung, Gläubiger-ID und Mailzustellung.

## Abschließende lokale Zielbestandsprüfung vom 24.10.2026

Die nachstehende erste Nachprüfung wurde um den echten lokalen `db`-Bestand und weitere Dokumenttests ergänzt:

| Prüfung | Abschließendes Ergebnis |
|---|---|
| Vollständiges kombiniertes Gate | **65 Basis-/isolierte Tests, 204 Assertions** und **32 Staging-HTTP-Tests, 987 Assertions**; insgesamt **97 Tests, 1.191 Assertions**, erfolgreich ohne übersprungene/unvollständige Tests. |
| Gesamte Migrationssuite | **14 Tests, 112 Assertions**, vollständig erfolgreich; einschließlich kanonischer historischer Ausrüstungsindizes, mehrdeutiger Indizes, Indexreparatur und historischer Rollbacks. Dieser Lauf ersetzt den zuvor dokumentierten fehlgeschlagenen Gesamtlauf samt gezielter Wiederholung. |
| Zielbestand vor Upgrade | Dienst `db`, Datenbank `db`, zuletzt `20261018150000`, 57 Tabellen einschließlich Migrationshistorie, zwei Mitgliedschaften/Konten und drei Flaschen; keine Kurse, privaten Dokumentdatensätze oder Ausrüstungsbuchungen. |
| Wiederherstellung | Restriktives Archiv von Anwendung einschließlich `vendor`, `public`, vorhandener Konfiguration und Dateien; Datei-/Rechtevergleich nach Extraktion erfolgreich. Datenbank-Dump nach Restore byteidentisch. Letzter bestätigter Wiederherstellungs-/Vergleichsschritt: 7 Sekunden; Snapshot-Erstellung davor: 4 Sekunden. |
| Zielmigration auf Kopie | **24 Migrationen**; SHA-256-Fingerprints sämtlicher ursprünglicher Zeilen in **56 Anwendungstabellen** unverändert, alle extrahierten Personenfelder bytegenau erhalten, Member-/Person-IDs und Konto-Zuordnungen erhalten. Schema synchron, keine ausstehenden Migrationen, frischer Prod-Container erfolgreich. |
| Reales Upgrade | Nach ausdrücklicher zusätzlicher Freigabe auf lokalem `db` angewendet. Korrekte Prod-Verbindung, Prod-Cache erneuert, Schema/Historie aktuell, jeweils zwei Personen/Mitgliedschaften/Konten erhalten. Bereits erfüllte additive/Indexmigrationen protokollieren korrekt Warnungen über fehlende SQL-Anweisungen, keinen Fehler. |
| Tatsächliches HTTPS | `https://dicoma.ddev.site/login`: 200; `/install`: 404. Persistenter Abschlussmarker zusätzlich angelegt, bestehende lokale Konfiguration bleibt außerhalb Git. |
| Dokumentregression | Neue synthetische Tests: Upload mit ungültigem/gültigem CSRF, Dateiintegrität, private Downloadheader, Eigentümer-/Vereinsgrenzen, Lösch-CSRF, tatsächliche Löschung sowie TÜV-Dokumentzugriff für Besitzer/Superadmin. Testdaten und Testdateien werden gezielt entfernt. |

Bei der ersten Zielmigration trat ein echter Fehler auf: Der alte Bestand hat bereits kanonische Ausrüstungsindexnamen, die Migration `20261022110559` wollte aber zwingend Altindizes umbenennen. Die historische Migration wurde deshalb gezielt um eine vollständige Vorprüfung beider zulässiger Namenszustände ergänzt; eine spätere Migration könnte den vorherigen Abbruch nicht reparieren. Neue Tests prüfen den akzeptierten Altbestand und den Abbruch bei mehrdeutigen Definitionen **vor** irgendeiner Umbenennung. Der erste Archivvergleich scheiterte außerdem an durch `umask` veränderten Rechten; die Extraktion erhält nun ausdrücklich die archivierten Rechte.

Reproduktion des historischen Ziel-Upgrades:

```bash
bash scripts/phase2_target_snapshot_check.sh --confirm-local-db-snapshot
```

Das Skript erwartet bewusst einen historischen Ausgangsbestand vor der Personenextraktion. Nach dem hier freigegebenen tatsächlichen Upgrade diesen historischen Prüfablauf nur auf dem aufbewahrten Vorher-Snapshot ausführen, nicht den migrierten Bestand als historischen Nachweis ausgeben. Beschreibung und Backup-Aufbewahrung siehe [Betrieb](../deployment/operations.md).

**Geltungsbereich:** technische lokale Phase-2-Abnahme abgeschlossen, Phase-3-Entwicklung ausdrücklich freigegeben. Keine externe Produktivfreigabe, kein Commit/Deployment und keine behauptete fachliche Abnahme nicht vorhandener realer Dokument-/Kassen-/Leih-/Kursdaten.

## Aktuelle Nachprüfung vom 24.10.2026

Umgebung: DDEV, PHP 8.4.24, PHPUnit 13.4.3, tatsächlicher MariaDB-Server `11.8.9-MariaDB-ubu2404`, separater Dienst und Datenbank `db_test`. Git-Basis: `0acebac` mit bestehenden uncommitteten Phase-2-Änderungen und den hier beschriebenen Ergänzungen; kein eingefrorenes oder deploytes Release.

Testschreibzugriffe, lokale Restore-Probe und die beschriebenen Rollenbeschränkungen wurden im laufenden Auftrag ausdrücklich freigegeben.

| Prüfung | Aktuelles Ergebnis |
|---|---|
| Finales Abnahme-Gate mit beiden Staging-Schaltern | Erfolgreich: Composer/Plattform, YAML, Container, Routing, Mapping, Installationssperren, Basis- und HTTP-Tests. |
| Unit und isoliertes SQLite im finalen Gate | **65 Tests, 204 Assertions**, keine übersprungenen oder unvollständigen Tests. |
| Vollständige vorhandene Staging-HTTP-Suite im finalen Gate | **30 Tests, 889 Assertions**, keine übersprungenen oder unvollständigen Tests; bestätigte lokale Wegwerf-Fixtures, Null-Mailer. |
| Schema und Migrationen auf `db_test` | Vor Reparatur fehlender `uniq_member_person_club` trotz aktueller Historie; nach neuer Migration `20261024100000` vollständig synchron und keine offenen Migrationen. |
| Migrationssuite einschließlich neuer Reparaturfälle | Gesamtlauf: 12 Tests, 99 Assertions, davon ein Fehler im historischen Rollbacktest, weil die neue absichtlich irreversible Migration noch nicht in dessen Ausnahmeliste stand. Die übrigen 11 Fälle bestanden, einschließlich Indexreparatur, Dublettenabbruch und frischer Installation. |
| Gezielte Wiederholung des korrigierten historischen Rollback-/Reapply-Tests | **1 Test, 6 Assertions**, erfolgreich nach Ergänzung der Irreversibilitätsliste. Kein erneuter Gesamtlauf der Migrationssuite behauptet. |
| Finale lokale Backup-/Restore-Probe | **70 Tabellen**, Restore in neuer temporärer Datenbank, byteidentischer vollständiger Schema-/Daten-Dump, Wiederherstellung 8 Sekunden. SHA-256: `20af51102d6cfbdb7d7cca459990700b967b18877186d743952b4c64dbaded26`. |
| Schutz und Bereinigung | Restore ohne Bestätigung verweigert; am Ende keine temporären Restore-/Migrationsdatenbanken vorhanden. PHP-/Bash-Syntax und `git diff --check` erfolgreich. |

Das finale kombinierte Gate bestätigt damit **95 Tests mit 1.093 Assertions**. Die Migrationsprüfungen und der Restore sind separate Nachweise.

Der erste Versuch des Basis-Gates auf dem Host mit PHP 8.5.4 scheiterte korrekt an 18 übersprungenen Tests wegen fehlendem `pdo_sqlite`. Es wurde keine grüne Host-Abnahme behauptet und kein Paket installiert; die vollständige Prüfung erfolgte mit den vorhandenen Erweiterungen in DDEV.

Reproduktion:

- Finales Gate: Umgebungswerte aus [lokaler HTTP-Abnahme](../PHASE2-HTTP-ABNAHME.md) verwenden und zusätzlich `DICOMA_RUN_STAGING_DB_CHECKS=1 DICOMA_RUN_STAGING_HTTP_TESTS=1` setzen; statt PHPUnit `bash scripts/phase2_acceptance_gate.sh` ausführen.
- Migrationsfälle: `--testsuite migrations`; der gezielte Wiederholungslauf verwendete zusätzlich `--filter testHistoricalReversibleMigrationsCanBeRolledBackAndReapplied`. Verbindungsdaten nur für den separaten lokalen Testdienst konfigurieren, nicht für Produktion.
- Restore: [Betriebsanleitung](../deployment/operations.md#reproduzierbare-lokale-backup-restore-probe).

### Weiterhin offene Freigaben

- Kein bereitgestellter produktionsnaher Ziel-Snapshot und kein Schema-/Migrations-/Datenvergleich dieses Bestands.
- Keine vollständige Wiederherstellung von Datenbank **und** privaten Dokumenten/Uploads, Konfiguration, Installationsmarker und Anwendung eines solchen Snapshots.
- Keine vollständige fachliche Regression aller Dokument-, Kassen-, Ausrüstungs- und Ausbildungsprozesse auf diesem Zielbestand; die vorhandene HTTP-Suite allein deckt das nicht ab.
- Kein eingefrorenes Release, kein Deployment, keine ausgeführte GitHub-Actions-Matrix und keine Produktivfreigabe. Die fachliche Rollenentscheidung ist dagegen bestätigt.

Phase 3 wurde wegen dieser noch offenen verbindlichen Phase-2-Abnahmekriterien nicht begonnen. Bestehende Änderungen wurden erhalten; ausschließlich der freigegebene lokale Testdienst wurde migriert.

## Vorheriger Nachweis vom 23.10.2026

Umgebung: DDEV, PHP 8.4.24, Symfony 7.4.21, Doctrine ORM 2, PHPUnit 13.4.3, MariaDB 11.8.

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

## Ergebnisse vom 23.10.2026

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

## Zum 23.10.2026 noch ohne Nachweis

- Keine ausgeführte GitHub-Actions-Matrix für PHP 8.4/8.5; die Workflow-Konfiguration allein ist kein Testergebnis.
- Keine vollständige Staging-HTTP-Abnahme aller alten CRUD-, Kassen-, TÜV-, Ausrüstungs- und Dokumentaktionen; diese Suite benötigt ausdrücklich bereitgestellte Fixtures und Schreibfreigaben.
- Keine tatsächliche Backup-/Restore-Probe eines produktionsnahen Snapshots.
- Keine Prüfung produktiver Mailzustellung, Infrastruktur-Härtung, Datenschutzprozesse oder Last-/Konkurrenzfälle aller Services.
- Keine vollständige Gast-Kursanmeldung ohne Member, keine Umsetzung der Folgephasen allein durch die neue Personenstruktur.

Die isolierten Tests beweisen die aufgeführten Fälle auf SQLite; sie ersetzen weder die MariaDB-Migrationssuite noch die vollständige Staging-/Betriebsabnahme. Phase 2 erhält damit keine automatische Produktivfreigabe.
