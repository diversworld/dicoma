# DICOMA – Phase 1: Architektur und Modulstruktur

Stand: 06.11.2026. **Status: teilweise abgeschlossen.** Grundlage ist die
freigegebene Phase 1 des aktuellen Auftrags, nicht die anders nummerierte
ältere [Master Specification](../MASTER_SPECIFICATION.md). Die Ergebnisse
beziehen sich auf den lokalen Arbeitsbaum einschließlich bereits vorhandener
unversionierter und nicht commiteter Änderungen.

## 1. Ausgangslage und Abhängigkeiten

Der bestehende Symfony-/Doctrine-/EasyAdmin-Monolith bleibt erhalten. Das
frühere [Phase-0-Protokoll](phase-00.md) ist eine teilweise Bestandsaufnahme,
keine vollständige Prüfung aller Anforderungen, Controller oder Rechte. Die
dort dokumentierten offenen Übersetzungs-, Sicherheits-, Betriebs- und
Abnahmepunkte bleiben offen. Die Auftraggeberseite hat zunächst nur isolierte
Entwicklungsarbeit gewählt; Zielsystemprüfung und Produktivdeployment sind
nicht Gegenstand dieses Schritts.

`App\` wird laut [composer.json](../../composer.json) aus `src/` geladen;
die [Servicekonfiguration](../../config/services.yaml) registriert die
Anwendungsklassen automatisch. Controller, Entities, Repositories und
Services liegen überwiegend technisch gruppiert, nicht physisch nach Fachmodul.
Die vorhandenen [DiveClub-Domainklassen](../../src/DiveClub) sind einzelne
Bausteine, kein Beleg dafür, dass sämtliche Geschäftsprozesse modularisiert
sind. Es wurden weder ein zweites Backend noch eine neue Infrastruktur
eingeführt.

## 2. Fachliche und technische Grenzen

Die folgende Zuordnung beschreibt Verantwortlichkeiten und vorhandene
Anknüpfungspunkte, keine bereits vollständig durchgesetzten Paketgrenzen:

| Bereich | Verantwortung und Beispiele | Wichtige Abhängigkeiten |
|---|---|---|
| Identität und Mitgliedschaft | [User](../../src/Entity/User.php), [Person](../../src/Entity/Person.php), [Member](../../src/Entity/Member.php), [MembershipService](../../src/Service/MembershipService.php) | Vereine, Status, Sicherheit und Benachrichtigungen |
| Ausbildung und Kurse | [TrainingPortalController](../../src/Controller/TrainingPortalController.php), [Courses](../../src/Entity/Courses.php), [TrainingProgressService](../../src/Service/TrainingProgressService.php) | Personen, Vereinskontext, Teilnehmer, Termine und Berechtigungen |
| Sparten und Wettbewerb | [Sport](../../src/Entity/Sport.php), [Team](../../src/Entity/Team.php), [CompetitionAccess](../../src/Service/CompetitionAccess.php) | Verein, Mitglieder, zeitgebundene Funktionen und Objektzugriff |
| Ausrüstung und Prüfungen | [EquipmentBookingService](../../src/Service/EquipmentBookingService.php), [EquipmentLoanService](../../src/Service/EquipmentLoanService.php), [TankInspectionService](../../src/Service/TankInspectionService.php) | Mitglieder, Bestand, Verfügbarkeit, Kasse und Dokumente |
| Thekendienst und Kasse | [BarDutyWorkflow](../../src/Service/BarDutyWorkflow.php), [BarCashWorkflow](../../src/Service/BarCashWorkflow.php) | Vereinskontext, Mitglieder, Dienstplanung und Rechte |
| Website und Veranstaltungen | [PublicWebsite](../../src/Service/PublicWebsite.php), [ClubEventController](../../src/Controller/Frontend/ClubEventController.php), [ClubEventBooking](../../src/Service/ClubEventBooking.php) | konfigurierter Website-Verein, Personen und Medien |
| Querschnitt und Infrastruktur | [AdminClubContext](../../src/Service/AdminClubContext.php), [SingleClubMode](../../src/Service/SingleClubMode.php), [MediaFileStorage](../../src/Service/MediaFileStorage.php), [LoginDestination](../../src/Security/LoginDestination.php) | Symfony Security, Doctrine, Routing und Dateisystem |

Die Identität ist nicht für jedes Fachobjekt eine Mitgliedschaft: Gäste und
Mitglieder können dieselben Personen- und Benutzerbausteine verwenden. Eine
zweite Personen-, Vereins- oder Spartentabelle wurde nicht eingeführt.
Diese Zuordnung ersetzt keine vollständige Prüfung aller Domänenregeln.

## 3. Öffentliche, Portal- und Adminbereiche

- Öffentliche Controller und registrierte Benutzerrouten liegen beispielsweise
  unter [Controller/Frontend](../../src/Controller/Frontend) und in
  [PublicWebsiteController](../../src/Controller/PublicWebsiteController.php).
  Dieselbe Controllerklasse kann öffentliche und angemeldete Aktionen enthalten.
- Das persönliche Portal verwendet unter anderem
  [PortalController](../../src/Controller/PortalController.php),
  [PortalNavigation](../../src/Service/PortalNavigation.php) und das vorhandene
  Frontend-Theme. Die Navigation entscheidet nicht über Objektzugriff.
- Das Backend verwendet
  [DashboardController](../../src/Controller/Admin/DashboardController.php)
  und EasyAdmin. [routes.yaml](../../config/routes.yaml) registriert
  Adminrouten mit EasyAdmin-Kontext; bestehende Legacy-Routen bleiben über
  [LegacyAdministrationController](../../src/Controller/Admin/LegacyAdministrationController.php)
  berücksichtigt.
- Die Trennung ist noch nicht überall physisch umgesetzt:
  [TrainingPortalController](../../src/Controller/TrainingPortalController.php)
  und [BarDutyController](../../src/Controller/BarDutyController.php) werden
  jeweils für Portal und Administration eingebunden und unterscheiden den
  Bereich anhand von `routeCreatedByEasyAdmin`. Auch Templates verwenden
  dieses Attribut. Änderungen daran benötigen getrennte Routen-, Rollen-,
  Objekt- und Layoutregressionen; ein bloßes Verschieben wäre keine sichere
  Bereichstrennung.

Die Regeln in [security.yaml](../../config/packages/security.yaml) werden
von oben nach unten ausgewertet. Rollen, Voter und Fachzugriffsdienste ergänzen
die Routenregeln; ein URL-Präfix, ein Menüeintrag oder ein Layout ist keine
Autorisierung. Der ausgewählte Adminverein und der Einzelvereinsmodus ersetzen
ebenfalls keine Objektprüfung.

## 4. Architekturentscheidung und Implementierung

Die Entscheidung über das Standardziel nach der Anmeldung gehörte bisher zum
Menüservice `PortalNavigation`. Sie wurde nach
[LoginDestination](../../src/Security/LoginDestination.php) verschoben.
[AppCustomAuthenticator](../../src/Security/AppCustomAuthenticator.php) und
[SecurityController](../../src/Controller/SecurityController.php) verwenden
dieselbe Entscheidung direkt; `PortalNavigation::getLoginRoute()` delegiert
weiterhin dorthin und bleibt als bestehende Schnittstelle erhalten.

Die bisherige Reihenfolge der Rollen, gespeicherter expliziter Frontendrollen,
redaktioneller Vereinsprüfung und Spartenrechte bleibt unverändert. Ein
gespeichertes Symfony-Rücksprungziel hat weiterhin Vorrang. Die
Zielentscheidung verwendet weiterhin `PublicWebsite` und
`CompetitionAccess`; diese vorhandenen fachlichen Abhängigkeiten wurden nicht
durch ein ungetestetes allgemeines Berechtigungssystem ersetzt.
Anmeldeziele sind Navigation, keine zusätzliche Berechtigung.

Geänderte bzw. neue Dateien dieses Schritts:

- `src/Security/LoginDestination.php`: gemeinsame Zielentscheidung.
- `src/Security/AppCustomAuthenticator.php` und
  `src/Controller/SecurityController.php`: direkte Nutzung bei Anmeldung bzw.
  erneutem Aufruf der Loginseite.
- `src/Service/PortalNavigation.php`: bestehende Methode als Delegation.
- `tests/Integration/PortalNavigationHttpTest.php`: zusätzliche Gast-,
  Kompatibilitäts-, Spartenleitungs- und fremde Vereinsredaktionsfälle.
- `docs/implementation/phase-01.md`: dieses Protokoll.

## 5. Konventionen und weitere schrittweise Strukturierung

Für neue und geänderte Komponenten gelten folgende Leitlinien; bestehende
Abweichungen werden nicht pauschal umbenannt:

1. PHP-Klassen und Namespaces gemäß PSR-4; Klassen in `PascalCase`, Methoden
   und Variablen in `camelCase`, Routen- und Konfigurationsnamen nach den
   vorhandenen Symfony-Konventionen. Öffentliche Routennamen und URLs nur
   nach Ermittlung aller Aufrufer ändern.
2. HTTP-Anpassung, Formulare und Darstellung an den Bereichsgrenzen halten;
   wiederverwendbare fachliche Entscheidungen und Transaktionen in vorhandenen
   Fachservices bündeln. Controller- und Template-Abhängigkeiten nicht in
   fachliche Regeln einführen.
3. Fachmodule über klar benannte Methoden und gemeinsame Identitäten
   verbinden. Schnittstellen nur bei einem konkreten austauschbaren Vertrag
   oder nachgewiesenen Entkopplungsbedarf einführen; keine parallelen
   Fachmodelle oder generischen Modulregistry-Demonstrationen.
4. Infrastruktur wie Dateispeicherung, Security, Konfiguration und
   Persistenz von fachlichen Zuständigkeiten unterscheiden. Bestehende
   Symfony-/Doctrine-Abhängigkeiten sind nicht bereits vollständig isoliert;
   bei jeder Entkopplung Verhalten und Fehlermeldungen erhalten.
5. Für jede Änderung von Bereichs- oder Fachgrenzen positive und negative
   Rollen-/Objekttests, bestehende Routen, Formulare und Rücksprungziele
   gemeinsam prüfen. Neue Modulgrenzen sind bisher weder mechanisch noch für
   alle Controller durchgesetzt.

Nächste architekturinterne Schritte sind eine quellenbasierte Zerlegung der
gemischten Portal-/Adminaktionen, die Ermittlung ihrer Aufrufer und
Verhaltensverträge sowie inkrementelle Änderungen mit HTTP-Regressionsprüfungen.
Keine pauschale Verschiebung aller Dateien und keine Änderung von URLs,
Datenbanktabellen oder Mehrmandantenmodell.

## 6. Datenbank, Migrationen und Sicherheit

Keine Entities, Mappings, Migrationen oder bestehenden Daten wurden verändert.
Das Mapping wurde ohne Datenbanksynchronitätsprüfung validiert. Der frühere
isolierte Migrationslauf und die Grenzen der lokalen Backup-/Restore-Prüfung
stehen in [Phase 0](phase-00.md); sie sind kein erneuter Nachweis für ein
tatsächliches Zielsystem. Die bestehenden Sicherheitsregeln und
Objektprüfungen wurden nicht erweitert oder abgeschwächt. Die offenen
umfassenden Rollen- und Objektprüfungen bleiben bestehen.

## 7. Tests und Qualitätsprüfungen

Ausführung im DDEV-Webcontainer mit PHP 8.5, SQLite im Speicher und Null-Mailer:

- Gezielt `PortalNavigationHttpTest` für Loginziel, gespeicherte Ziele,
  Gäste, Spartenleitung und vereinsfremde Redaktion: **16 Tests,
  565 Assertions** bestanden, ohne übersprungene oder unvollständige Tests.
  Ein erster Lauf hatte einen Fixture-Fehler durch eine nach einer Anfrage
  nicht mehr verwaltete Club-Instanz; nach Korrektur des Testaufbaus bestand
  der vollständige gezielte Wiederholungslauf.
- `SingleClubModeHttpTest` und `Phase2FoundationHttpTest`, gezielt für
  fremde Sitzungen, echte Anmeldung und Gastregistrierung: **3 Tests,
  27 Assertions** bestanden.
- `lint:container`, `lint:yaml config`, `debug:router` und
  `doctrine:schema:validate --skip-sync`: bestanden; eine
  Schemasynchronitätsprüfung war ausdrücklich nicht Teil dieses Befehls.
- PHP-Syntax der fünf geänderten PHP-Dateien und statisches Routengate
  (30 Routen, 21 Controller): bestanden. Das Routengate ist keine umfassende
  Prüfung aller Routen.

Das vollständige Basis-Gate aus Phase 0 wurde **vor** der hier beschriebenen
Codeänderung ausgeführt und ist kein vollständiger Nachweis für diesen neuen
Arbeitsstand. Eine fachliche oder reale Staging-/Produktivabnahme wurde nicht
durchgeführt.

## 8. Abnahmekriterien und offene Punkte

| Kriterium | Status / Nachweis |
|---|---|
| Fachliche Grenzen, aktuelle Struktur und Namenskonventionen | Für die genannten Bereiche dokumentiert; nicht jede Klasse und Abhängigkeit einzeln geprüft. |
| Gemeinsame Services und Schnittstellen strukturieren | Anmeldeziel von der Menüdarstellung getrennt, bestehende Methode erhalten und gezielt getestet; weitere Kopplungen offen. |
| Public, Portal und Admin trennen | Bestehende technische Grenzen und Mischcontroller dokumentiert und Navigation gezielt getestet; keine vollständige physische oder sicherheitsbezogene Trennung nachgewiesen. |
| Bestehende Routen, Daten und Funktionen erhalten | Keine Routendefinitionen oder Datenbankstrukturen geändert; gezielte Tests bestanden, aber keine Vollabdeckung aller Workflows. |
| Neue Fachmodule ohne Beeinträchtigung bestehender Funktionen | Architekturrichtung und ein inkrementeller Schritt vorhanden; für die gesamte Anwendung noch nicht ausreichend nachgewiesen. |

## 9. Empfehlung und nächster Schritt

Phase 1 ist noch nicht als vollständig abgenommen einzustufen. Zunächst sind
die gemischten Bereichsgrenzen und ihre Berechtigungs- und
Regressionsverträge weiter zu untersuchen und nach Bedarf schrittweise zu
trennen; Phase-0-Risiken und fachliche Abnahme bleiben gesondert sichtbar.
Eine Freigabe von Phase 2 kann erst nach Bewertung dieser offenen Punkte und
ausdrücklichem Auftrag erfolgen. Phase 2 wird nicht automatisch begonnen.
