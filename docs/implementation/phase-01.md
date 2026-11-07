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
Abnahmepunkte bleiben offen. Ergänzend wurde das lokale DDEV-Testsystem
vorbereitet und begrenzt geprüft; ein Produktivdeployment ist nicht Gegenstand
dieses Schritts.

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
| Querschnitt und Infrastruktur | [AdminClubContext](../../src/Service/AdminClubContext.php), [SingleClubMode](../../src/Service/SingleClubMode.php), [MediaFileStorage](../../src/Service/MediaFileStorage.php), [LoginDestination](../../src/Security/LoginDestination.php), [EasyAdminRequest](../../src/Http/EasyAdminRequest.php) | Symfony Security, Doctrine, Routing und Dateisystem |

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
  jeweils für Portal und Administration eingebunden. Der neue
  [EasyAdminRequest](../../src/Http/EasyAdminRequest.php) kapselt dort sowie
  im Kalender- und Ausbildungsbewertungscontroller das Lesen des bestehenden
  Attributs `routeCreatedByEasyAdmin`. Templates verwenden das Attribut
  weiterhin direkt. Änderungen daran benötigen getrennte Routen-, Rollen-,
  Objekt- und Layoutregressionen; ein bloßes Verschieben wäre keine sichere
  Bereichstrennung.

Die Regeln in [security.yaml](../../config/packages/security.yaml) werden
von oben nach unten ausgewertet. Rollen, Voter und Fachzugriffsdienste ergänzen
die Routenregeln; ein URL-Präfix, ein Menüeintrag oder ein Layout ist keine
Autorisierung. Der ausgewählte Adminverein und der Einzelvereinsmodus ersetzen
ebenfalls keine Objektprüfung.

Die Konto-Selbstverwaltung ist als eigener Controller mit Portal- und
EasyAdmin-Routen umgesetzt. Sie verwendet für beide Bereiche dieselben
Formulare und dieselbe angemeldete Benutzeridentität; der EasyAdmin-Kontext
steuert nur das Layout. Die konkreten Routen sind für `ROLE_USER` freigegeben,
ohne Zugriff auf die übrige Administration zu erteilen.

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

Die Interpretation des EasyAdmin-Routenattributs in den genannten Controllern
ist nun ein gemeinsamer HTTP-Adapter. Er behält die bisherige PHP-Umwandlung
in Boolean und `false` bei fehlender Anfrage bei. Er prüft lediglich den
Routenkontext und ist **keine** Rollen-, Vereins- oder Objektberechtigung;
die bisherigen Prüfungen bleiben an ihren Aufrufstellen bestehen.

Die schrittweise Prüfung der gemischten Controller hat zwei konkrete
Bereichsprobleme ergeben und behoben:

- Thekenleitungen dürfen fremde Einteilungen nur im Backend zurückziehen.
  Das persönliche Portal prüft innerhalb der Transaktion zusätzlich, ob die
  Einteilung dem angemeldeten Mitglied gehört. Eigene Rückzüge bleiben dort
  möglich; Rollen-, Objekt- und CSRF-Prüfungen bleiben erforderlich.
- Zahlungsbuchung, Teilnehmer- und Kursabschluss sowie die Stornierung einer
  fremden Kursanmeldung werden von den bisherigen Portal-POST-Adressen nach
  Prüfung von Berechtigung und CSRF mit HTTP 307 an die zugehörige Adminroute
  weitergeleitet. Erst dort wird die Aktion nach erneuter Prüfung des
  ausgewählten Vereins ausgeführt. Die Stornierung der eigenen Anmeldung bleibt
  eine persönliche Portalaktion.
- Auch eine fremde Ausbildungsbescheinigung wird über die Portaladresse an die
  Adminroute weitergeleitet, die den ausgewählten Verein prüft. Eigene
  Bescheinigungen bleiben im persönlichen Portal erreichbar. Berechtigte
  Ausbilder können die Adminansicht ihres zugeordneten Kurses weiter öffnen;
  die Bescheinigung selbst bleibt mit `private, no-store` geschützt.

