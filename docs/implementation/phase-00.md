# DICOMA – Phase 0: Bestandsaufnahme und Entwicklungsgrundlage

Stand: 06.11.2026, einschließlich Nachprüfung. **Status: teilweise
abgeschlossen; umfassende Abnahme-Gates offen.** Maßgeblich ist die zum aktuellen Auftrag übergebene
Phasenplanung, nicht die Nummerierung älterer Berichte.

## 1. Auftrag, Ausgangslage und Grenzen

Untersucht wurde der lokale Arbeitsbaum auf Basis von Commit `c22fdf04`,
einschließlich bereits vorhandener nicht commiteter und unversionierter
Änderungen. Insbesondere der Einzelvereinsmodus und zugehörige Tests sind
Teil dieses Arbeitsstands, aber noch kein eingefrorener Release. Fremde
Änderungen wurden nicht zurückgesetzt. Die Bestandsaufnahme kombiniert
Dateiinventar, stichprobenartige Code- und Routenprüfung, bestehende
Dokumentation, isolierte Tests und eine lesende Sicht auf das vorhandene
Admin-Dashboard. Sie ist **keine vollständige Einzelprüfung aller
Controller, Fachanforderungen, Migrationspfade oder Berechtigungen**.

Der ältere [Phase-1-Abschluss](../analysis/PHASE1-ABSCHLUSSBERICHT.md)
bezieht sich auf ein Archiv vom 22.10.; das
[Phase-2-Gate](../../PHASE2-ABSCHLUSS-GATES.md) und der
[Testbericht](../testing/test-report.md) dokumentieren frühere lokale Läufe.
Sie belegen nicht automatisch den aktuellen Arbeitsbaum oder eine
Produktivfreigabe. Die ältere
[Architekturanalyse](../analysis/architecture-analysis.md) nennt noch
verschiedene inzwischen geänderte Befunde. Beispielsweise verwendet die
[Registrierung](../../src/Controller/RegistrationController.php) jetzt
`Person::setPostalCode`, und die
[Reset-Konfiguration](../../config/packages/reset_password.yaml) verwendet
ein persistentes Repository.

Es wurden keine Produktivdaten verändert und keine Migrationen oder
Schreibtests gegen die bestehende Anwendungsdatenbank ausgeführt. Die
nachträglich ausdrücklich genehmigten Sicherungs-, Restore- und
Migrationsprüfungen betreffen ausschließlich die lokale DDEV-Umgebung und
isolierte Testdatenbanken. Produktivkonfiguration und -backups wurden nicht
geprüft.

## 2. Architektur und Inventar

- Symfony-/Doctrine-/EasyAdmin-Monolith mit Twig und getrennten öffentlichen,
  Portal- und Adminrouten; die Grenzen ergeben sich aus Routing,
  Zugriffskontrollen und Fachservices, nicht allein aus Verzeichnisnamen.
  Siehe [Composer](../../composer.json),
  [Routing](../../config/routes.yaml) und
  [Security](../../config/packages/security.yaml).
- Dateizählung einschließlich Unterverzeichnissen, ohne Sicherungsdateien:
  104 PHP-Dateien unter `src/Entity`, 145 unter `src/Controller`, 64 unter
  `src/Service`, 26 Repositories, 46 Formulare, drei Voter, 253 Twig-Dateien,
  141 versionierte Migrationen und 55 PHP-Dateien unter `tests`. Dateizahlen
  sind keine Funktions- oder Testabdeckung.
- Das effektive Test-Routing umfasst 657 Routen. Das statische Phase-2-Gate
  prüft davon gezielt 30 Routen und 21 Controller; es ersetzt keine
  Einzelprüfung aller Routen oder Controller. Die ältere
  [Anforderungsnachverfolgung](../analysis/requirements-traceability.md)
  erfasst 291 Aufzählungsanforderungen als unbestätigte Prüfkandidaten,
  nicht die vollständige Abnahme einschließlich aller Fließtextanforderungen.
- Lockfile: Symfony Framework 7.4.23, Doctrine ORM 2.20.14, EasyAdmin 4.29.16,
  Twig 3.30.0 und PHPUnit 13.4.6. `composer.json` deklariert PHP >= 8.2 für die
  Anwendung; das installierte PHPUnit benötigt PHP >= 8.4.1.
- Wiederverwendbare Kernbausteine: `Person`, `User`, `Member`, `Sport`,
  `MemberSport` und `DivisionRoleAssignment`; zentrale Fachservices für
  Mitglieder, Kurse, Veranstaltungen, Geräte und Thekendienste. Keine
  zweite Personen-, Vereins- oder Spartenentität ohne Prüfung einführen.
- Ausführung asynchroner Arbeit ist nicht generell nachgewiesen:
  [Konsolenkommandos](../../src/Command) existieren beispielsweise für
  Mitgliedschaft, Thekendienst und TÜV. Ein aktiver Scheduler und dessen
  Zustellung wurden nicht verifiziert.
- Das vorhandene Dashboard wurde im freigegebenen Browser ausschließlich
  lesend betrachtet. Sichtbare Menüs belegen Navigation, nicht die
  Funktionsfähigkeit oder Berechtigung aller Ziele.

Das Inventar lässt sich im Repository z. B. mit
`find src/Entity -type f -name '*.php' | sort` und entsprechend für
`src/Controller`, `src/Service`, `src/Repository`, `src/Form`, `migrations`
und `tests` reproduzieren. Vorhandene CSV-Inventare unter
[`docs/analysis/`](../analysis/) sind historische Hilfsmittel und nicht
vollständig gegen diesen Arbeitsstand aktualisiert.

## 3. Datenmodell und Migration

