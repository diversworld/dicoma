# Phase 2 – Architektur und technische Stabilisierung

Stand: 24.10.2026. Dieser Bericht ersetzt den veralteten Grundstandsbericht vom 15.10.2026. Maßgeblich ist das vorhandene Symfony-/Doctrine-/EasyAdmin-Projekt, kein neues Framework und keine PostgreSQL-Umstellung.

## Bestätigte Entscheidungen

Der Auftraggeber hat in dieser Umsetzung ausdrücklich freigegeben:

1. Eine zentrale `Person`, getrennt von Benutzerkonto und Vereinsmitgliedschaft.
2. Vereinsgebundene `ROLE_ADMIN`-Rechte; `ROLE_SUPER_ADMIN` bleibt vereinsübergreifend.
3. `ROLE_MEMBER` nur bei aktiver Mitgliedschaft mit Vereinszuordnung; separat vergebene Funktionsrollen bleiben bestehen.
4. Verlustfreie Übernahme der bisherigen Personenfelder mit anschließender Entfernung der überprüften Altspalten. Ausführung ausschließlich in isolierten Testdatenbanken, nicht produktiv.

## Implementiert

| Bereich | Änderung |
|---|---|
| Personenmodell | `Person` enthält Namen, Geburtstag, Kontakt und Anschrift. `Member` hält Vereinszuordnung, Mitgliedsnummer, Status und Fachbeziehungen. Bestehende Member-Getter/-Setter delegieren an dieselbe Person. |
| Benutzerkonto | `User.person` ist unabhängig von `User.member`. Bidirektionale Zuordnungen werden beim Wechsel/Trennen synchronisiert. Konto- und Mitgliedschaftslöschung löschen nicht mehr gegenseitig die Identität. |
| Gäste | Öffentliche Registrierung erzeugt Konto und Person, keine aktive Mitgliedschaft und keine Funktionsrolle. Personen und Gäste sind ausschließlich für Superadmins zentral verwaltbar. |
| Aufnahme | In der Personenverwaltung führt „Mitgliedschaft anlegen“ in das vorhandene Mitgliederformular. Es verwendet dieselbe Person und übernimmt gegebenenfalls deren bisher mitgliedschaftsloses Konto. Doppelte Mitgliedschaften derselben Person im selben Verein werden validiert und durch einen DB-Constraint verhindert. |
| Rollen | `ROLE_USER` ist die Kontobasis. Gespeichertes `ROLE_MEMBER` umgeht die Mitgliedschaftsprüfung nicht. Funktionsrollen erben nicht länger automatisch Mitgliedsrechte; ihre Portalrouten sind separat abgesichert. |
| Vereinsgrenzen | Bestehende Voter/CRUD-Filter bleiben erhalten. Dashboard-Qualifikationen einschließlich Zähler werden nach Verein gefiltert. Globale Geräteübersicht, Sammelbuchung im Dashboard und die alten ungefilterten Member-/Student-/Instructor-Controller erfordern Superadmin. Vereinsadmins verwenden die vereinsgefilterte EasyAdmin-Mitgliederverwaltung. |
| Anmeldung | Signierte E-Mail-Bestätigung ist ohne vorheriges Login erreichbar. Gäste werden bei erneutem Login-Seitenaufruf nicht mehr in den geschützten Mitgliederkalender umgeleitet. |
| Abfragen/UI | Suchfelder, Standardnamenssortierung, Auswahllisten und relevante Repository-Abfragen verwenden `person`-Beziehungen. Kompatible Formularfeldnamen bleiben erhalten. |
| Migrationen | Zweistufige Extraktion/Normalisierung, exakter Feldvergleich vor Spaltenentfernung, keine automatische Zusammenführung gleichnamiger Personen. |
| Indexreparatur | Neue additive Migration `Version20261024100000` ergänzt den auf bereits normalisiertem `db_test` fehlenden Person-/Verein-Unique-Index. Korrekte vorhandene Indizes bleiben bestehen; Dubletten führen zum Abbruch ohne Datenbereinigung. |
| Stabilisierung | Fälschlich im Entity-Verzeichnis liegende zweite Deklaration von `TuvBookingOverviewController` entfernt; der tatsächlich geroutete Controller mit Superadmin-Schutz bleibt bestehen. |
| Abnahme | Linux-Pfad des Routengates korrigiert; getrennte Testgruppen, tatsächliche Testdatenbankprüfung, keine grünen Skip-Ergebnisse und kein Freigabenachweis allein durch einen Migrationsstatus. CI prüft die Grundlagen mit PHP 8.4/8.5. |

Die Symfony-Constraints der unmittelbar geprüften Registrierungs-/Passwortformulare verwenden die aktuelle API. Die E-Mail-Verifizierung verwendet die Request-basierte API; hierfür ist die Mindestanforderung des bereits vorhandenen Verify-Email-Bundles auf 1.17 angehoben, ohne Paketversionswechsel im Lockfile.

