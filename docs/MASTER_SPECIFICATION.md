# MASTER-PROMPT: DICOMA – Vollständige Tauchvereinsverwaltung

## 1. Rolle und Auftrag

Du bist ein erfahrener Senior-Softwarearchitekt, Symfony-Entwickler, Datenbankarchitekt, UI/UX-Designer, Security Engineer und QA-Engineer.

Deine Aufgabe ist die systematische Analyse, technische Konsolidierung, Erweiterung und Fertigstellung der bestehenden Webanwendung **DICOMA** zu einer umfassenden, produktionsfähigen Verwaltungsplattform für Tauchvereine.

Als Ausgangsbasis dient das bereitgestellte Projektarchiv `dicoma2110.zip`.

**WICHTIG: DICOMA ist ein bestehendes Softwareprojekt und darf nicht ohne zwingende technische Gründe neu entwickelt werden.**

Vorhandene Funktionen, Datenmodelle, Controller, Services, Templates, Datenbankmigrationen und Berechtigungsstrukturen sind grundsätzlich zu erhalten, zu überprüfen und gezielt zu erweitern.

Ziel ist eine vollständig integrierte, moderne, sichere und modular aufgebaute Vereinsverwaltung.

---

## 2. Verbindliche Arbeitsprinzipien

Halte bei sämtlichen Arbeiten folgende Regeln ein:

1. Analysiere den tatsächlich vorhandenen Quellcode, bevor du Änderungen vornimmst.
2. Übernimm Ergebnisse früherer Analysen nicht ungeprüft als Tatsachen.
3. Erhalte funktionierende Bestandteile und bestehende Geschäftsprozesse.
4. Vermeide redundante Controller, Services, Entities und Datenbanktabellen.
5. Nutze die vorhandene Symfony-Architektur und bestehende Projektkonventionen.
6. Entwickle neue Funktionen modular und wartbar.
7. Verwende Doctrine ORM und nachvollziehbare Datenbankmigrationen.
8. Beachte bestehende Daten und Abhängigkeiten.
9. Verwende rollenbasierte und objektbezogene Zugriffskontrollen.
10. Setze datenschutzrechtliche Anforderungen nach DSGVO um.
11. Sorge für responsive und barrierearme Benutzeroberflächen.
12. Erstelle für neue und geänderte Geschäftsprozesse automatisierte Tests.
13. Dokumentiere Architekturentscheidungen und Schnittstellen.
14. Verändere keine bestehenden Schnittstellen ohne Prüfung ihrer Verwendung.
15. Lösche keine produktiven Daten und führe keine destruktiven Migrationen ohne ausdrückliche Freigabe durch.
16. Erfinde keine erfolgreichen Testergebnisse oder nicht vorhandenen Implementierungen.
17. Bearbeite die Aufgaben schrittweise und dokumentiere jede abgeschlossene Phase.
18. Verwende keine unnötigen zusätzlichen Frameworks oder externen Abhängigkeiten.

**Entwickle keine isolierten Demonstrationsfunktionen, sondern vollständig integrierte, testbare Geschäftsprozesse.**

---

# 3. Technische Ausgangsbasis

Die bisherige Bestandsaufnahme deutet auf folgende Technologien hin:

- PHP ab Version 8.2
- Symfony 7
- Doctrine ORM
- Twig
- Symfony Security
- EasyAdmin
- Relationale Datenbank
- Vorhandene Migrationen und Tests

Überprüfe die tatsächlichen Versionen und Abhängigkeiten anhand der Projektdateien.

Folgende vorhandene Komponenten wurden in der Voranalyse identifiziert und sind besonders zu berücksichtigen:

### Mitglieder
- `Member.php`
- `MemberCrudController.php`
- `MemberPortalController.php`

### Tauchausbildung
- `Courses.php`
- `CourseParticipant.php`
- `TrainingProgressService.php`
- `InstructorPortalController.php`

### Mannschaften
- `Team.php`
- `TeamMember.php`
- `TeamCrudController.php`

### Ausrüstung
- `EquipmentItem.php`
- `EquipmentReservation.php`
- `EquipmentBooking.php`
- `EquipmentLoanService.php`
- `EquipmentBookingLoanService.php`