`Person` enthält personenbezogene Stammdaten; `User` hält Konto und Rollen;
`Member` verknüpft Person und Verein und bildet Status und Bezüge zu Sparten
ab: [Person](../../src/Entity/Person.php),
[User](../../src/Entity/User.php), [Member](../../src/Entity/Member.php).
[Sport](../../src/Entity/Sport.php) ist vereinsgebunden und dynamisch,
[MemberSport](../../src/Entity/MemberSport.php) unterstützt mehrere
Spartenzuordnungen mit Status und Datumsfeldern.
[DivisionRoleAssignment](../../src/Entity/DivisionRoleAssignment.php) bildet
zeitlich begrenzte Funktionen ab. Eine vollständige Historisierung aller
erneuten Ein- und Austritte und ein allgemeines Feature-Flag-System je Sparte
sind damit nicht nachgewiesen.

Der optionale [Einzelvereinsmodus](../../src/Service/SingleClubMode.php)
verwendet den konfigurierten Website-Verein; ältere vereinsbezogene Modelle
bleiben trotzdem bestehen. Der Modus ersetzt keine Objektprüfung (siehe S1).

141 Migrationsdateien reichen von `Version20240108075321` bis
`Version20261030110000`. Einzelne neuere Migrationen prüfen Voraussetzungen
und erklären Rollbacks für irreversibel, z. B.
[Personenmigration](../../migrations/Version20261023100000.php) und
[Mitgliedschaftsmigration](../../migrations/Version20261024102048.php).
Andere enthalten destruktive Rückwärtsoperationen. Aus einer bloßen
Dateisuche folgt nicht, welche `up`- oder `down`-Operationen im konkreten
Upgrade tatsächlich laufen. Auf einer isolierten Wiederherstellung der lokalen Datenbank sind alle 141
Migrationen als ausgeführt registriert; keine ist offen. Doctrine meldet das
Mapping und das wiederhergestellte Schema als synchron. Dies belegt weder
einen Upgradepfad eines älteren Bestands noch den Stand einer Produktivdatenbank.
Der separate Migrationskettentest auf `db_test` ist hiervon unabhängig; nach
Korrektur dreier Testfehler bestand der vollständige Wiederholungslauf,
siehe Abschnitt 6.

## 4. Funktionsmatrix zur aktuellen Phasenplanung

„Teilweise“ bedeutet vorhandene konkrete Bausteine, aber keine Abnahme der
gesamten Anforderung. Die Prüfungen in Abschnitt 6 ersetzen keine
fachlichen End-to-End-Abnahmen.

| Phase / Bereich | Status | Aktuelle Evidenz und wesentliche offene Punkte |
|---|---|---|
| 1 Architektur | Teilweise | [Routing](../../config/routes.yaml), Services und Twig/EasyAdmin vorhanden; konsistente Modulgrenzen und Zugriffspfade nicht vollständig abgenommen. |
| 2 Mitglieder / Sparten | Teilweise | Personen-/Mitgliedschaftsmodell, `Sport`, `MemberSport`, [Spartenverwaltung](../../src/Controller/Admin/DivisionController.php) und Rollenzeitraum vorhanden. Dynamische Modulaktivierung, lückenlose Historie und vollständiger End-to-End-Nachweis offen. |
| 3 Rollen / Sicherheit | Teilweise | Rollenmehrfachvergabe, `ROLE_MEMBER` aus aktivem Mitglied, Voter und Fachzugriffsdienste vorhanden; S1–S3 wurden gezielt bearbeitet, umfassende Objekt-/Negativtests bleiben offen. `ROLE_ADMIN` erbt mehrere Fachrollen; Spartenfunktionen sind nicht automatisch ein allgemeines Berechtigungssystem. |
| 4 Öffentlich / CMS | Teilweise | [PublicWebsiteController](../../src/Controller/PublicWebsiteController.php), Artikel, Veranstaltungen, Kontaktinformationen, Kursangebot, Anträge und Medienspeicher vorhanden; eigenständige Sparten-CMS-Bereiche, Kontaktformular, Barrierefreiheit und SEO nicht vollständig abgenommen. |
| 5 Mitgliederportal / Kommunikation | Teilweise | [PortalNavigation](../../src/Service/PortalNavigation.php), Profil, eigene Buchungen/Kurse und Kalender vorhanden; ein einheitlicher allgemeiner Nachrichten-/Empfängergruppenprozess ist nicht nachgewiesen. |
| 6 Ausbildung | Teilweise | Kurse, Teilnahme, Warteliste, Qualifikation, Ausbilderportal und Fortschrittsservices vorhanden; fachliche Rollenabnahme und bestehende Schutzgrenzen sind vollständig zu prüfen. |
| 7 Veranstaltungen / Training | Teilweise | [ClubEventBooking](../../src/Service/ClubEventBooking.php) mit Frist, Kapazität, Warteliste und Storno; Terminkalender und Kurse vorhanden. Konkurrenztests auf Ziel-DB, Erinnerungen und Zugriffsgrenzen sind nicht aktuell abgenommen. |
| 8 Geräte | Teilweise | Inventar, Reservierung, Ausgabe/Rückgabe und Wartung, u. a. [EquipmentBookingLoanService](../../src/Service/EquipmentBookingLoanService.php); Vollständigkeit, Erinnerungen und echte Parallelität offen. |
| 9 Dienste | Teilweise | Schichtdefinition, Einteilung, Tausch und Kassenworkflow, u. a. [BarDutyWorkflow](../../src/Service/BarDutyWorkflow.php); Betrieb der Benachrichtigungen und fachliche Vollabnahme offen. |
| 10 Finanzen / Dokumente | Teilweise | Beitragsklassen, Forderungen, Zahlungen und Mandatsdaten in [MembershipFinanceService](../../src/Service/MembershipFinanceService.php); private Dokumentablagen. Mandatserfassung löst keinen Bankeinzug aus; Rechnungen, Mahnwesen, SEPA-End-to-End und Datenschutzabnahme nicht vollständig belegt. |
| Clubreisen | Nicht nachgewiesen | Die Master-Spezifikation fordert ein eigenständiges Reisemodul. Die Suche nach Reise-/Trip-/Travel-/Journey-Begriffen in Entity- und Controller-Dateien sowie nach entsprechend benannten Dateien ergab keinen Nachweis eines solchen Moduls. Das ist keine erschöpfende Funktionsprüfung generisch benannter Komponenten. |
| 11 Gesamtqualität / Betrieb | Offen | CI, isolierte Tests und Betriebsdokumente vorhanden; vollständiger Sicherheits-, Migrations-, Backup-/Restore-, Datenschutz-, Accessibility- und Produktivnachweis fehlt. |

