# Analyse des Referenzrepositorys `diversworld/contao-diveclub-bundle`

**Stand:** 2026-10-15  
**Quellenbasis:** bereitgestelltes Archiv `contao-diveclub-bundle-main.zip` (268 Einträge); ein zusätzlicher `git clone` war in der Ausführungsumgebung wegen fehlender DNS-/Netzwerkauflösung für `github.com` nicht möglich. Aussagen zu implementiertem Verhalten beziehen sich daher auf den Archivstand, nicht auf einen verifizierten aktuellen Remote-HEAD.

## 1. Architektur und Lizenz

Das Referenzprojekt ist ein Contao-Bundle, kein eigenständiges Symfony-Projekt. `composer.json` nennt `diversworld/contao-diveclub-bundle`, Typ `contao-bundle`, PHP `^8.3`, Contao Core/Calendar `^5.7 || ^6.0`, TCPDF und FPDI. Der Quellcode nutzt Contao DCA-Konfigurationen, Contao-Backend-/Frontend-Module, Contao-Kalender-Events, Insert-Tags und Contao-Dateiverwaltung. Eine eigenständige Symfony-Anwendung sollte diese Integrationspunkte nicht übernehmen.

Die Lizenz im Referenz-`composer.json` ist **GPL-3.0-or-later**. Das erlaubt grundsätzlich Weitergabe und Bearbeitung unter den GPL-Bedingungen, einschließlich Lizenz- und Copyright-Hinweisen sowie Quellcodepflichten bei Verteilung eines abgeleiteten Werks. Ob konkrete Dateien oder Drittinhalte zusätzliche Bedingungen tragen, ist einzeln zu prüfen. Dieses Patchpaket übernimmt keine Quellcodedateien aus dem Referenzprojekt; es dokumentiert beobachtete fachliche Funktionen und entwirft eine neue Architektur. Vor späterer Codeübernahme ist eine Lizenzprüfung der jeweiligen Datei und ihrer Herkunft erforderlich. Keine Rechtsberatung.

## 2. Untersuchte Artefakte

Das Archiv enthält `README.md`, `TEMPLATES.md`, Wiki-Dokumente unter `docs/wiki/`, `composer.json`, Contao-Konfigurationen und Services, DCA-Dateien, PHP-Klassen unter `src/`, Twig-Templates sowie Export-/PDF-bezogene Funktionen. In der Inventur wurden 90 PHP-Dateien unter `src/` und 20 `tl_dc_*`-DCA-Dateien gezählt. Ein belastbarer Teststatus des Referenzprojekts lässt sich aus dem bereitgestellten Archiv allein nicht ableiten.

### Relevante DCA-Tabellen

| Tabelle | In den Referenzunterlagen beschriebener Zweck | Zielbereich |
|---|---|---|
| `tl_dc_tanks` | Tauchflaschen, Prüfdaten und Status | Equipment, Inspection |
| `tl_dc_regulators` | Atemregler und Revisionsinformationen | Equipment |
| `tl_dc_regulator_control` | Regler-Kontrollkarten/-Protokolle | Equipment, Inspection |
| `tl_dc_equipment` | allgemeine Ausrüstung | Equipment |
| `tl_dc_reservation` | Reservierungskopf | Rental |
| `tl_dc_reservation_items` | Reservierungspositionen | Rental |
| `tl_dc_check_proposal` | TÜV-/Prüfangebote bzw. Sammeltermine | Inspection |
| `tl_dc_check_booking` | Mitgliederbuchungen für Prüfungen | Inspection |
| `tl_dc_check_order` | einzelne Flaschen/Aufträge innerhalb einer Buchung | Inspection |
| `tl_dc_check_articles` | Preis-/Rechnungspositionen | Inspection, Billing |
| `tl_dc_config` | zentrale Bundle-Konfiguration | Administration |
| `tl_dc_dive_course` | Kursstammdaten | außerhalb des primären DiveClub-Manager-Scopes |
| `tl_dc_course_modules` | Kursmodule | außerhalb des primären Scopes |
| `tl_dc_course_exercises` | Kursübungen | außerhalb des primären Scopes |
| `tl_dc_course_event` | konkrete Kursveranstaltung | optional spätere Membership/Training-Erweiterung |
| `tl_dc_course_event_schedule` | Zeitplan eines Kursereignisses | optional spätere Erweiterung |
| `tl_dc_event_schedule_exercises` | Übungen im Zeitplan | optional spätere Erweiterung |
| `tl_dc_students` | Tauchschüler | optional spätere Erweiterung |
| `tl_dc_course_students` | Teilnehmerzuordnung | optional spätere Erweiterung |
| `tl_dc_student_exercises` | Übungsfortschritt | optional spätere Erweiterung |