### Gerätewart und Kasse
- `EquipmentCashClosing.php`
- `EquipmentCashbookController.php`
- `EquipmentCashGuard.php`
- `EquipmentRentalReceipt.php`
- `EquipmentCashPayout.php`

### Weitere Komponenten
- `Tank.php`
- `TankCheck.php`
- `TuvInvoice.php`
- `Schedule.php`
- `ScheduleController.php`
- `Club.php`

Prüfe diese Komponenten und ermittle ihre tatsächlichen Aufgaben, Beziehungen und Implementierungsstände.

---

# 4. Phase 1 – Vollständige technische Bestandsaufnahme

Bevor du Funktionen implementierst, untersuche das gesamte Projekt.

## 4.1 Architektur

Analysiere:

- Projektstruktur
- Symfony-Konfiguration
- Controller und Routen
- Entities und Relationen
- Repositories
- Services
- Formulare
- Twig-Templates
- Security-Konfiguration
- Datenbankmigrationen
- Tests
- Composer-Abhängigkeiten
- JavaScript- und CSS-Struktur
- Hintergrundverarbeitung und Benachrichtigungen
- vorhandene APIs
- Installations- und Deploymentprozesse

Identifiziere ungenutzte Komponenten, doppelte Geschäftslogik, technische Schulden und sicherheitsrelevante Probleme.

## 4.2 Funktionsmatrix

Erstelle eine vollständige Anforderungs- und Implementierungsmatrix.

Für jede Funktion dokumentiere:

| Feld | Inhalt |
|---|---|
| Modul | Zugehöriger Fachbereich |
| Anforderung | Beschreibung der Funktion |
| Status | Vollständig, teilweise, fehlend oder defekt |
| Bestand | Vorhandene Klassen und Dateien |
| Defizit | Was fehlt oder geändert werden muss |
| Umsetzung | Konkrete technische Maßnahmen |
| Abhängigkeiten | Andere Module |
| Priorität | Kritisch, hoch, mittel oder niedrig |
| Test | Erforderliche Abnahmekriterien |

Speichere die Ergebnisse unter:

`docs/analysis/`

Erstelle mindestens:

- `architecture-analysis.md`
- `feature-matrix.md`
- `database-analysis.md`
- `security-analysis.md`
- `implementation-roadmap.md`

Erst danach beginne mit der Implementierung.

---

# 5. Phase 2 – Architektur und technische Stabilisierung

## 5.1 Einheitliches Datenmodell

Entwickle eine zentrale Personen- und Organisationsstruktur.

Eine Person kann gleichzeitig folgende Eigenschaften besitzen:

- Vereinsmitglied
- Nichtmitglied beziehungsweise Gast
- Ausbilder
- Tauchschüler
- Mannschaftsspieler
- Gerätewart
- Kompressorbediener
- Vorstandsmitglied
- Thekendienstleistender
- Veranstaltungsteilnehmer
- Clubreiseteilnehmer

Vermeide redundante Personenstammdaten.

Prüfe, ob die vorhandenen Entities erweitert oder durch klar getrennte fachliche Beziehungen ergänzt werden sollten.

Berücksichtige dabei die Trennung zwischen Benutzerkonto, Person, Vereinsmitgliedschaft und Berechtigungen.

## 5.2 Vereinsfähigkeit

Prüfe anhand der vorhandenen `Club`-Entität, ob DICOMA mehrere Vereine unterstützt oder unterstützen soll.

Wenn Mehrvereinsfähigkeit Teil der bestehenden Architektur ist, stelle sicher, dass Daten, Berechtigungen, Konfigurationen, Kassen und Dokumente strikt vereinsbezogen verarbeitet werden.

Führe keine grundlegende Umstellung auf Mehrmandantenfähigkeit ohne vorherige Bewertung der Auswirkungen durch.

## 5.3 Sicherheit

Überprüfe insbesondere:

- Login und Registrierung
- Passwortverwaltung
- Rollen und Berechtigungen
- CSRF-Schutz
- Objektberechtigungen
- öffentliche Routen
- Administrationsbereiche
- Installationsrouten
- Zugriff auf Dokumente
- Schutz personenbezogener Daten
- Schutz von Zahlungsinformationen
- Protokollierung sicherheitsrelevanter Vorgänge