Die frühere [Funktionsmatrix](../analysis/feature-matrix.md) und der
[Umsetzungsplan](../analysis/implementation-roadmap.md) enthalten weitere
Detailanforderungen. Ihre damaligen Aussagen wie fehlende
Veranstaltungsdokumente sind nicht pauschal aktuell: beispielsweise
existieren inzwischen [Veranstaltungsdokument-Routen](../../src/Controller/Admin/ClubEventAdminController.php).
Einzelne dort genannte Teilfunktionen müssen vor weiterer Umsetzung neu
verifiziert werden.

## 5. Sicherheitsbefunde und Prioritäten

### S1 – Vereinsübergreifende Kurstermine sichtbar: hoch, gezielt behoben

Die effektiv registrierten Routen `/schedule/` und `/schedule/{id}` verwenden
weiterhin [ScheduleController](../../src/Controller/ScheduleController.php):
Zuvor luden `index()` und `calendar()` alle Termine; `show()` prüfte weder
Verein noch Kurssichtbarkeit. Die globale
[Security-Konfiguration](../../config/packages/security.yaml) verlangt für
`/schedule` lediglich `ROLE_MEMBER`. Das
[Detailtemplate](../../templates/schedule/show.html.twig) zeigt auch Notizen.
Ein temporärer isolierter HTTP-Probe-Test mit zwei synthetischen Vereinen,
aktivem Mitglied in Verein A, Termin und Notiz in Verein B sowie aktiviertem
Einzelvereinsmodus lieferte für Übersicht und direkte Detail-URL jeweils
HTTP 200 und fremden Inhalt: **1 Test, 6 Assertions**. Es wurden keine
Geschäftsdaten benutzt oder verändert. Der temporäre Probe-Test wurde
anschließend entfernt, da er das unerwünschte Verhalten bestätigt statt es
als Sollverhalten dauerhaft festzuschreiben.

Alle drei Lesepfade verwenden jetzt dieselbe vereins- und statusgebundene
Repository-Abfrage und verlangen ein aktives Mitglied mit Vereinszuordnung.
Auf ausdrückliche Entscheidung sind offene, laufende und abgeschlossene Kurse
sichtbar, Entwürfe und abgesagte Kurse nicht. Nicht erreichbare Details liefern
404. Im isolierten HTTP-Test werden beide Betriebsmodi und fremde,
unzugeordnete und nicht freigegebene Termine geprüft. Ob im realen Bestand
fremde oder vertrauliche Termine vorhanden sind, wurde nicht untersucht.

### S2 – Legacy-Kursupload im öffentlichen Webroot: hoch, gezielt abgesichert

Die effektive Route `/courses/upload` erreicht weiterhin
[CoursesController::upload](../../src/Controller/CoursesController.php).
Sie verlangte `ROLE_ADMIN`, prüfte aber keinen CSRF-Token, keine Größen- oder
Inhalts-Whitelist und keine Kurs-/Vereinsbindung; das Ziel
[`bilder_ordner`](../../config/services.yaml) liegt unter `public/images/kurse`.
Für harmlose Beispielinhalte liefert die MIME-Erkennung für HTML bzw. SVG die
Endungen `html` bzw. `svg`; die
[Apache-Regeln](../../public/.htaccess) lassen vorhandene Dateien grundsätzlich
direkt ausliefern. Damit besteht insbesondere ein Risiko aktiver Inhalte auf
derselben Origin, abhängig von Webserver und Browserkonfiguration.
Eine erfolgreiche Ausnutzung, PHP-Ausführung oder Umgehung der Adminrolle
wurde **nicht** getestet oder behauptet. `SameSite=Lax` ist vorhanden, ersetzt
aber keine explizite CSRF-Prüfung.

Der Endpunkt benötigt jetzt `ROLE_ADMIN`, `course_id`, einen Token mit der
ID `course_image_upload_<Kurs-ID>` und die hochgeladene Datei `file`.
Kurs-Voter und ausgewählter Verein werden serverseitig geprüft. JPG, PNG und
WebP sind bis 10 MiB und 40 Megapixel erlaubt; Dateityp und Bildkopf werden
geprüft, ein zufälliger Dateiname mit sicherer Endung vergeben. Bildvalidierung
wird mit [MediaFileStorage](../../src/Service/MediaFileStorage.php) geteilt.
Der Endpunkt liefert JSON mit `file`, ordnet das Bild aber **nicht** selbst
dem Kurs zu. Ein aktiver Aufrufer oder eine integrierte Oberfläche für diesen
neuen Vertrag wurde nicht nachgewiesen.