## Architektur und Betrieb

- [Systemübersicht](architecture/system-overview.md)
- [Datenmodell und Kompatibilität](architecture/data-model.md)
- [Rollenvertrag](architecture/roles-permissions.md)
- [Architekturentscheidungen](architecture/architecture-decisions.md)
- [Betrieb, Migration, Backup und Wiederherstellung](deployment/operations.md)
- [Prüfbericht](testing/test-report.md)
- [Freigabegates](../PHASE2-ABSCHLUSS-GATES.md)

## Grenzen und Abnahme

### Abschließende lokale technische Abnahme

Der unten beschriebene Zwischenstand wurde inzwischen erweitert: Nach ausdrücklicher Freigabe ist der tatsächliche lokale `db`-Bestand gesichert, auf neuer isolierter Datenbank samt Anwendung/Dateien wiederhergestellt und mit **24 Migrationen** verlustfrei aktualisiert worden. Originalzeilen aller **56 Anwendungstabellen**, Personenfelder und Kontozuordnungen sind auf der Kopie geprüft. Anschließend wurde mit gesonderter Freigabe auch lokales `db` migriert, die Prod-Verbindung korrigiert, der Prod-Cache erneuert und ein persistenter Installationsabschlussmarker angelegt.

Finales Gate: **97 Tests, 1.191 Assertions**; vollständige Migrationssuite: **14 Tests, 112 Assertions**. Reale HTTPS-Prüfungen: Login 200, Installation 404. Die technische **lokale** Phase-2-Abnahme ist abgeschlossen; der Beginn der Phase-3-Entwicklung wurde ausdrücklich freigegeben.

Ein geschütztes Vorher-Backup bleibt außerhalb des Repositorys erhalten. Keine externen Systeme wurden verändert. Der reale Ausgangsbestand enthält keine privaten Dokumente, Kurse oder Ausrüstungsbuchungen; zusätzliche Dokumenttests verwenden ausdrücklich synthetische Fixtures. Eine externe Betriebsfreigabe und die vollständige fachliche Regression aller anderen Module bleiben davon getrennt.

### Früherer Zwischenstand vor Zielbestandsfreigabe

Das finale kombinierte Gate ist auf DDEV erfolgreich: **65 Basis-/isolierte Tests mit 204 Assertions** und **30 Staging-HTTP-Tests mit 889 Assertions**, ohne übersprungene oder unvollständige Tests. `db_test` ist bestätigt, Mapping und Schema sind synchron, alle Migrationen einschließlich Indexreparatur sind angewendet.

Die erweiterte Migrationssuite prüfte 12 Fälle. Elf bestanden im Gesamtlauf; der historische Rollbacktest musste um die neue absichtlich irreversible Migration ergänzt werden und bestand anschließend im gezielten Wiederholungslauf (**1 Test, 6 Assertions**). Die genaue Aufschlüsselung und der anfängliche Fehler stehen im Prüfbericht.

Eine lokale Datenbank-Backup-/Restore-Probe ist erfolgt: **70 Tabellen**, byteidentischer Schema-/Daten-Dump, Restore in 8 Sekunden in eine neue temporäre Datenbank. Die Quelldatenbank wurde nicht überschrieben; temporäre Datenbanken und Dumpdateien wurden bereinigt. Die Rollenbeschränkungen und isolierten Testschritte wurden am 24.10.2026 ausdrücklich freigegeben.

Diese lokalen Nachweise sind **keine vollständige Phase-2-Abnahme oder Produktivfreigabe**. Weiterhin fehlen ein freigegebener produktionsnaher Zielbestand, dessen Schema-/Migrations-/Datenvergleich und die vollständige Wiederherstellung einschließlich Dateien sowie fachliche Regression der Dokument-, Kassen-, Ausrüstungs- und Ausbildungsprozesse. Ein eingefrorenes Release und eine betriebliche Freigabe sind noch nicht dokumentiert. Deshalb wurde Phase 3 entsprechend der Bedingung des Auftrags noch nicht begonnen.

Die spätere Kursanmeldung einer Person ohne `Member` sowie Ausbildungs-/Veranstaltungs-/Reisebeziehungen direkt zu Personen sind **nicht** durch die neue Personen-Entity automatisch fertiggestellt. Bestehende Kurs-, Mannschafts-, Kassen- und Dokumentbeziehungen bleiben bewusst an ihren bisherigen Fachobjekten erhalten. Die entsprechenden Folgephasen müssen diese Abläufe integrieren und testen.

Im früheren Zwischenstand wurde keine produktive Datenbank verändert. Die anschließend ausdrücklich freigegebene Aktualisierung betrifft ausschließlich das oben beschriebene lokale `db`; kein externer Produktivserver, kein Commit und kein Deployment.