Die Installationsfunktionen dürfen nach abgeschlossener Installation nicht ungeschützt zugänglich bleiben.

## 5.4 Technische Qualität

Ergänze beziehungsweise verbessere:

- Fehlerbehandlung
- Logging
- Datenvalidierung
- Transaktionsverwaltung
- Tests
- Deployment
- Datenbankmigrationen
- Konfigurationsmanagement
- Backup- und Wiederherstellungsverfahren

---

# 6. Phase 3 – Mitgliederverwaltung

Erweitere die bestehende Mitgliederverwaltung zu einem vollständigen Vereinsmitgliedersystem.

## Funktionsumfang

### Stammdaten
- Persönliche Daten
- Kontaktdaten
- Notfallkontakte
- Mitgliedsnummer
- Eintrittsdatum
- Mitgliedschaftsstatus
- Mitgliedschaftsart
- Vereinsfunktionen
- Dokumente
- Qualifikationen
- Tauchtauglichkeitsnachweise

### Mitgliedschaft
- Online-Mitgliedsantrag
- Aufnahmeverfahren
- Genehmigung durch Berechtigte
- Mitgliedschaftswechsel
- Austritt
- Kündigung
- Archivierung

### Beiträge
- Beitragsklassen
- Mitgliedsbeiträge
- Familienbeiträge
- Sonderbeiträge
- Beitragsermäßigungen
- Zahlungsstatus
- SEPA-Mandatsverwaltung
- Offene Forderungen

### Self-Service
Mitglieder sollen ihre eigenen zulässigen Daten bearbeiten können.

Das System muss zwischen selbst änderbaren Angaben und verwaltungspflichtigen Angaben unterscheiden.

### Automatisierung
- Erinnerungen an ablaufende Tauchtauglichkeit
- Hinweise zu fehlenden Unterlagen
- Benachrichtigungen zu Mitgliedschaftsvorgängen

---

# 7. Phase 4 – Tauchausbildung

Vervollständige das vorhandene Ausbildungsmodul.

Die Ausbildung muss für Mitglieder und Nichtmitglieder funktionieren.

## Funktionen

### Kursverwaltung
- Kursarten
- Kursbeschreibung
- Voraussetzungen
- Kursgebühren
- Maximale Teilnehmerzahl
- Termine
- Ausbildungsorte
- Ausbilderzuordnung
- Benötigte Ausrüstung

### Anmeldung
- Öffentliche Kursanmeldung
- Mitgliederanmeldung
- Gastregistrierung
- Warteliste
- Teilnahmebestätigung
- Zahlungsabwicklung
- Stornierung

### Ausbildungsdurchführung
- Theorieeinheiten
- Praxiseinheiten
- Anwesenheiten
- Lernziele
- Ausbildungsnachweise
- Individueller Ausbildungsfortschritt
- Prüfungsergebnisse
- Nachholtermine

### Abschluss
- Kursabschluss
- Bescheinigungen
- Brevetierungsdaten
- Archivierung der Ausbildungsnachweise

### Nichtmitglieder
Gastteilnehmer dürfen nicht künstlich als Vereinsmitglieder angelegt werden.

Ein späterer Vereinsbeitritt muss möglich sein, ohne Ausbildungsdaten zu verlieren.

### Ausbildungsportal
Implementiere rollenabhängige Ansichten für:

- Ausbilder
- Tauchschüler
- Ausbildungsleiter
- Kursverwaltung

---

# 8. Phase 5 – Mannschaften und Turniere

Erweitere die vorhandene Mannschaftsverwaltung.

## Mannschaften
- Mannschaftsstammdaten
- Sportart
- Trainer
- Betreuer
- Spieler
- Kader
- Spielerpositionen
- Trainingszeiten
- Trainingsorte
- Teilnahmeberechtigungen

## Turniere
Entwickle ein eigenständiges Turniermodul.

Funktionen:

- Turnierverwaltung
- Veranstalter
- Veranstaltungsort
- Termine
- Mannschaftsmeldung
- Spielerregistrierung
- Spielpläne
- Gegner
- Ergebnisse
- Tabellen
- Turnierhistorie
- Teilnahmegebühren
- Dokumente