Die separaten aktiven EasyAdmin-Bildfelder für Kurse und Termine verwendeten
zuvor die EasyAdmin-Standardprüfung `image/*`, die auch SVG zulässt, und keine
vergleichbaren Größen- und Pixelgrenzen. Beide verwenden jetzt
[CourseImageField](../../src/Field/CourseImageField.php) mit JPEG/PNG/WebP,
10 MiB, 40 Millionen Pixeln und zufälligem Dateinamen. Das ändert die
akzeptierten Formate für neue Uploads; bestehende Bilder und URLs bleiben
unverändert. Die gemeinsamen Grenzen und ihre exakten Grenzwerte werden in
[CourseImageFieldTest](../../tests/Unit/CourseImageFieldTest.php) geprüft.
Die alten POST-Routen `/courses/new` und `/schedule/new` erreichen
laut effektivem Routing den Legacy-Controller und werden dort nicht als
Schreibvorgang ausgeführt.

Bei einer lesenden Prüfung der obersten Verzeichnisebene von
`public/images/kurse` wurden 16 Dateien gezählt; keine hatte eine andere als
die zugelassene Endung oder einen abweichenden MIME-/Bildkopf. Dateiinhalte
wurden nicht veröffentlicht oder verändert. Dies ist keine Prüfung von
Unterverzeichnissen, Polyglot-Dateien, Malware oder Produktionsbeständen;
die Validierung kodiert Bilder nicht neu. Rollen-, CSRF-, Verein-, Größen-
und Dateitypprüfungen des Legacy-Endpunkts werden isoliert getestet.

### S3 – Ungefilterte HTML-Ausgabe von Terminnotizen: hoch, Ausgabe abgesichert

Das [Detailtemplate](../../templates/schedule/show.html.twig) renderte
`schedule.notes` mit `|raw`. Ein Rich-Text-Feld existiert in der
[Terminverwaltung](../../src/Controller/Admin/ScheduleCrudController.php);
eine Bereinigung aller Speicher- und Altdatenpfade ist damit nicht belegt.
Die Ausgabe verwendet jetzt den konfigurierten HTML-Sanitizer; der HTTP-Test
prüft erhaltene Formatierung und entfernte Skript-/Eventhandler-Nutzlasten.
Altdaten werden dabei nicht verändert und wurden nicht vollständig untersucht.
Andere mögliche Ausgabepfade und die Herkunft gespeicherter Inhalte sind damit
nicht allgemein geprüft.

### Weitere Schutzmechanismen und Grenzen

- [InstallationGuard](../../src/Security/InstallationGuard.php) verlangt eine
  explizite Aktivierung und prüft einen persistenten Abschlussmarker.
  Bootstrap prüft die Benutzerzahl unter einer Sperre. Der Standalone-Test
  belegt diese begrenzten Eigenschaften, keinen vollständigen Setup-Pentest.
- [UserCrudController](../../src/Controller/Admin/UserCrudController.php)
  verlangt `ROLE_SUPER_ADMIN`; `User::getRoles()` leitet `ROLE_MEMBER` aus
  aktiver Mitgliedschaft ab. Der
  [CourseManagementVoter](../../src/Security/Voter/CourseManagementVoter.php)
  prüft für Kursbearbeitung auch Vereinskontext.
- [PrivateMemberDocumentStorage](../../src/Service/PrivateMemberDocumentStorage.php)
  und [MediaFileStorage](../../src/Service/MediaFileStorage.php) speichern
  private Dokumente außerhalb des Webroots und beschränken Dateitypen.
  Das belegt keine vollständige Prüfung aller Downloadrouten.
- Kein vollständiger Penetrationstest oder Nachweis der Abwesenheit weiterer
  kritischer Schwachstellen; die gezielten Korrekturen sind keine allgemeine
  Sicherheitsfreigabe.

## 6. Tests und Qualitätsprüfungen

Alle Ergebnisse beziehen sich auf den oben genannten Arbeitsbaum, nicht auf
ein freigegebenes Release.