Contao-Kalendertabellen `tl_calendar`, `tl_calendar_events` und `tl_module` werden laut Dokumentation erweitert bzw. verknüpft. Sie werden nicht als neue Symfony-Tabellen übernommen; Termine werden als eigenständige `InspectionEvent`-Entität modelliert.

**Wichtige Einschränkung:** `docs/wiki/TABLES.md` ist eine Zweckübersicht, keine vollständige SQL-Spezifikation. Vor einer Datenmigration müssen die DCA-Felddefinitionen, Datenbankspalten, Relationen, Mehrfachwerte, Contao-Datei-UUIDs und tatsächlich vorhandenen Produktionsdaten gesondert ausgelesen werden.

## 3. Fachprozesse und Umsetzungsstand

Die nachfolgende Einstufung trennt Dokumentation von nachgewiesener Implementierung. Ein DCA-Eintrag belegt ein Backend-Datenmodell, aber allein keine vollständige Prozessimplementierung.

| Fachfunktion | Dokumentierter Ablauf/Funktion | Im Archiv nachgewiesene Implementierungsartefakte | Einstufung |
|---|---|---|---|
| Flaschenverwaltung | Seriennummer, Größe, letzte/nächste Prüfung bzw. Fälligkeit | `tl_dc_tanks`, `src/`-Klassen und Frontend-/Backend-Templates rund um Tank/Check | Datenmodell und Prozesscode vorhanden; vollständige Regelabdeckung nicht allein aus Dokumentation ableitbar |
| Atemregler | erste/zweite Stufe, Revisionen/Kontrollkarten | `tl_dc_regulators`, `tl_dc_regulator_control` | Datenmodell vorhanden; Details und Vollständigkeit per Code-/Datenbankprüfung weiter zu verifizieren |
| Allgemeine Ausrüstung | Artikel wie Jackets und Anzüge | `tl_dc_equipment` | Datenmodell vorhanden |
| Verleih | Zeitraum-/Artikelreservierung, Abholung, Rückgabe, Status, Gebühren und Historie | `tl_dc_reservation`, `tl_dc_reservation_items`; README/Wiki beschreibt Frontend-Anfrage und Backend-Abwicklung | dokumentierter Ablauf; atomare Parallelitätskontrolle, Teilrückgabe und unveränderliche Historie sind nicht durch die Wiki-Texte belegt |
| Flaschenprüfung | Angebot/Termin, Anmeldung, Preispositionen, Export und PDF-Rechnung | `tl_dc_check_proposal`, `tl_dc_check_booking`, `tl_dc_check_order`, `tl_dc_check_articles`; Wiki beschreibt Ablauf | zentrale Bausteine dokumentiert; Kapazitäts- und Konfliktsicherheit sowie Preis-Snapshots müssen im Quellcode gezielt auditiert werden |
| Exporte | PDF, CSV und XLSX für Prüflisten werden dokumentiert | Composer hängt TCPDF/FPDI ein; Wiki benennt die Formate | dokumentiert; alle Exportpfade und Randfälle nicht durch Testlauf bestätigt |
| Konfiguration | Vorlagen, Rechnungstexte, Speicherordner, Exportformat, Reservierungsmails und Verleihbedingungen | `tl_dc_config` | Konfigurationsdatenmodell dokumentiert |
| E-Mail | Benachrichtigungen und konfigurierbare Tokens, teils optionale Notification-Center-Integration | README dokumentiert Tokens; `terminal42/notification_center` ist nur `suggest` | bestimmte Benachrichtigungsfunktionen dokumentiert, optionale Integration nicht als immer vorhanden anzunehmen |
| Kurse/Ausbildung | Kurse, Module, Übungen, Veranstaltungen, Schüler und Fortschritt | mehrere Kurs-DCA-Dateien und Templates | vorhanden, aber nicht Kern des hier definierten Geräte-/Prüfungs-/Verleihumfangs |

