# Betrieb und Wiederherstellung – Phase 2 und 3

## Phase 3: lokale Aktivierung und aktueller Snapshot

Die lokale Phase-3-Aktivierung wurde nach vollständigen erfolgreichen Prüfungen ausdrücklich freigegeben. Bei der anschließenden direkten Zielprüfung war `Version20261024102048` auf Dienst `db`/Datenbank `db` bereits verbucht; es wurde deshalb kein erneutes DDL und keine manuelle Änderung der Migrationshistorie vorgenommen. Prod-Cache, Schema, tatsächliches Verbindungsziel und HTTPS wurden geprüft bzw. erneuert.

Der hierbei erstellte und tatsächlich wiederhergestellte aktuelle Snapshot liegt außerhalb Git unter:

```text
/home/diversworld/.copilot/session-state/7ba4614b-3b41-43f7-983c-f277e378a7fc/files/phase3-before-local-prod-20261024/
```

**Trotz Verzeichnisname ist dies kein Vorher-Backup vor der Phase-3-Datenbankmigration:** Das Schema war bereits aktuell. Der Snapshot enthält `database.sql`, `application.tar` und `SHA256SUMS`, mit Verzeichnisrechten 0700 und Dateirechten 0600. Die Wiederherstellung in eine neue isolierte Testdatenbank und eine private Anwendungskopie war erfolgreich; alle ursprünglichen Daten in 77 Anwendungstabellen blieben unverändert. Snapshot und Archiv enthalten personenbezogene Daten/Secrets und dürfen nicht veröffentlicht werden.

Für zukünftige Updates denselben Snapshotablauf mit einem neuen eindeutigen Backupverzeichnis verwenden und die Quelle unmittelbar vor dem Upgrade prüfen. Die Phase-3-Migration ist additiv, prüft doppelte Mitgliedsnummern vor DDL und hat zum Schutz der Finanz-/Vorgangsdaten kein automatisches `down()`. MySQL-DDL kann teilweise committed sein; bei Abbruch den tatsächlichen Stand prüfen, nicht Historieneinträge fingieren. Für einen Rückfall einen zusammengehörigen, geprüften Release-/Datenstand wiederherstellen; alte Anwendungsversionen nicht ungeprüft gegen das neue Schema starten.