| Prüfung | Ergebnis |
|---|---|
| `php -l` für PHP-Dateien in `src`, `config`, `migrations`, `tests` | bestanden |
| `php bin/console lint:twig templates --env=test` mit SQLite-URL | 253 Twig-Dateien bestanden |
| `node --test tests/js/team-member-sport-check.test.cjs` | 4 Tests bestanden |
| `php tests/translation_health.php` | fehlgeschlagen: Das [Prüfskript](../../tests/translation_health.php) baut die aktuellen Formulartypen nun mit benötigten Offline-Testdaten auf und meldet anschließend 169 fehlende Schlüssel/Hinweise im statischen Katalogcheck. Diese sind nicht einzeln fachlich bewertet. |
| Host-Basis-Gate mit isolierter SQLite-URL | nicht bestanden: Host-PHP 8.5.4 ohne `pdo_sqlite`; Mappingprüfung meldet fehlenden Treiber, PHPUnit 409 Tests, 1.019 Assertions, 11 Fehler und 321 übersprungen. Elf Fehler entstehen beim Cleanup von [MediaFileHttpTest](../../tests/Integration/MediaFileHttpTest.php), weil `$root` nach übersprungenem Setup nicht initialisiert ist. |
| DDEV-Basis-Gate, `PHP_BIN=php8.5`, SQLite-In-Memory, Null-Mailer | Nach 20:46 Minuten bewusst unterbrochen: 357 Tests, 8.535 Assertions bis zum Signal ohne gemeldeten Fehler; **keine vollständige Suite**. PHPUnit und Gate meldeten trotz Unterbrechung `OK` bzw. `PASS` und Exit 0: Diese Meldungen sind kein vollständiger Gate-Erfolg. YAML, Container, Routing, Doctrine-Mapping ohne Schemasynchronität und beide Standalone-Sperrtests bestanden. |
| DDEV-Basis-Gate nach den Änderungen, `PHP_BIN=php8.5`, SQLite-In-Memory, Null-Mailer | Vollständig bestanden: 401 Tests, 9.525 Assertions, 31:04 Minuten, ohne übersprungene oder unvollständige Tests. Routing, Composer, YAML, Container, Doctrine-Mapping ohne Schemasynchronität und Standalone-Sperrtests ebenfalls bestanden. Die Composer-Plattformprüfung lief mit DDEV-Standard-PHP 8.4.27; PHPUnit mit PHP 8.5.12. Staging-Prüfungen waren deaktiviert. |
| Wiederholtes DDEV-Basis-Gate nach der Blockerbearbeitung, `PHP_BIN=php8.5`, SQLite-In-Memory, Null-Mailer | Vollständig bestanden: **405 Tests, 9.587 Assertions**, 44 Minuten 29,729 Sekunden, ohne übersprungene oder unvollständige PHPUnit-Tests. Statische Routenprüfung, Composer, YAML, Container, Routing, Doctrine-Mapping ohne Schemasynchronität und beide Standalone-Sperrtests bestanden. Composer-Plattformprüfung mit DDEV-Standard-PHP 8.4.27, PHPUnit mit PHP 8.5.12; Staging-Prüfungen waren in diesem Lauf deaktiviert. Der nach Beginn dieses Laufs hinzugefügte Controller-Bildfeldtest wurde separat geprüft. |
| DDEV: `SingleClubModeHttpTest`, `SingleClubReassignmentTest`, `CourseManagementVoterTest` | gezielter separater Lauf bestanden: 20 Tests, 69 Assertions, keine übersprungenen oder unvollständigen Tests |
| Sicherheits-HTTP-Tests | Nach den letzten Ergänzungen bestanden: 3 Tests, 132 Assertions, beide Vereinsmodi. |
| Sicherheits- und Medien-HTTP-Tests | Vor den letzten Ergänzungen für Gäste und ein defektes Bild bestanden: 14 Tests, 500 Assertions. |
| MariaDB-Migrationssuite auf `db_test` | Erster vollständiger Lauf: 36 Tests, 288 Assertions, 3 Fehler. Zwei Konkurrenztests verwendeten einen veralteten Konstruktor für `BarDutyAccess`; der historische Rückrolltest behandelte neuere ausdrücklich irreversible Migrationen fälschlich als reversibel. Nach Anpassung von Testfixture und Liste bestanden die drei betroffenen Fälle mit 20 Assertions und der vollständige Wiederholungslauf mit **36 Tests, 302 Assertions**, ohne übersprungene oder unvollständige Tests. An bestehenden Migrationen wurde nichts geändert. |
| Browser | bestehende Adminübersicht lesend betrachtet; keine Aktionen, Rollenmatrix oder responsive Vollprüfung |

Der Abbruchschutz wurde mit simulierten PHPUnit-Aufrufen geprüft, die trotz
Abbruchmeldung Exit 0 zurückgeben. Die dauerhaften Tests
[AcceptanceGateTest](../../tests/Unit/AcceptanceGateTest.php) prüfen dies
für Basis- und Staging-Suite; beide melden `FAIL` und Exit 1. Zusätzlich
wurde der tatsächlich laufende PHPUnit-Prozess des Basis-Gates gezielt mit
SIGTERM beendet: Das Gate meldete `FAIL` und Exit 1, und Gate und
PHPUnit-Hauptprozess waren anschließend beendet. Dies ist kein vollständiger
Test sämtlicher Signalzeitpunkte und Prozessbäume.

### Fachliche Testpfade

Die folgenden vorhandenen Tests beschreiben konkrete Abläufe, sind aber keine
vollständige fachliche Abnahme aller Anforderungen. Ein erfolgreicher Lauf ist
jeweils nur mit dem Ergebnis der zugehörigen Suite belegt:

| Bereich | Beispiele isolierter HTTP-Szenarien |
|---|---|
| Mitglieder | Antrag und manuelle Freigabe, Vereinsgrenzen, Kündigung, Beiträge und Zahlungen: [Phase3MembershipHttpTest](../../tests/Integration/Phase3MembershipHttpTest.php). |
| Ausbildung | Gastanmeldung, Rollen-/Vereinsgrenzen, Fristen, Abschluss und Archiv: [Phase4TrainingHttpTest](../../tests/Integration/Phase4TrainingHttpTest.php). |
| Mannschaften | Training, Turnier, Tabellen, Rollen-/Vereinsgrenzen und Dokumente: [Phase5CompetitionHttpTest](../../tests/Integration/Phase5CompetitionHttpTest.php). |
| Geräte | Freigaben, Wartung, Verleih, Prüfungen und Dokumente: [Phase6EquipmentHttpTest](../../tests/Integration/Phase6EquipmentHttpTest.php). |
| Dienste/Kasse | Planung, Überschneidungen, Zuordnung, Kassenabschluss und Wiederholung fehlgeschlagener Benachrichtigungen: [Phase8BarDutyHttpTest](../../tests/Integration/Phase8BarDutyHttpTest.php). |
| Veranstaltungen/Website | Buchung, Warteliste, Zahlungen und Veröffentlichung: [ClubEventBookingHttpTest](../../tests/Integration/ClubEventBookingHttpTest.php), [WebsitePublishingHttpTest](../../tests/Integration/WebsitePublishingHttpTest.php). |

Die Prüfungen nutzen synthetische Daten und isoliertes SQLite; sie belegen
weder sämtliche Benutzerrollen noch echte Konkurrenzbedingungen auf MariaDB,
externe Zustellung oder fachliche Abnahme mit realen Daten.

Basis-Gate:

```sh
ddev exec env DATABASE_URL='sqlite:///:memory:' MAILER_DSN='null://null' \
  DICOMA_RUN_STAGING_DB_CHECKS=0 DICOMA_RUN_STAGING_HTTP_TESTS=0 \
  PHP_BIN=php8.5 bash scripts/phase2_acceptance_gate.sh
```

Der separate gezielte Lauf wurde mit folgendem Befehl ausgeführt:

```sh
ddev exec env DATABASE_URL='sqlite:///:memory:' MAILER_DSN='null://null' \
  php8.5 vendor/bin/phpunit tests/Integration/SingleClubModeHttpTest.php \
  tests/Unit/SingleClubReassignmentTest.php \
  tests/Security/Voter/CourseManagementVoterTest.php \
  --no-progress --fail-on-skipped --fail-on-incomplete
```

CI führt das [Basis-Gate](../../scripts/phase2_acceptance_gate.sh) auf
PHP 8.4 und 8.5 aus; sie beweist keine MariaDB-Migrationskette oder
Staging-Schreibtests. DDEV-Standard-PHP war 8.4.27; die separate PHP-8.5-
Laufzeit war 8.5.12 mit SQLite-Treiber. Das Host-Ergebnis wurde nicht
nachträglich als Erfolg gewertet. Tests der Migrationskette können
Testdatenbanken anlegen und löschen; ihre separate Ausführung wurde für
`db_test` ausdrücklich genehmigt. Die Staging-Suite enthält Schreibtests;
der spätere Lauf im gesonderten Wegwerfsystem ist nachstehend dokumentiert.

### Nachträgliche isolierte Staging-Prüfung

Auf ausdrückliche Freigabe wurde eine neue MariaDB in einem eigenen
Docker-Netz und eine getrennte Anwendungskopie mit synthetischen Testdaten
verwendet. Der Testcontainer war ausschließlich mit diesem Netz verbunden,
die Datenbank dort als `db_test` erreichbar. Der tatsächlich verbundene
Server und der Datenbankname sowie die einzige Einbindung der isolierten
Anwendungskopie wurden vor dem Beschreiben geprüft. Vorhandene DDEV-Datenbanken
und echte Uploads wurden nicht als Testziel verwendet; der Mailer war
deaktiviert.

Auf der leeren Datenbank liefen **141 Migrationen**; Doctrine meldete
korrektes Mapping, synchrones Schema und keine offenen Migrationen. Zwei
Vereine, Rollen, Kurse, Termine, Buchungen und gesonderte Wegwerf-Mitglieder
wurden synthetisch angelegt; die für die Tests vorausgesetzten IDs und
Vereinszuordnungen wurden überprüft.

Die vollständige bestehende PHPUnit-Suite `staging` wurde mit beiden
erforderlichen Testfreigaben, `--fail-on-skipped` und
`--fail-on-incomplete` ausgeführt: **32 Tests, 920 Assertions, 9 Fehlschläge**,
keine gemeldeten übersprungenen oder unvollständigen Tests. Das Gate ist
damit **nicht bestanden**. Die Fehlschläge betreffen:

| Prüfungen | Beobachtung und Einordnung |
|---|---|
| Drei Buchungs-Isolationstests | Die alten Bearbeitungsrouten liefern GET 303 bzw. POST 410 statt der erwarteten 403/200. Die [Legacy-Routen](../../config/routes/legacy_administration.php) und der [Legacy-Controller](../../src/Controller/Admin/LegacyAdministrationController.php) leiten sichere Aufrufe um und nehmen Schreibaufrufe nicht mehr an. Dadurch werden insbesondere die bisherigen manipulierten Formular- und gültigen CSRF-Schreibversuche nicht bis zum Ende geprüft. |
| Vier Tests zu Superadmin-Zugriff auf Kurse, Mitglieder und Teilnahmen | Erwartet wird Zugriff auf beide Vereine ohne Wechsel; die getesteten Backend-Voter begrenzen den Zugriff auf den ausgewählten Verein. Bei den synthetischen Fixtures ist zunächst Verein A ausgewählt. Die Tests müssen an den gewünschten Auswahlprozess angepasst und vollständig wiederholt werden. |
| Zwei Superadmin-Tests für Lieferanten und TÜV-Preisartikel | Die alten Indexrouten antworten mit 303 auf die kanonischen Adminrouten, die Tests erwarten unmittelbar 2xx. Ob die gesamte jeweilige Zielaktion fachlich korrekt arbeitet, ist dadurch nicht bewiesen. |

Die neun betroffenen Testmethoden sind:

- [Phase2BookingIsolationHttpTest](../../tests/Http/Phase2BookingIsolationHttpTest.php):
  `testForeignBookingShowAndEditAreForbidden` (303 statt 403).
- [Phase2BookingWriteIsolationHttpTest](../../tests/Http/Phase2BookingWriteIsolationHttpTest.php):
  `testForeignEditPostAndValidCsrfDeleteCannotModifyOtherClub` (410 statt 403)
  und `testForgedAssociationsOnOwnEditAndNewCannotBePersisted`
  (303 statt 200).
- [Phase2ClubIsolationHttpTest](../../tests/Http/Phase2ClubIsolationHttpTest.php):
  `testSuperAdminCanReadBothAndOrdinaryUserCannot` (403 statt 2xx).
- [Phase2CourseParticipantIsolationHttpTest](../../tests/Http/Phase2CourseParticipantIsolationHttpTest.php):
  `testParticipantDetailAndEditRespectClubBoundaries` (403 statt 2xx).
- [Phase2MemberIsolationHttpTest](../../tests/Http/Phase2MemberIsolationHttpTest.php):
  `testSuperAdminCanReadBothAndOrdinaryUserCannot` (403 statt 2xx).