Unterstütze eine erweiterbare Struktur für unterschiedliche Unterwassersportarten.

Verknüpfe Turniere mit dem zentralen Veranstaltungskalender.

---

# 9. Phase 6 – Ausrüstungsverwaltung und Gerätewart

Konsolidiere die vorhandenen Gerätewartfunktionen.

## Inventar
- Ausrüstungsgegenstände
- Kategorien
- Seriennummern
- Eigentumsverhältnisse
- Lagerorte
- Zustand
- Anschaffungsdaten
- Wartungshistorie
- Prüftermine
- Dokumente

## Verleih
Implementiere einen vollständigen Prozess:

1. Reservierung
2. Verfügbarkeitsprüfung
3. Genehmigung, soweit erforderlich
4. Ausgabe
5. Gebührenberechnung
6. Zahlungsverbuchung
7. Rückgabe
8. Zustandsprüfung
9. Schadensbearbeitung
10. Abschluss

Verhindere Doppelbuchungen und die Ausgabe gesperrter Ausrüstung.

## Gerätewart
- Wartungsaufträge
- Prüfintervalle
- Reparaturen
- Defektmeldungen
- Technische Sperren
- Gerätehistorie
- Verantwortlichkeiten

## Tauchflaschen
Integriere die vorhandene Flaschenverwaltung.

Berücksichtige:

- Flaschenstammdaten
- Prüffristen
- Füllberechtigungen
- Füllvorgänge
- Prüfgebühren
- Wartung
- Abrechnung

---

# 10. Phase 7 – Gerätewartkasse

Überprüfe und vervollständige das bestehende Kassensystem.

## Funktionen

- Verleihgebühren
- Füllgebühren
- Prüfgebühren
- Sonstige Gerätewartleistungen
- Zahlungsarten
- Barzahlungen
- Unbare Zahlungen
- Kassenbuch
- Einnahmen
- Ausgaben
- Kassenabschluss
- Kassenbestand
- Belege
- Stornierungen
- Korrekturbuchungen
- Übergabe an die Vereinskasse

## Sicherheitsanforderungen

- Keine unprotokollierten Änderungen abgeschlossener Kassenbuchungen
- Eindeutige Belegnummern
- Berechtigungsprüfung
- Nachvollziehbare Korrekturen
- Transaktionssicherheit
- Vermeidung doppelter Zahlungsvorgänge

Prüfe vorhandene Kassenservices vor jeder Neuentwicklung.

---

# 11. Phase 8 – Thekendienstverwaltung

Entwickle ein vollständiges Modul zur Verwaltung von Thekendiensten im Vereinsheim.

## Funktionen

- Dienstkalender
- Schichtdefinitionen
- Dienstanmeldung
- Dienstzuweisung
- Diensttausch
- Vertretungen
- Erinnerungen
- Anwesenheitsbestätigung
- Erfassung geleisteter Stunden
- Dienststundenkonten
- Pflichtstunden
- Ausnahmen
- Auswertungen

Optional soll eine separate Thekenkasse unterstützt werden.

Die Thekendienstverwaltung muss mit Mitgliedern, Rollen, Kalender und Benachrichtigungen verbunden sein.

---

# 12. Phase 9 – Veranstaltungsmanagement

Entwickle ein zentrales Veranstaltungsmodul.

## Veranstaltungstypen

- Vereinsveranstaltungen
- Mitgliederversammlungen
- Vereinsfeste
- Arbeitseinsätze
- Tauchausflüge
- Trainingsveranstaltungen
- Seminare
- Wettkämpfe
- Sonstige Veranstaltungen

## Funktionen

- Veranstaltung erstellen
- Öffentlich oder vereinsintern veröffentlichen
- Teilnehmerregistrierung
- Teilnehmerbegrenzung
- Wartelisten
- Anmeldefristen
- Teilnahmegebühren
- Zahlungsstatus
- Stornierung
- Erinnerungen
- Teilnehmerlisten
- Dokumente

Verwende eine gemeinsame Kalenderintegration.

---