Für einen abweichenden Admin-Vereinskontext beim Thekendienst wurde ausdrücklich
die Sperre gewählt: Backendzugriffe einschließlich Jahresregel und Thekenkasse
erfordern, dass der ausgewählte Verein dem Mitgliedschaftsverein entspricht.
Ein fehlender Vereinskontext sperrt ebenfalls; vereinsübergreifende
Thekendienstverwaltung wird nicht eingeführt. Dies ändert insbesondere die
bisher mögliche Jahresregelverwaltung eines fremd ausgewählten Vereins durch
Superadmins. Das persönliche Portal bleibt vom ausgewählten Adminverein
unabhängig. Im Admin-Gesamtkalender bleiben Termine des ausgewählten Vereins
sichtbar, aber ein bei abweichendem Mitgliedschaftsverein nicht erreichbarer
Thekendienstlink wird nicht ausgegeben.

Das EasyAdmin-Benutzermenü hatte zwei Abmeldeeinträge: den Standardlink des
Bundles und das bestehende CSRF-geschützte POST-Formular. Der Standard-Logout
wird nun gezielt entfernt; andere Standardaktionen einschließlich einer
gegebenenfalls angebotenen Impersonierungsrückkehr bleiben erhalten. Im
responsiven und im Desktop-Dropdown steht jeweils ein POST-Abmeldeformular.

Das Menü und die Portalnavigation verweisen auf die eigene Kontoseite und die
Kennwortänderung. Benutzername und Konto-E-Mail werden angezeigt, aber nicht
über die Selbstverwaltung geändert. Die Kontaktangaben beziehen sich auf die
zugeordnete zentrale Person; Notfalldaten werden nur bei einer zugeordneten
Mitgliedschaft angeboten und für Mitglieder weiterhin protokolliert. Eine
Personenzuordnung wird nicht automatisch angelegt; fehlt sie, verweist die Seite
an die Verwaltung. Für eine reine Kontaktkorrektur werden keine zusätzlichen
Pflichtangaben zur Mitgliedschaft verlangt.

Die Kennwortänderung erfordert das aktuelle Kennwort, eine wiederholte Eingabe
und ein CSRF-Token. Sie verwendet die bereits bestehende Kennwortregel
(mindestens sechs Zeichen, höchstens 4096), nicht eine neue strengere
Passwortrichtlinie. Das neue Kennwort wird gehasht, eigene ausstehende
Rücksetzanforderungen werden in derselben Transaktion entfernt und die aktuelle
Sitzung wird beendet. Andere Sitzungen werden beim nächsten Benutzer-Refresh
wegen des geänderten Kennworthashs abgemeldet.

Geänderte bzw. neue Dateien dieses Schritts:

- `src/Security/LoginDestination.php`: gemeinsame Zielentscheidung.
- `src/Security/AppCustomAuthenticator.php` und
  `src/Controller/SecurityController.php`: direkte Nutzung bei Anmeldung bzw.
  erneutem Aufruf der Loginseite.
- `src/Service/PortalNavigation.php`: bestehende Methode als Delegation.
- `tests/Integration/PortalNavigationHttpTest.php`: zusätzliche Gast-,
  Kompatibilitäts-, Spartenleitungs-, fremde Vereinsredaktions- und
  Mehrrollenfälle.
- `src/Http/EasyAdminRequest.php`, `src/Controller/BarDutyController.php`,
  `src/Controller/TrainingPortalController.php`,
  `src/Controller/Frontend/ClubCalendarController.php` und
  `src/Controller/Admin/TrainingUnitBatchController.php`: gemeinsamer
  Adapter für den bestehenden Routenbereich.
- `tests/Unit/EasyAdminRequestTest.php`: Wahrheitswerte des Routenattributs
  und fehlende Anfrage.
- `tests/Integration/AreaRouteBoundaryTest.php` und `phpunit.dist.xml`:
  dauerhafter Vertragstest für 24 bestehende Portal-/Adminroutenpaare.