- [Phase2MemberValidEditHttpTest](../../tests/Http/Phase2MemberValidEditHttpTest.php):
  `testForeignMemberRejectsValidFormSubmission` (403 statt 2xx bei der
  Vorbereitung des Formulars).
- [Phase2TankCheckArticleAuthorizationHttpTest](../../tests/Http/Phase2TankCheckArticleAuthorizationHttpTest.php):
  `testSuperAdminCanReadTankCheckArticleIndex` (303 statt 2xx).
- [Phase2VendorAuthorizationHttpTest](../../tests/Http/Phase2VendorAuthorizationHttpTest.php):
  `testSuperAdminCanReadVendorAdministration` (303 statt 2xx).

Ein zusätzlicher temporärer Diagnosetest bestand mit **2 Tests und
31 Assertions**: Nach tatsächlichem Wechsel über das CSRF-geschützte
Vereinsauswahlformular konnte der Superadmin die Mitglieder- und
Kurshistorien des ausgewählten Vereins öffnen, die des anderen nicht; beim
Wechsel zu beiden Vereinen wurde dies jeweils geprüft. Für beide Vereinsadmins
führten alte fremde Buchungs-Edit-GETs auf ein erfolgreiches allgemeines Ziel
ohne fremde ID, die POSTs lieferten 410 und die Buchungszeilen blieben
unverändert. Dies belegt weder die kanonischen Schreibformulare noch alle
Rollen und Buchungsaktionen. Die Testannahmen und das beabsichtigte
Fachverhalten sind vor einer Änderung der Tests oder Anwendung gesondert
abzustimmen; die ursprünglichen Tests wurden nicht auf Grün angepasst.

Es handelt sich um Symfony BrowserKit gegen MariaDB im lokalen Wegwerfsystem,
nicht um eine bereitgestellte Staging-Installation mit echtem Netzwerk,
TLS, Reverse Proxy, Scheduler, Mailzustellung oder produktionsnahem Bestand.
Die technische Zielsystemabnahme und fachliche Gesamtabnahme bleiben offen.

### Wiederholung nach Anpassung der Staging-Prüfungen

Eine erneut angelegte MariaDB ohne Host-Port und eine separate Anwendungskopie
liefen ausschließlich in einem eigenen Docker-Netz. Serveridentität,
`db_test` und leere Zieltables wurden vor Schreibvorgängen überprüft;
141 Migrationen, Schemasynchronität und Migrationsstand bestanden. Die
synthetischen Zwei-Vereins-Fixtures wurden neu angelegt und ihre IDs geprüft.
Die bestehende DDEV-Anwendungs- und Testdatenbank waren nicht Testziel.

Die neun fehlschlagenden Prüfungen wurden an die aktiven Legacy-Weiterleitungen,
die Superadmin-Vereinsauswahl und die kanonischen Routen angepasst. Die
Buchungstests prüfen jetzt auch die Ablehnung kanonischer Schreibversuche
mit echten Form- bzw. Lösch-CSRF-Tokens und vergleichen die Datenbankzeilen;
die Superadmin-Tests prüfen nach jedem Vereinswechsel sowohl den erlaubten
als auch den fremden Verein. Die vollständige Staging-Suite bestand danach
mit **32 Tests und 1.085 Assertions**, ohne übersprungene oder unvollständige
Tests. Zusätzliche temporäre HTTP-Tests mit **2 Tests und 42 Assertions**
bestätigten, dass beide tatsächlichen EasyAdmin-Bildformulare SVG ablehnen
und keine Bilddatei oder Bildreferenz speichern sowie gültiges PNG mit
zufälligem Dateinamen speichern.

Gezielt bestanden außerdem der Bildregel- und Abbruchschutztest mit
**4 Tests und 64 Assertions**, die Sicherheits-HTTP-Tests mit **3 Tests und
132 Assertions**, die Medien-HTTP-Tests mit **11 Tests und 372 Assertions**
und die Symfony-Containerprüfung. Der nach Beginn des Basis-Gates ergänzte
[Controller-Bildfeldtest](../../tests/Integration/CourseImageControllerFieldsTest.php)
bestand separat mit **1 Test und 29 Assertions** und prüft die tatsächlichen
Feldkonfigurationen beider Controller für neue und bearbeitete Datensätze,
ohne Bilder hochzuladen. Er ist für künftige Basisläufe registriert.
Ohne `pdo_sqlite` werden die elf
Medientests auf dem Host nun sauber übersprungen statt im Cleanup mit elf
Fehlern abzubrechen; sie sind dort nicht bestanden.

Der Übersetzungsprüfer verwendet bei statischer Inspektion und anschließendem
Rendering nun dieselben Form-Fixtures. Mit ausschließlich synthetischen
Platzhaltern für die fehlenden Katalogschlüssel lief in der Testkopie auch
der restliche Prüfer durch: 44 Formulartypen, vier Sprachkataloge und
JavaScript-/Editor-Prüfungen. Diese Platzhalter wurden nicht in die Anwendung
übernommen und sind keine Übersetzungen. Der unveränderte reale Katalogcheck
meldet weiterhin **169 Fundstellen zu 156 Schlüsseln**, die im geprüften
englischen PHP-Katalog fehlen. Ob die einzelnen Schlüssel an anderer Stelle
übersetzt oder fachlich überhaupt übersetzbar sind, ist nicht vollständig
bewertet; die Übersetzungs- und fachliche Qualitätsprüfung ist damit nicht
bestanden.

### Lokale Sicherung und Wiederherstellung

