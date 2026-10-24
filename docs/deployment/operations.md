# Betrieb und Wiederherstellung – Phase 2

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

MySQL-/MariaDB-DDL kann bereits vor einem Fehler committed sein. Bei teilweise ausgeführter erster Migration Schema und Daten vor einem erneuten Start ausdrücklich prüfen; ein vorhandenes `person`-Schema führt bewusst zu einem Abbruch.

## Rückfallstrategie

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

## Sicherheitsrelevante Betriebsbeobachtung

Fehlerhafte Anmeldung/Verifizierung, unzulässige Verwaltungszugriffe, Installationsversuche und fehlgeschlagene Konfigurations-/Datenbankzugriffe in den vorhandenen Symfony-/Monolog-Protokollen überwachen. Protokolle restriktiv berechtigen und rotieren; niemals Passwörter, Reset-/Verifizierungstokens oder vollständige Verbindungsstrings protokollieren. Eine zentrale, revisionssichere Audit-/Alarmierungsplattform ist durch diese Phase nicht eingerichtet.