- `tests/Integration/Phase4TrainingHttpTest.php`: erfolgreiche Stornierung
  mit echtem Formular und bereichsspezifischem Rücksprung im Portal und Backend;
  Vereinswechsel und vier administrative Schreibvorgänge.
- `src/Service/BarDutyAccess.php`, `src/Service/BarDutyWorkflow.php` und
  `src/Controller/BarDutyController.php`: Vereinsgrenze und Unterscheidung
  eigener Rückzüge von Backend-Verwaltungsaktionen.
- `src/Service/ClubCalendar.php`: keine gesperrten Backend-Thekendienstlinks.
- `tests/Integration/Phase8BarDutyHttpTest.php`,
  `tests/Integration/MediaFileHttpTest.php` und
  `tests/Integration/SingleClubModeHttpTest.php`: Formular-, Vereinswechsel-,
  Kassen-, Jahresregel-, Kalender- und Einzelvereinsregressionen.
- `src/Controller/AccountController.php`, `src/Form/PersonContactType.php`,
  `src/Form/PersonContactFields.php`, `src/Form/AccountPasswordType.php`,
  `src/Form/MemberContactType.php` und `config/packages/security.yaml`:
  eigene Konto- und Kennwortformulare mit getrennten Bereichsrouten und
  gemeinsamen Kontaktfeldern.
- `src/Controller/Admin/DashboardController.php`, `templates/account/`,
  `templates/includes/_navigation.html.twig`, `templates/security/login.html.twig`
  und `translations/form_catalogues.php`: Benutzermenü, bereichsspezifische
  Darstellung und Texte.
- `tests/Integration/AccountSelfServiceHttpTest.php` und `phpunit.dist.xml`:
  isolierte HTTP-Regressionen für die Selbstverwaltung.
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

Keine Entities, Mappings oder Migrationen wurden geändert. Die isolierten
Code- und HTTP-Tests verwenden keine vorhandenen Anwendungsdaten. Das Mapping
wurde zunächst ohne Datenbanksynchronitätsprüfung validiert. Der frühere
isolierte Migrationslauf und die Grenzen der lokalen Backup-/Restore-Prüfung
stehen in [Phase 0](phase-00.md); sie sind kein erneuter Nachweis für ein
tatsächliches Zielsystem. Die oben beschriebenen zusätzlichen Bereichs- und
Vereinsprüfungen ergänzen die bestehenden Sicherheits- und Objektprüfungen.
Die offenen umfassenden Rollen- und Objektprüfungen bleiben bestehen.

### Lokales Abnahmeziel und Betriebsgrenzen

Auf ausdrücklichen Wunsch wurde das vorhandene DDEV-Projekt unter
`https://dicoma.ddev.site` vorbereitet, **nicht** eine neue isolierte
Installation. Es verwendet weiterhin seinen vorhandenen Datenbestand.
Eine schreibende oder destruktive fachliche Prüfung auf diesem Bestand wurde
nicht automatisiert; Teständerungen dort wären echte Datenänderungen.

Vor der Anpassung wurde ein vom Auftraggeber bestätigtes Wartungsfenster mit
Schreibpause genutzt. Bei gestopptem Webcontainer wurden Datenbank und
Anwendungsdateien einschließlich privater Dateien und Konfiguration in einem
geschützten lokalen Backup gesichert. Dessen Dateiarchiv wurde mit erhaltenen
Rechten separat wiederhergestellt und verglichen. Eine netzwerkisolierte
MariaDB-Wiederherstellung ergab 108 übereinstimmende Tabellenprüfsummen.
Die SQL-Dumps stimmen nach Entfernung von CR-Zeichen überein; eine
byteidentische Wiederherstellung der ursprünglichen SQL-Textzeilen wird daher
nicht behauptet. Die Schreibpause anderer Prozesse beruht auf der Bestätigung
der Auftraggeberseite. Das Backup enthält vertrauliche Daten und ist nicht
Bestandteil des Repositorys.

Für den DDEV-Webcontainer wurde per lokaler, nicht versionierter
`.ddev/config.abnahme.yaml` der Mailtransport auf das lokale Mailpit umgestellt.
Die lokale Datei enthält:

```yaml
web_environment:
  - APP_DEBUG=0
  - MAILER_DSN=smtp://127.0.0.1:1025
```

Eine synthetische Nachricht wurde über Symfony versendet und dort nachgewiesen;
die bisherige externe Zustellung aus diesem Container ist damit für die
Abnahme ersetzt. Mailpit ist unter `https://dicoma.ddev.site:8026` erreichbar.
Ein kurzzeitig eingerichteter, nur Statuscodes ausgebender HTTP-Diagnosetest
bestätigte die wirksamen Einstellungen auch im PHP-FPM-Request; er wurde
anschließend entfernt und die Entfernung per HTTP 404 geprüft.
Zudem wurde das zuvor trotz `APP_ENV=prod` aktivierte Debugging im
Webcontainer abgeschaltet. Nach dem Neustart lieferte das Ziel zunächst
HTTP 500 wegen eines veralteten Produktionscontainers im Symfony-Cache; nach
`cache:clear` mit der tatsächlichen Web-PHP-Version 8.4 war es wieder
erreichbar. Eine öffentliche nicht vorhandene Route antwortete anschließend
mit 404 ohne Debug-Trace. Die Einstellungen gelten nach erneutem DDEV-Neustart.
Die Einstellung gilt für den DDEV-Webcontainer, nicht nachgewiesenermaßen für
unabhängige Hostprozesse oder andere externe Integrationen. Die Umgebung ist
deshalb nicht als vollständig ausgehendisoliert anzusehen.
Das Entfernen der lokalen Überschreibung kann den zuvor konfigurierten
externen Mailtransport wieder aktivieren; ein solcher Wechsel erfordert eine
gesonderte Betriebsentscheidung.

Die Auftraggeberseite hat den vorhandenen Datenbestand inzwischen als Test-
und Entwicklungsdaten ohne produktive Daten eingeordnet. Der Ziel-Container,
das Doctrine-Mapping und das tatsächlich verbundene Schema
bestanden die Prüfung; es waren keine Migrationen offen. Anonyme öffentliche
Routen waren erreichbar, `/admin` und `/calendar` leiteten zur Anmeldung und
`/install` antwortete mit 404. Mit einer von der Auftraggeberseite angemeldeten
Sitzung wurden Ausbildung, Thekendienst und Kalender jeweils im Portal und
Backend lesend geöffnet: die Modulansichten und bereichsspezifischen Layouts
waren sichtbar. Dies prüft weder alle Rollen noch sämtliche Geschäftsvorgänge.

Für die lokale Prüfung mit vorhandenen Konten:

1. `https://dicoma.ddev.site` öffnen und mit einem berechtigten Konto anmelden.
2. Portal und Backend über die entsprechenden Navigationsziele prüfen;
   Testmails des Webcontainers gegebenenfalls in Mailpit einsehen.
3. Schreibende Fachabläufe nur mit ausdrücklich dafür freigegebenen Datensätzen
   durchführen: Sie ändern den vorhandenen Datenbestand tatsächlich. Andere
   Nebenwirkungen als der abgefangene Web-Mailversand sind nicht isoliert.
4. Nach Änderungen am Anwendungscode den Produktionscache im Webcontainer mit
   `ddev exec php bin/console cache:clear --env=prod --no-debug` erneuern.

Dies ist eine lokal erreichbare, vorbereitete Prüfoberfläche, keine vollständig
abgenommene oder gegen alle Nebenwirkungen abgeschottete Stagingumgebung.

## 7. Tests und Qualitätsprüfungen

Ausführung im DDEV-Webcontainer mit PHP 8.5, SQLite im Speicher und Null-Mailer:

- Kontoselbstverwaltung auf dem endgültigen Stand: **11 Tests, 161
  Assertions**. Geprüft wurden sechs Backend-Rollenkonstellationen, beide
  Menüs und echte Links, ein Login ohne Mitgliedschaft und ein Konto ohne
  Person, persönliche Kontakt- und Notfalländerungen, getrennte Konto-E-Mail,
  CSRF, ungültige und zusätzliche Felder, Passwortregel, falsches aktuelles
  Kennwort, Bestätigung, erneute Anmeldung, Abmeldung einer weiteren Sitzung
  und die Entfernung nur der eigenen Rücksetzanforderungen.
