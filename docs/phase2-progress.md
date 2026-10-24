# Phase 2 – Architektur und technische Stabilisierung

Stand: 23.10.2026. Dieser Bericht ersetzt den veralteten Grundstandsbericht vom 15.10.2026. Maßgeblich ist das vorhandene Symfony-/Doctrine-/EasyAdmin-Projekt, kein neues Framework und keine PostgreSQL-Umstellung.

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

Das finale technische Basis-Gate ist erfolgreich: **65 Tests mit 204 Assertions**. Die vollständige Migrationssuite ist ebenfalls erfolgreich: **10 Tests mit 90 Assertions**, einschließlich frischer Installation, historischer Upgrade-/Rollbackfälle und Personenübernahme.

Ein erfolgreiches technisches Basis-Gate ist **keine vollständige Phase-2-Abnahme oder Produktivfreigabe**. Die vollständige vorhandene Staging-HTTP-Suite mit Test-Fixtures und schreibenden Negativtests, ein realer Backup-/Restore-Versuch, die betriebliche Freigabe der Rollenänderung und die Prüfung des tatsächlichen Zielschemas bleiben erforderlich.

Die spätere Kursanmeldung einer Person ohne `Member` sowie Ausbildungs-/Veranstaltungs-/Reisebeziehungen direkt zu Personen sind **nicht** durch die neue Personen-Entity automatisch fertiggestellt. Bestehende Kurs-, Mannschafts-, Kassen- und Dokumentbeziehungen bleiben bewusst an ihren bisherigen Fachobjekten erhalten. Die entsprechenden Folgephasen müssen diese Abläufe integrieren und testen.

Keine produktive Datenbank wurde migriert oder bereinigt. Es wurde kein Commit und kein Deployment ausgeführt.