Nach ausdrücklicher Genehmigung wurden die lokale DDEV-Datenbank sowie
`var/private` und `public/images/kurse` geschützt lokal gesichert und
ausschließlich in einer separaten Testumgebung wiederhergestellt. Auf der
Testdatenbank stimmten 108 Basistabellen-Prüfsummen mit der Quelle überein;
die wiederhergestellten Dateien stimmten ebenfalls überein. Ohne
Wartungsfenster ist keine konsistente gemeinsame Datenbank-/Datei-Zeitaufnahme
unter gleichzeitigen Schreibzugriffen belegt. Die geschützte Sicherung kann
personenbezogene Daten enthalten und ist kein Produktions-Backup-Nachweis.
Die eigens angelegte Restore-Datenbank wurde nach der Prüfung wieder entfernt;
die geschützten Sicherungsarchive bleiben erhalten. Nach den Migrationsläufen
waren auch keine Datenbanken mit dem Testpräfix `dicoma_migration_test_`
mehr auf `db_test` vorhanden.

## 7. Risiken, Inbetriebnahme und aktualisierter Plan

1. **Sicherheit vor Nutzung:** S1–S3 gezielt korrigiert; Altdateien,
   Datenherkunft, weitere Ausgabepfade und umfassende Rollen- und
   Objektprüfungen bleiben offen. Keine pauschale Produktivfreigabe.
2. **Prüfbasis stabilisieren:** Das DDEV-Basis-Gate und die isolierte
   Staging-Suite wurden nach der Blockerbearbeitung vollständig wiederholt;
   der nachträglich ergänzte Controller-Bildfeldtest bestand separat.
   Der Abbruchschutz wurde für beide Suiten simuliert und für den
   Basis-PHPUnit-Prozess mit SIGTERM geprüft. Test-Cleanup und
   Form-Fixtures des Übersetzungsprüfers wurden korrigiert; die fehlenden
   Übersetzungen bleiben offen. Kein grünes Ergebnis aus übersprungenen oder
   unterbrochenen Tests ableiten.
3. **Datenverträglichkeit:** Lokale Backup-/Restore-, Migrations- und einzelne
   Parallelitätsprüfungen wurden isoliert ausgeführt. Vor Änderungen anderer
   Zielsysteme deren Bestand, konsistentes Backup, Wiederherstellungsprobe und
   relevante Upgrade-/Parallelitätspfade gesondert prüfen. Keine automatische
   Migration oder Datenbereinigung.
4. **Architekturphase 1:** nach ausdrücklichem Start bestehende Architektur
   und Autorisierungsgrenzen schrittweise konsolidieren; keine pauschale
   Komplettumstrukturierung. Sicherheitsblocker sind zuvor oder ausdrücklich
   innerhalb eines freigegebenen Arbeitsumfangs zu behandeln.
5. **Phasen 2–11:** bestehende Module anhand der Tabelle erneut prüfen,
   nur belegte Lücken ergänzen; Sparten-Features, rollenbezogene
   Objektprüfungen, Kommunikation, Betriebs- und Fachabnahme priorisieren.

Die Nachprüfung enthält Code- und Teständerungen, jedoch keine neuen Entities,
Schemaänderungen oder Migrationen auf der Anwendungsdatenbank.
Eine Produktivsetzung ist nicht freigegeben. Für den laufenden Arbeitsbaum
sind insbesondere offene Sicherheitsbefunde, fehlender vollständiger
Anforderungsabgleich und fehlende Zielsystemprüfung festzuhalten.

## 8. Abnahme

| Kriterium | Status / Nachweis |
|---|---|
| Nachvollziehbare, quellenbasierte Bestandsaufnahme | Teilweise: Inventar, Modulmatrix und ausgewählte Code- und Laufzeitnachweise hier vorhanden; nicht alle Einzelanforderungen/-dateien geprüft. |
| Kritische Sicherheitsprobleme behoben oder blockierend gekennzeichnet | S1–S3 gezielt bearbeitet; Sicherheitsprüfung nicht vollständig und keine pauschale Sicherheitsfreigabe. |
| Technische Tests | Das wiederholte Basis-Gate bestand mit 405 Tests und 9.587 Assertions; der nach Beginn hinzugefügte Controller-Bildfeldtest bestand separat mit 1 Test und 29 Assertions. Die isolierte Staging-Suite bestand mit 32 Tests und 1.085 Assertions, die frühere Migrationssuite mit 36 Tests und 302 Assertions. Der reale Übersetzungscheck bleibt rot. |
| Fachliche Abnahme | Offen; keine fachliche Gesamtbestätigung. Die abschließende fachliche Freigabe erfolgt durch die Auftraggeberseite. |
| Dokumentation und nächste Schritte | Dieses Dokument; Start der nächsten Phase ausschließlich nach ausdrücklichem Befehl. |

**Empfehlung:** Die konkret reproduzierten S1–S3 und der nachträglich
festgestellte SVG-Uploadpfad wurden gezielt abgesichert; die isolierte
Staging-Suite besteht. Entwicklungsarbeit an Phase 1 kann nach ausdrücklichem
Start auf dieser isolierten Grundlage vorbereitet werden, ohne dass dies eine
Gesamt- oder Produktivfreigabe bedeutet. Phase 0 bleibt für die verlangte
vollständige Einzelprüfung aller Anforderungen und Controller sowie die
fachliche Abnahme unvollständig. Insbesondere Übersetzungen, weitere Rollen-
und Objektprüfungen, ein konsistentes Backup unter Schreiblast und die
Prüfung des tatsächlichen Zielsystems bleiben offen und sind vor
entsprechender Produktivnutzung gesondert nachzuweisen. Phase 1 wird nicht
automatisch begonnen. Die Auftraggeberseite hat zunächst ausschließlich die
Feststellung der isolierten Entwicklungsbereitschaft gewählt; die Prüfung des
tatsächlichen Zielsystems bleibt zurückgestellt.