SEPA-Schlüssel, PHP-sodium-Anforderung, Senderkonfiguration und täglicher Job werden in [Phase-3-Mitgliederverwaltung](../phase3-membership.md#betrieb-schlüssel-und-nachrichten) beschrieben. Es wurde kein echter Versand eingeschaltet, kein Bankeinzug ausgeführt und kein Cronjob installiert.

## Vorbereitung

1. Release-Version und tatsächlich verwendete PHP-/MariaDB-Version dokumentieren. Entwicklungstests mit dem vorhandenen PHPUnit 13 benötigen PHP >=8.4.1 sowie `pdo_sqlite`; die vorhandenen MySQL-/MariaDB-Migrationen zusätzlich `pdo_mysql`.
2. Secrets ausschließlich außerhalb des Repositorys verwalten. Bestehende `.env.local`, `.env.test.local`, `DATABASE_URL`, Mailer-Konfiguration und private Dokumentpfade nicht durch Beispiele überschreiben.
3. Auf einer isolierten Kopie der Zielumgebung Composer-Abhängigkeiten aus dem Lockfile installieren und das Basis-Gate ausführen.
4. Datenbank, private Dokumente/Uploads, Konfiguration und `var/install-completed.marker` konsistent sichern. Der Installationsmarker darf bei Deployment, Cache-Bereinigung oder Restore nicht versehentlich verschwinden.
5. Restore in eine neue, zugriffsbeschränkte Testdatenbank tatsächlich ausprobieren. Ein existierender Dump allein ist kein Wiederherstellungsnachweis.

## Beispiel für ein Datenbankbackup

Nur mit einer vom Betreiber bereitgestellten, restriktiv geschützten Client-Konfiguration; keine Passwörter in Shell-History oder Prozessargumenten:

```bash
mariadb-dump --defaults-extra-file=/secure/dicoma-backup.cnf \
  --single-transaction --routines --events --triggers \
  --databases dicoma > /secure/backups/dicoma-before-phase2.sql
```

Der Datenbankname und die Pfade sind Platzhalter. Beim Restore eines Dumps mit `--databases` enthaltene `CREATE DATABASE`-/`USE`-Anweisungen berücksichtigen: Ein solcher Dump darf nicht ungeprüft auf einem gemeinsam genutzten Produktionsserver importiert werden. Restore ausschließlich auf einem isolierten Testserver durchführen oder einen hierfür separat erstellten Dump ohne diese Anweisungen verwenden.

Backups enthalten personenbezogene und finanzielle Daten: Zugriff beschränken, verschlüsselt aufbewahren, Integrität prüfen, Aufbewahrungsfristen festlegen und außer Haus nur in freigegebene Infrastruktur übertragen.

## Migrationsablauf

Die Personenmigration verschiebt Daten und entfernt anschließend die bytegenau verifizierten Altspalten. Sie benötigt ein Wartungsfenster:

1. HTTP-Schreibzugriffe, Worker und geplante Jobs stoppen; sicherstellen, dass auch zwischen beiden Migrationsschritten niemand Personendaten schreibt.
2. Geprüftes konsistentes Backup erstellen und Zielverbindung unabhängig kontrollieren.
3. Migration auf Staging zuerst bis `DoctrineMigrations\Version20261023100000` ausführen.
4. Kopierte Personen, Kontozuordnungen und Member-Anzahl kontrollieren. Danach bis `DoctrineMigrations\Version20261023110000` migrieren. Bei einem Prüfabbruch **nicht** mit SQL-Spaltenlöschungen oder manuell gesetzten Migrationsversionen umgehen.
5. `doctrine:schema:validate` und `doctrine:migrations:up-to-date` gegen genau diese Staging-Datenbank ausführen.
6. Cache mit den richtigen Umgebungsparametern neu aufbauen; HTTP- und fachliche Regressionstests ausführen.
7. Erst nach dokumentierter Freigabe denselben kontrollierten Vorgang für das Produktionsrelease planen. Die hier erfolgte Entwicklung führt keine Produktivmigration aus.

Die ergänzende Migration `Version20261024100000` sichert den Unique-Index für `(person_id, club_id)` auch auf bereits normalisierten Installationen ab. Auf dem lokalen `db_test` fehlte dieser Index trotz verbuchter Personenmigrationen. Die neue Migration verändert keine Mitgliederdaten, akzeptiert einen korrekt vorhandenen Index und bricht bei Dubletten oder einer abweichenden gleichnamigen Indexdefinition ab. Nicht durch `schema:update --force` oder manuelles Zurücksetzen der Migrationshistorie ersetzen.

MySQL-/MariaDB-DDL kann bereits vor einem Fehler committed sein. Bei teilweise ausgeführter erster Migration Schema und Daten vor einem erneuten Start ausdrücklich prüfen; ein vorhandenes `person`-Schema führt bewusst zu einem Abbruch.

## Rückfallstrategie

### Tatsächlich ausgeführte lokale Aktualisierung

Am 24.10.2026 wurden mit ausdrücklicher Freigabe zuerst der reale lokale Bestand `db` und die aktuelle Anwendung auf neue temporäre Ziele restauriert und mit unveränderten Originalzeilen verlustfrei migriert. Erst nach erfolgreicher kompletter Migrationssuite und zusätzlicher Freigabe wurden die 24 ausstehenden Migrationen auf lokalem `db` angewendet. Der Prod-Cache wurde neu aufgebaut; die ignorierte lokale Prod-Konfiguration verbindet jetzt ausdrücklich Dienst `db` und Datenbank `db`, deaktiviert Debug/Installation und markiert die Installation als abgeschlossen. Die Testverbindung bleibt auf dem separaten Dienst `db_test`.

Das aufbewahrte Vorher-Backup liegt lokal außerhalb Git unter:

```text
/home/diversworld/.copilot/session-state/7ba4614b-3b41-43f7-983c-f277e378a7fc/files/phase2-before-local-prod-20261024/
```

Es enthält `database.sql`, `application.tar` und `SHA256SUMS`. Verzeichnisrechte 0700, Backupdateien 0600. Das Archiv enthält lokale Secrets und personenbezogene Daten: nicht veröffentlichen oder in Git übernehmen. Integrität vor Benutzung mit `sha256sum -c SHA256SUMS` prüfen. Dieses Vorher-Abbild entspricht dem damaligen tatsächlichen Arbeitsstand einschließlich der damals inkonsistenten Prod-Konfiguration/Cache-Annahmen; es wird nicht als separat freigegebenes älteres lauffähiges Release ausgegeben.

Für zukünftige freigegebene lokale historische Snapshots:

```bash
DICOMA_PHASE2_BACKUP_DIRECTORY=/secure/new-local-backup-directory \
  bash scripts/phase2_target_snapshot_check.sh --confirm-local-db-snapshot --keep-backup
```

Das Zielverzeichnis muss neu und absolut sein. Der Skriptlauf liest ausschließlich Dienst `db`, prüft InnoDB, archiviert vorhandene Anwendung/Abhängigkeiten/Konfiguration/Dateien, restauriert auf neuem `dicoma_snapshot_test_*` in `db_test` sowie neuer privater Anwendungskopie und führt nur dort Migrationen aus. Ziel-Events dürfen nicht aktiv ausgeführt werden. Ohne `--keep-backup` werden auch die temporären Archive entfernt; alle eigens erzeugten temporären Datenbanken/Anwendungskopien werden gezielt bereinigt. Das Skript führt keine Migration auf der Quelldatenbank aus.

Der Snapshotprüfer unterstützt sowohl historische Bestände ohne `person` als auch bereits normalisierte Ausgangsbestände. Bei historischen Beständen prüft er zusätzlich jedes extrahierte Personenfeld; bei normalisierten Beständen vergleicht er sämtliche ursprünglichen Spalten aller Anwendungstabellen einschließlich `person`, Mitgliedern und Konten. Ein erneuter Lauf auf dem aktuellen Bestand ist ein Nachweis des aktuellen additiven Upgrades, nicht eine Wiederholung der historischen Personenmigration. Für einen nachträglichen historischen Nachweis zuerst das Vorher-Backup auf einen **neuen separaten Dienst** wiederherstellen und die Verbindung im Prüfskript dafür bewusst anpassen; niemals `db` mit dem alten Dump überschreiben, um einen Test erneut grün zu bekommen.

Beide Personenmigrationen verweigern automatisches `down()`. Ein Rückfall erfordert entweder eine eigens geprüfte Reverse-Datenmigration oder die Wiederherstellung des zusammengehörigen Datenbank-, Datei- und Anwendungssnapshots. Alten Code nicht gegen das normalisierte Schema starten. Während der Wiederherstellung müssen Schreibzugriffe weiterhin gesperrt bleiben.

Vor Freigabe mindestens Anzahlen und IDs, Personenfelder einschließlich Unicode/NULL/führender PLZ-Nullen, Kontozuordnungen, Kurs-/Mannschaftsreferenzen, Dokumentdownloads und Anmeldung prüfen. Wiederherstellungsdauer und Datenverlustfenster messen und vom Betreiber abnehmen lassen.

## Technisches Basis-Gate

```bash
ddev exec bash scripts/phase2_acceptance_gate.sh
```

Ohne DDEV mit gleichwertiger PHP-Umgebung:

```bash
bash scripts/phase2_acceptance_gate.sh
```

Das Basis-Gate führt Unit- und isolierte SQLite-Tests aus. Es schreibt keine Datensätze in die konfigurierte Anwendungsdatenbank, erstellt keine Staging-Fixtures und führt keine Migration aus.

## Optionale Staging-Abnahme

Vor dem folgenden Aufruf die Umgebungswerte aus [HTTP-Abnahme](../PHASE2-HTTP-ABNAHME.md) und alle weiteren von der vorhandenen HTTP-Suite geforderten Fixtures konfigurieren:

```bash
APP_ENV=test \
DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED=1 \
DICOMA_TEST_EXPECTED_DATABASE=db_test \
DICOMA_RUN_STAGING_DB_CHECKS=1 \
bash scripts/phase2_acceptance_gate.sh
```

Zusätzlich `DICOMA_RUN_STAGING_HTTP_TESTS=1` und `DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED=1` setzen, wenn die komplette HTTP-Suite einschließlich Schreibtests auf ausdrücklich dafür vorgesehenen Wegwerfdatensätzen laufen soll. Fehlende Fixtures führen durch `--fail-on-skipped` zum Fehlschlag, nicht zu einer Freigabe.

Die vorhandene Doctrine-Testkonfiguration hängt `_test` an den Datenbanknamen an. Dienstname, Basisname in `DATABASE_URL` und tatsächlicher Datenbankname sind nicht dasselbe. Bei DDEV kann beispielsweise Dienst `db_test` mit Basisname `db` zum tatsächlichen Namen `db_test` führen. Den tatsächlichen Wert kontrolliert:

```bash
APP_ENV=test php bin/console app:test-database:check --expected=db_test --env=test
```

Die Prüfung ist lesend und verweigert unbekannte Namen, falsche Umgebungen und eine nicht übereinstimmende reale Verbindung. Sie ersetzt keine getrennten Datenbankbenutzer, Server und Backups.

## Reproduzierbare lokale Backup-/Restore-Probe

Nur nach ausdrücklicher Testfreigabe, ohne gleichzeitige Schreibzugriffe auf `db_test`:

```bash
docker exec -i ddev-dicoma-db_test \
  env DICOMA_PHASE2_RESTORE_TEST_CONFIRMED=1 bash -s \
  < scripts/phase2_backup_restore_check.sh
```

Der Containername gehört zum lokalen DDEV-Projekt `dicoma`. Das Skript prüft die Dienstkonfiguration und den tatsächlichen Quelldatenbanknamen. Ein eingeschalteter Event-Scheduler führt zum Abbruch, damit wiederhergestellte Events nicht selbsttätig laufen. Es erstellt einen zugriffsbeschränkten Dump einschließlich Routinen, Triggern und Events, berechnet SHA-256, stellt ihn in einer zufällig benannten neuen Datenbank auf demselben Testdienst wieder her und vergleicht den erneuten Schema-/Daten-Dump bytegenau. Es überschreibt `db_test` nicht. Dumpdateien und die eigens erzeugte Restore-Datenbank werden auch bei Fehlern gezielt entfernt; Bereinigungsfehler führen zum Fehlschlag.

Das ist ein Wiederherstellungsnachweis für den lokalen Testbestand, **nicht** für Produktivdaten, private Dokumentdateien, Konfiguration oder die Wiederanlaufzeit des vollständigen Systems. Die produktionsnahe Snapshot-/Datei-Wiederherstellung aus der Vorbereitung bleibt separat erforderlich. Das Skript niemals gegen den normalen `db`-Dienst oder Produktion ausführen.

## Sicherheitsrelevante Betriebsbeobachtung

Fehlerhafte Anmeldung/Verifizierung, unzulässige Verwaltungszugriffe, Installationsversuche und fehlgeschlagene Konfigurations-/Datenbankzugriffe in den vorhandenen Symfony-/Monolog-Protokollen überwachen. Protokolle restriktiv berechtigen und rotieren; niemals Passwörter, Reset-/Verifizierungstokens oder vollständige Verbindungsstrings protokollieren. Eine zentrale, revisionssichere Audit-/Alarmierungsplattform ist durch diese Phase nicht eingerichtet.