- Auf dem lokalen DDEV-Ziel wurden nach dem Cache-Neuaufbau das geöffnete
  Benutzermenü sowie die Konto- und Kennwortseite in einer bestehenden Sitzung
  **lesend** geprüft. Die Formulare wurden dort nicht abgesendet. Anonyme
  Zugriffe auf die Kontoseiten leiten zur Anmeldung.
- Die verwendeten Kontoformular-Bezeichnungen sind in den Katalogen für
  Deutsch, Englisch, Französisch und Spanisch vorhanden.
- Ein erneutes isoliertes Basis-Gate bestand mit **443 Tests, 10.577
  Assertions**; da während dieses Laufs noch letzte Änderungen erfolgten,
  ist er kein einheitlicher Nachweis des endgültigen Arbeitsstands.
- Ausbildungsbescheinigung und angrenzende persönliche, Rollen- und
  Vereinsgrenzen: **7 Tests, 149 Assertions**. Der neue Fall deckt
  unter anderem den echten Vereinswechsel, eigenen und fremden Nachweis,
  zugeordneten Ausbilder, unberechtigte Person und Cache-Header ab.
- Ein erneutes vollständiges Basis-Gate wurde für die zeitnahe Abschaltung des
  zuvor öffentlichen Debug-Modus bewusst unterbrochen; sein PHPUnit-Teil und
  das Gate gelten **nicht als bestanden**. Der anschließende vollständige
  Wiederholungslauf bestand mit **432 Tests und 10.274 Assertions**, ohne
  übersprungene oder unvollständige Tests. Er umfasst Unit- und isolierte
  SQLite-Tests, nicht die MariaDB-Staging-HTTP-Suite; die Schemasynchronität
  des tatsächlichen DDEV-Ziels wurde separat lesend geprüft:

  ```sh
  docker exec -e DATABASE_URL=sqlite:///:memory: -e MAILER_DSN=null://null -e DICOMA_SINGLE_CLUB_MODE=0 -e APP_DEBUG=1 -e PHP_BIN=php8.5 ddev-dicoma-web bash scripts/phase2_acceptance_gate.sh
  ```
- Vollständige betroffene Integrationstestdateien für Thekendienst,
  Ausbildung, Einstellungen und Einzelvereinsmodus: **117 Tests,
  3.144 Assertions**, ohne übersprungene oder unvollständige Tests:

  ```sh
  docker exec -e DATABASE_URL=sqlite:///:memory: -e MAILER_DSN=null://null -e DICOMA_SINGLE_CLUB_MODE=0 ddev-dicoma-web php8.5 vendor/bin/phpunit --no-progress --fail-on-skipped --fail-on-incomplete tests/Integration/Phase8BarDutyHttpTest.php tests/Integration/Phase4TrainingHttpTest.php tests/Integration/MediaFileHttpTest.php tests/Integration/SingleClubModeHttpTest.php
  ```

- Aktueller bereichsbezogener Wiederholungslauf über ausgewählte Ausbildungs-,
  Thekendienst-, Kassen-, Einstellungs-, Kalender-, Einzelvereins-, Routen-
  und Adaptertests: **51 Tests, 1.160 Assertions**, ohne übersprungene oder
  unvollständige Tests. Die neuen HTTP-Fälle verwenden echte Formulare und
  CSRF-Tokens sowie die echte Vereinsauswahl; sie prüfen Schreibsperren mit
  Datenbankzustand, erlaubte Aktionen nach Rückwechsel und im Portal sowie
  fehlende Vereinsauswahl und Kalenderlinks.
- Der erste Lauf der vier neuen bzw. angepassten Vereinskontextfälle fand
  einen Test-Fixture-Fehler beim Entfernen einer nach einer HTTP-Anfrage
  abgelösten Entity. Nach erneutem Laden der Entity bestanden die vier Fälle
  mit **131 Assertions**; der obige Lauf enthält auch die anschließende
  Erweiterung um persönliche Kassenaktionen.