### Beobachtete Statuswerte aus der Verleihdokumentation

Die Wiki-Dokumentation nennt `available`, `reserved`, `borrowed`, `returned`, `overdue`, `damaged`, `lost`, `missing` und `cancelled`. Für die Zielanwendung werden diese Werte nicht zu einem einzigen Statusfeld zusammengelegt: Reservierungsstatus, tatsächliche Ausleihe, technischer Gerätezustand und physischer Aufenthaltsort sind getrennte Zustandsdimensionen.

### Beobachtete Integrations-/Darstellungsmechanismen

- Contao DCA für Backend-Masken und Tabellenkonfiguration.
- Contao Frontend-Module, darunter `dc_booking` und die Flaschenprüfungsanmeldung.
- Contao-Kalender-Verknüpfung für Termine.
- Twig-Templates und Contao Insert-Tags für Buchungs-/Kursdaten.
- Konfigurationswerte aus `tl_dc_config`.
- PDF-/Exportfunktionalität; konkrete Datenfelder und Formatdetails müssen anhand der jeweiligen Implementierung validiert werden.

## 4. Wiederverwendbare fachliche Regeln

Als zu übernehmende **fachliche Anforderungen**, nicht als ungeprüft kopierbare Implementierung, ergeben sich:

1. Geräte sind nach Typen zu unterscheiden (Flasche, Atemregler, allgemeine Ausrüstung).
2. Flaschenprüfung ist ein Termin-/Angebotsprozess mit mehreren Flaschen je Buchung.
3. Prüfpositionen und Zusatzleistungen haben eigene Preise.
4. Verleih besteht aus Reservierung, Ausgabe und Rückgabe; die Historie gehört zum Mitglied und Gerät.
5. Geräteverfügbarkeit muss vom technischen Zustand abhängen.
6. Prüflisten und Abrechnungsbelege benötigen reproduzierbare Exporte.
7. Konfigurierbare Vorlagen, Texte und Benachrichtigungen sind fachlich sinnvoll.
8. Prüf- und Wartungshistorie darf bei einer neuen Aktion nicht einfach überschrieben werden.

Die Punkte 1–7 sind aus den Referenzdokumenten abgeleitete Fachfunktionen. Atomare Buchung, halb offene Intervalle, idempotente Nachrichten, Preis-Snapshots und unabhängige Statusdimensionen sind zusätzliche Zielanforderungen aus dem Projektauftrag, keine Behauptung über die Contao-Implementierung.

## 5. Abweichungen zur Zielanwendung

| Contao-Referenz | Ziel-Symfony |
|---|---|
| DCA und Contao-Backend | Doctrine-Entities, Symfony Forms, EasyAdmin |
| Contao-Frontend-Module | Symfony-Controller, Voters, Twig, UX/Stimulus |
| Kalender-Event als Verknüpfung | eigenständiges `InspectionEvent` mit Kapazität und Frist |
| zentrale Contao-Konfigurationstabelle | typisierte Konfiguration/Settings und Umgebungsvariablen für Infrastruktur |
| implizite Status-/Feldkombinationen möglich | getrennte Statusmaschinen für Buchung, Einzelprüfung, Standort, Zahlung und Gerätezustand |
| Contao-Dateiablage | kontrollierter Dokumentenspeicher mit Metadaten, Zugriffsschutz und Aufbewahrungsregeln |
| vorhandene Kursverwaltung | außerhalb des MVP; bestehende Kursdaten werden nicht automatisch verworfen |