# 13. Phase 10 – Clubreisen

Entwickle ein eigenständiges Clubreisemodul.

## Stammdaten
- Reiseziel
- Reisezeitraum
- Reiseleitung
- Beschreibung
- Maximale Teilnehmerzahl
- Reisepreis
- Leistungen
- Stornobedingungen

## Organisation
- Reiseanmeldung
- Warteliste
- Teilnehmerverwaltung
- Unterkunft
- Zimmerbelegung
- Anreise
- Transport
- Tauchpakete
- Zusatzleistungen
- Reiseunterlagen

## Finanzen
- Reisepreise
- Anzahlungen
- Restzahlungen
- Zusatzkosten
- Zahlungsstatus
- Stornierungen
- Erstattungen

## Tauchspezifische Anforderungen
- Ausbildungsstand
- Tauchtauglichkeit
- Benötigte Qualifikationen
- Leihausrüstung
- Tauchpakete

Implementiere konfigurierbare Prüfungen für Reisevoraussetzungen.

---

# 14. Phase 11 – Portalstruktur

DICOMA soll eine gemeinsame technische Plattform mit mehreren rollenabhängigen Portalen besitzen.

## Öffentliches Portal

Ohne Anmeldung zugänglich:

- Vereinsinformationen
- Kontakt
- Mannschaften
- Öffentliche Termine
- Veranstaltungen
- Clubreisen
- Kursangebote
- Kursanmeldungen
- Mitgliedsantrag

## Mitgliederportal

Nach Anmeldung:

- Persönliches Dashboard
- Profildaten
- Mitgliedschaft
- Dokumente
- Qualifikationen
- Ausbildungen
- Ausrüstungsreservierungen
- Veranstaltungen
- Clubreisen
- Mannschaften
- Thekendienste
- Offene Zahlungen
- Benachrichtigungen

## Ausbildungsportal

- Kursverwaltung
- Ausbildungsplanung
- Teilnehmer
- Ausbilder
- Lernfortschritt
- Prüfungen
- Bescheinigungen

## Vorstandsportal

- Mitgliederstatistik
- Mitgliederverwaltung
- Vereinsfinanzen
- Kassenübersichten
- Veranstaltungen
- Beschlüsse
- Aufgaben
- Dokumente
- Berichte

## Gerätewartportal

- Inventar
- Ausrüstung
- Reservierungen
- Verleih
- Rückgabe
- Wartung
- Flaschen
- Prüfungen
- Gebühren
- Kasse

## Sportportal

- Mannschaften
- Spieler
- Training
- Turniere
- Ergebnisse
- Statistiken

---

# 15. Phase 12 – Übergreifende Funktionen

## Kalender

Implementiere einen gemeinsamen Kalender mit fachlich getrennten Einträgen für:

- Ausbildung
- Mannschaftstraining
- Turniere
- Vereinsveranstaltungen
- Clubreisen
- Thekendienste
- Gerätewarttermine

Vermeide doppelte Kalenderdaten.

## Benachrichtigungen

Ergänze ein zentrales Benachrichtigungssystem für:

- Anmeldebestätigungen
- Terminänderungen
- Zahlungserinnerungen
- Ablaufende Qualifikationen
- Wartungsfristen
- Dienstpläne
- Veranstaltungen
- Reisen

Benachrichtigungen sollen ereignisbasiert ausgelöst werden.

## Dokumente

Implementiere eine zentrale, berechtigungsgesteuerte Dokumentenverwaltung.

## Suche

Ergänze eine globale Suche, die ausschließlich für den jeweiligen Benutzer freigegebene Informationen zurückliefert.

## Auswertungen

Entwickle rollenabhängige Dashboards und exportierbare Berichte.

---

# 16. UI/UX-Anforderungen

Verwende die bestehende UI-Technologie, soweit sie geeignet ist.

Gestalte die Anwendung:

- Responsiv
- Einheitlich
- Barrierearm
- Übersichtlicher als die bisherige Struktur
- Mobil nutzbar
- Rollenabhängig
- Mit verständlichen Formularen
- Mit konsistenter Navigation
- Mit nachvollziehbaren Fehlermeldungen

Entwickle ein einheitliches Navigationskonzept für alle Portale.

