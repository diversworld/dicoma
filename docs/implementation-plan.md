# DiveClub Manager – Implementierungsplan und Abnahmekriterien

## Architekturziel

Modularer Monolith unter `App\DiveClub\{Identity,Membership,Equipment,Inspection,Rental,Billing,Notification,Administration}`. Jeder Fachbereich trennt `Domain`, `Application`, `Infrastructure` und `UI` (Controller/Form/Twig). Gemeinsame Identität bleibt in `App\Entity\User`/bestehenden Membership-Modellen, bis eine geprüfte Migration eine Ablösung erlaubt. Bestehende Kursverwaltung bleibt intakt.

## Phase 1 – Analyse und Architektur (dieser Patch)

- [x] Inventur des bereitgestellten Contao-Referenzarchivs und Lizenzstatus dokumentieren.
- [x] Dokumentierte Fachfunktionen von nachgewiesenem Implementierungsumfang unterscheiden.
- [x] Erstes Tabellen-/Entity-Mapping dokumentieren und nicht verifizierte Spalten ausdrücklich markieren.
- [x] Zieldomänen, Statusdimensionen, ERD und Risiken festhalten.
- [ ] Mit anonymisiertem Produktivschema feldgenaues Mapping vervollständigen.
- [x] Fachliche Entscheider bestätigen Steueranzeige (kein Umsatzsteuerausweis), terminindividuelle Kapazität und 30-Kalendermonate-Prüffrist.
- [ ] Storno-, Aufbewahrungs- und steuerrechtliche Einordnung vor Produktivbetrieb klären.

**Abnahme:** Dokumente sind versioniert; keine unbestätigte Regel wird als implementiert ausgegeben.

## Phase 2 – Grundsystem

1. Laufzeit auf PHP 8.4+ setzen und Symfony 7.4 LTS/Komponenten auf exakt kompatible Versionen begrenzen; `composer validate`, `composer update --dry-run` und Tests in der Zielumgebung ausführen.
2. PostgreSQL als unterstützte Hauptdatenbank einrichten; keine SQLite-only Constraints für Buchungen.
3. Symfony Workflow, Messenger, UX Stimulus, Bootstrap 5 und Mailer/Transport konfigurieren, nachdem die Paketauflösung in der Zielumgebung geprüft wurde.
4. Identitäts-/Mitgliedszuordnung, Rollen, Voters, Passwort-Reset und Audit-Ereignisse definieren.
5. Docker Compose für PostgreSQL und lokalen Mail-Testtransport sowie `.env`-Beispiele bereitstellen.
6. PHPUnit, PHPStan und PHP-CS-Fixer in CI ausführen.

**Abnahme:** frische Installation startet, Migrationen laufen auf leerer PostgreSQL-DB, Anmeldung/Autorisierung werden funktional getestet; keine bestehenden Kursfunktionen brechen.

## Phase 3 – Geräteverwaltung

- [ ] Equipment, Category, Manufacturer, Model, CylinderDetails, RegulatorDetails, MaintenanceRecord, InspectionRecord, EquipmentDocument und StatusHistory
- [ ] Migrationen, eindeutige Inventarnummer, Filter/Suche, CRUD, Voter
- [ ] Prüffrist-Service, Dokumentenspeicher, QR-Code
- [ ] Tests für Eigentum, Sperrung und Historie; Prüffrist-Policy (30 Kalendermonate) als erster Domain-Baustein ergänzt

## Phase 4 – Flaschenprüfung

- [ ] Provider, Event, Offer/Items, Booking/Items/Services, Result, Handover
- [ ] Preis-Snapshot mit Integer-Minor-Units, keine Umsatzsteuerposition gemäß Vereinsvorgabe; steuerrechtliche Prüfung bleibt erforderlich
- [ ] Workflow je Buchung und je Flasche, Kapazität mit PostgreSQL-Transaktion/Sperre
- [ ] Messenger-Bestätigung mit Idempotenz, PDF und CSV/XLSX
- [ ] Parallelitäts-, Teilbestehens- und Berechtigungstests

## Phase 5 – Verleih

- [ ] Reservation/Items, Handover, Return, Condition/Damage Report, Pricing Rule, Terms Acceptance
- [ ] Verfügbarkeit mit halb offenen Intervallen `[start, end)`, Wartungs-/Prüf-/Sperrzeiten und Puffern
- [ ] PostgreSQL-seitige Parallelitätskontrolle, Ausgabe, QR-Scan und Teilrückgabe
- [ ] Tests für Überschneidungen, parallele Reservierungen, Schäden und Überfälligkeit

## Phase 6 – Abschluss

- [ ] Dashboard, E-Mails, PDF/Exports, Audit-/Retention-Prozesse
- [ ] Datenschutzexport und konfigurierbare Lösch-/Anonymisierungsprozesse
- [ ] Security Review, Integration-/Funktionstests, Dokumentation, Beispieldaten und Produktionsbetriebshandbuch

## Definition of Done für jedes Arbeitspaket

- Migration ist rückwärts nachvollziehbar und auf leerer DB ausführbar.
- Domänenregeln liegen nicht in Controller/Twig.
- Serverseitige Validierung und objektbezogene Autorisierung sind vorhanden.
- Kritische Status-/Buchungsänderungen sind transaktional und gegen Parallelität abgesichert.
- Tests decken Erfolg, Fehler, Berechtigung und Grenzfälle ab.
- `phpunit`, `phpstan` und `php-cs-fixer` laufen; Abweichungen werden ausdrücklich dokumentiert.
- Entwicklerdokumentation und gegebenenfalls ERD sind aktualisiert.

## Deployment-Anpassung

- Datenbank: vorhandene MySQL-/MariaDB-Instanz, keine Umstellung auf PostgreSQL.
- Auslieferung: additive Dateiergänzung in der bestehenden Symfony-Installation; keine Docker-Abhängigkeit.
- Jede spätere Migration muss zur tatsächlichen MySQL-/MariaDB-Version passen und vor Produktion auf einer Datenbankkopie getestet werden.
- Vor Entity-Erweiterungen werden vorhandene Entities und die reale Datenbankstruktur geprüft, damit `Tank`, `EquipmentItem`, `TankCheck` und Reservierungsmodelle nicht doppelt modelliert werden.