## 6. Technische Ausgangslage `dicoma1510`

Das bereitgestellte Zielprojekt ist bereits eine Symfony-Anwendung mit Symfony Framework **7.4.20** laut `composer.lock`, Doctrine ORM **2.20.13**, Doctrine Migrations Bundle, EasyAdmin **4.29.16**, Security, Validator, Forms, Twig, Mailer und Reset-Password-Bundle. Die Composer-Datei deklariert aktuell `php >=8.2` und Symfony `^7.0`. Damit ist ein Teil der Phase-2-Grundlage vorhanden; das Projekt ist aber ursprünglich ein Dive Course Manager mit Kurs-/Mitglieder-/Ausrüstungsmodellen, nicht bereits der vollständige angeforderte DiveClub Manager.

Die geforderte PHP-Untergrenze 8.4 ist noch nicht im Composer-Vertrag umgesetzt. Symfony 7.4 ist die gewählte LTS-Linie, jedoch müssen die konkrete Laufzeit, die Symfony-Komponenten, PHPUnit, PHPStan, CS-Fixer, Messenger/Workflow/UX sowie PostgreSQL-PDO gemeinsam in der Zielumgebung getestet werden, bevor ein Upgrade als kompatibel gilt.

## 7. Offene Punkte, die keine stillschweigende Annahme erlauben

1. **Prüfintervalle und Prüfregeln:** Welche Fristen gelten pro Flaschenart, Material, Land und Prüforganisation? Wer darf ein offizielles Ergebnis eintragen/freigeben?
2. **Steuer/Preise/Rechnungen:** Netto-/Bruttopreis, Steuersätze je Leistung, Rundung, Rechnungskorrekturen, Rechnungsnummernkreis, Kleinunternehmerregelung und gesetzliche Pflichtangaben müssen vom Verein bzw. Steuerberater festgelegt werden.
3. **Kapazität:** Bezieht sich die Kapazität auf Buchungen, Flaschen, Gewicht/Volumen oder die verfügbare Kapazität der Prüfstelle?
4. **Konfliktdefinition:** Wann kollidieren Prüfaufträge für dieselbe Flasche (Status, Zeitfenster, Stornierung, Nachprüfung)?
5. **Verleihkonditionen:** Stornierungsfrist, Pufferzeiten, Gebühren, Kaution, Verspätungsregeln und zulässige Teilrückgabe.
6. **Eigentum/Mitglieder:** Wie werden bestehende Mitglieds-IDs, inaktive Mitglieder, Geräte im Vereinseigentum und private Geräte migriert?
7. **Aufbewahrung/Datenschutz:** Fristen für Rechnungen, Prüfprotokolle, Fotos, Audit-Logs und Lösch-/Anonymisierungsanträge müssen pro Datenklasse konfiguriert und rechtlich geprüft werden.
8. **Datenmigration:** Es liegt kein Datenbankdump vor. Feldgenaues Mapping und Datenqualitätsbericht sind vor Migration mit anonymisiertem Schema-/Datenexport erforderlich.

## 8. Nächste überprüfbare Schritte

Die konkrete Roadmap und Abnahmekriterien stehen in `docs/implementation-plan.md`. Die Umsetzung sollte mit Infrastruktur und Domain-Tests beginnen, anschließend einen vollständigen vertikalen Schnitt für Geräte/Prüfung/Verleih liefern. Ein Gesamtumfang dieser Größe kann nicht seriös mit einem einzigen Patch als fertig oder produktionsreif bezeichnet werden.