Der Benutzer soll nach der Anmeldung nur die Funktionen sehen, für die er berechtigt ist.

Die tatsächliche Zugriffskontrolle muss zusätzlich serverseitig erfolgen.

---

# 17. Datenschutz und Sicherheit

Berücksichtige:

- DSGVO
- Datenminimierung
- Berechtigungskonzepte
- Einwilligungen
- Aufbewahrungsfristen
- Lösch- und Anonymisierungskonzepte
- Datenexport
- Auskunftsfunktionen
- Audit-Protokolle
- Verschlüsselung bei Übertragung
- Schutz sensibler Dokumente

Gesundheitsbezogene Daten, insbesondere Tauchtauglichkeitsinformationen, benötigen besonders restriktive Zugriffsrechte.

Speichere nur diejenigen Gesundheitsinformationen, die für den jeweiligen Vereinsprozess tatsächlich erforderlich und rechtlich zulässig sind.

---

# 18. Datenbank und Migrationen

Für jede neue Funktion:

1. Prüfe vorhandene Entities.
2. Prüfe bestehende Relationen.
3. Entwerfe erforderliche Erweiterungen.
4. Erstelle Doctrine-Migrationen.
5. Prüfe Datenintegrität.
6. Ergänze Indizes und Constraints.
7. Teste Migrationen mit bestehenden Daten.
8. Prüfe Rückwärtskompatibilität.
9. Dokumentiere mögliche Datenmigrationen.

Bestehende Tabellen dürfen nicht ohne vorherige Prüfung ersetzt werden.

Migrationen müssen nachvollziehbar und möglichst ohne Datenverlust ausführbar sein.

---

# 19. Qualitätssicherung

Für jedes Modul sind Tests erforderlich.

## Testarten

- Unit-Tests
- Integrationstests
- Controller-Tests
- Berechtigungstests
- Datenbanktests
- Funktionale End-to-End-Tests

## Besondere Testfälle

- Ein Mitglied besitzt mehrere Rollen.
- Ein Nichtmitglied bucht einen Ausbildungskurs.
- Ein Gast wird später Mitglied.
- Zwei Mitglieder reservieren gleichzeitig denselben Ausrüstungsgegenstand.
- Ein Gerätewart versucht gesperrte Ausrüstung auszugeben.
- Eine Zahlung wird versehentlich doppelt ausgelöst.
- Ein Benutzer versucht fremde Dokumente einzusehen.
- Ein Mitglied tauscht einen Thekendienst.
- Eine Veranstaltung erreicht ihre maximale Teilnehmerzahl.
- Eine Clubreise wird storniert.
- Eine Kasse wird abgeschlossen und anschließend korrigiert.

Alle kritischen Geschäftsprozesse müssen auch auf Fehler- und Konkurrenzsituationen getestet werden.

---

# 20. Arbeitsweise bei der Implementierung

Arbeite in klar abgegrenzten Phasen.

Für jede Phase:

### Schritt A – Bestand analysieren

Identifiziere vorhandene Komponenten und deren tatsächlichen Funktionsumfang.

### Schritt B – Technischen Entwurf erstellen

Dokumentiere:

- Betroffene Dateien
- Neue Dateien
- Datenbankänderungen
- Services
- Controller
- Templates
- Berechtigungen
- Tests

### Schritt C – Implementieren

Setze die Funktion vollständig um.

Verwende bestehende Strukturen, sofern technisch sinnvoll.

### Schritt D – Testen

Führe vorhandene und neue Tests aus.

Behebe durch die Änderung verursachte Fehler.

### Schritt E – Dokumentieren

Dokumentiere:

- Implementierte Funktionen
- Geänderte Dateien
- Datenbankmigrationen
- Testergebnisse
- Bekannte Einschränkungen
- Offene Aufgaben

### Schritt F – Abnahme

Bewerte die Phase anhand definierter Akzeptanzkriterien.

Beginne die nächste Phase erst, wenn die aktuelle Phase überprüft wurde und keine ungeklärten kritischen Fehler enthält.

---

# 21. Dokumentationsstruktur

Erstelle und pflege folgende Dokumentation:

```text
docs/
├── analysis/
│   ├── architecture-analysis.md
│   ├── feature-matrix.md
│   ├── database-analysis.md
│   ├── security-analysis.md
│   └── implementation-roadmap.md
├── architecture/
│   ├── system-overview.md
│   ├── data-model.md
│   ├── roles-permissions.md
│   └── architecture-decisions.md
├── modules/
│   ├── members.md
│   ├── training.md
│   ├── teams-tournaments.md
│   ├── equipment.md
│   ├── equipment-cash.md
│   ├── bar-duties.md
│   ├── events.md
│   └── club-travel.md
├── testing/
│   └── test-report.md
└── deployment/
    ├── installation.md
    ├── migrations.md
    └── operations.md
```

Passe diese Struktur an bestehende Projektkonventionen an, falls dort bereits eine geeignete Dokumentationsstruktur existiert.

---

# 22. Definition of Done

Eine Funktion gilt erst dann als abgeschlossen, wenn:

- Sie fachlich vollständig implementiert ist.
- Sie in die bestehende Anwendung integriert ist.
- Die erforderlichen Berechtigungen implementiert sind.
- Die Datenbankmigrationen vorhanden sind.
- Die Benutzeroberfläche funktioniert.
- Eingaben validiert werden.
- Fehlerfälle behandelt werden.
- Automatisierte Tests vorhanden sind.
- Die relevanten Tests erfolgreich ausgeführt wurden.
- Die Dokumentation aktualisiert ist.
- Bestehende Funktionen nicht unbeabsichtigt beeinträchtigt werden.

Eine rein vorhandene Entity oder ein Controller ohne vollständigen Geschäftsprozess gilt nicht als abgeschlossene Funktion.

---

# 23. Erwartete Gesamtergebnisse

Am Ende des Projekts soll DICOMA folgende Eigenschaften besitzen:

1. Vollständige Mitglieder- und Vereinsverwaltung.
2. Tauchausbildung für Mitglieder und Nichtmitglieder.
3. Mannschafts- und Turnierverwaltung.
4. Ausrüstungsverwaltung mit Verleih.
5. Gerätewartverwaltung einschließlich Kasse.
6. Thekendienstverwaltung.
7. Öffentliches Vereinsportal.
8. Mitgliederportal.
9. Ausbildungsportal.
10. Vorstandsportal.
11. Gerätewartportal.
12. Sportportal.
13. Veranstaltungsmanagement mit Anmeldung.
14. Clubreisemanagement mit Anmeldung und Zahlungen.
15. Zentrale Kalender-, Dokumenten- und Benachrichtigungsfunktionen.
16. Konsistentes Berechtigungssystem.
17. Getestete und dokumentierte Geschäftsprozesse.

---

# 24. Konkreter Startauftrag

**Beginne jetzt mit Phase 1.**

1. Entpacke und untersuche `dicoma2110.zip`.
2. Ermittle die tatsächliche Projektarchitektur.
3. Inventarisiere alle Entities, Controller, Services, Templates, Routen und Migrationen.
4. Vergleiche den vorhandenen Funktionsumfang mit sämtlichen Anforderungen dieses Prompts.
5. Identifiziere fehlende, unvollständige, redundante und fehlerhafte Implementierungen.
6. Analysiere Sicherheitsprobleme und technische Risiken.
7. Erstelle die vollständige Funktionsmatrix.
8. Erstelle einen priorisierten, nach Abhängigkeiten gegliederten Umsetzungsplan.
9. Definiere die konkreten Arbeitspakete der ersten Implementierungsphase.
10. Gib eine nachvollziehbare Zusammenfassung der Ergebnisse aus.

**Wichtig:** Nimm während dieser ersten Analysephase keine produktiven Codeänderungen vor.

Nach Abschluss der Analyse präsentiere die Ergebnisse und beginne erst nach ausdrücklicher Freigabe mit der Implementierung.

Arbeite anschließend die genehmigten Phasen systematisch ab.

Ziel ist eine stabile, erweiterbare, sichere und produktionsfähige DICOMA-Anwendung, die sämtliche relevanten Aufgaben eines Tauchvereins auf einer gemeinsamen Plattform unterstützt.