- Gezielt `PortalNavigationHttpTest` für Loginziel, gespeicherte Ziele,
  Gäste, Spartenleitung, vereinsfremde Redaktion und zusätzliche
  Mehrrollenfälle: **21 Tests, 827 Assertions** bestanden, ohne
  übersprungene oder unvollständige Tests.
  Ein erster Lauf hatte einen Fixture-Fehler durch eine nach einer Anfrage
  nicht mehr verwaltete Club-Instanz; nach Korrektur des Testaufbaus bestand
  der vollständige gezielte Wiederholungslauf.
- Vollständiger `PortalNavigationHttpTest` nach der Login-Entkopplung:
  **58 Tests, 2.378 Assertions** bestanden. Dieser Lauf erfolgte vor
  Aufnahme weiterer Mehrrollenfälle und vor dem EasyAdmin-Routenadapter.
- `AreaRouteBoundaryTest` und `EasyAdminRequestTest`: zusammen **9 Tests,
  201 Assertions** bestanden. Der Routentest prüft 24 Routenpaare einschließlich
  Pfad, Methoden, Anforderungen und Bereichsmarkierung; er ersetzt keine
  Prüfung der Zugriffsrechte.
- Gezielt Ausbildungs-, Thekendienst- und Kalenderregressionen:
  **25 Tests, 748 Assertions** bestanden. Sie umfassen unter anderem
  Bereichslayouts, fremde Vereine, Managementoperationen und CSRF.
- Ergänzend Ausbildungs-Stornierungen über die echten Portal- und
  Backendformulare sowie Rollen- und Layoutregressionen: **3 Tests,
  296 Assertions** bestanden.
- `SingleClubModeHttpTest` und `Phase2FoundationHttpTest`, gezielt für
  fremde Sitzungen, echte Anmeldung und Gastregistrierung: **3 Tests,
  27 Assertions** bestanden.
- `lint:container`, `lint:yaml config`, `debug:router` und
  `doctrine:schema:validate --skip-sync`: bestanden; eine
  Schemasynchronitätsprüfung war ausdrücklich nicht Teil dieses Befehls.
- PHP-Syntax der geänderten PHP-Dateien und statisches Routengate
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
| Gemeinsame Services und Schnittstellen strukturieren | Anmeldeziel von der Menüdarstellung getrennt, bestehende Methode erhalten; Interpretation des EasyAdmin-Routenkontexts gekapselt. Weitere Kopplungen offen. |
| Public, Portal und Admin trennen | Bestehende technische Grenzen und Mischcontroller dokumentiert, Navigation und 24 Portal-/Adminroutenpaare geprüft; einzelne Schreib- und Vereinsgrenzen abgesichert, aber keine vollständige physische oder sicherheitsbezogene Trennung nachgewiesen. |
| Bestehende Routen, Daten und Funktionen erhalten | Bestehende Routen und Datenbankstrukturen bleiben erhalten; für die Selbstverwaltung wurden neue Routen ergänzt. Gezielte Tests bestanden, aber keine Vollabdeckung aller Workflows. |
| Neue Fachmodule ohne Beeinträchtigung bestehender Funktionen | Architekturrichtung und ein inkrementeller Schritt vorhanden; für die gesamte Anwendung noch nicht ausreichend nachgewiesen. |

## 9. Empfehlung und nächster Schritt

Phase 1 ist noch nicht als vollständig abgenommen einzustufen. Zunächst sind
die gemischten Bereichsgrenzen und ihre Berechtigungs- und
Regressionsverträge weiter zu untersuchen und nach Bedarf schrittweise zu
trennen; Phase-0-Risiken und fachliche Abnahme bleiben gesondert sichtbar.
Eine Freigabe von Phase 2 kann erst nach Bewertung dieser offenen Punkte und
ausdrücklichem Auftrag erfolgen. Phase 2 wird nicht automatisch begonnen.
