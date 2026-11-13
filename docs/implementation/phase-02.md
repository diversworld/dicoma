# Phase 2: Personen, Mitgliedschaften und Sparten

## 1. Status

In Arbeit; keine fachliche Gesamt- oder Produktivfreigabe. Die Modulsteuerung
ist ergänzt, aber einzelne Abnahme- und Nebenläufigkeitsnachweise stehen noch
aus. Die fachliche Freigabe erteilt die zuständige Person.

## 2. Umsetzung

Die vorhandenen Modelle für Personen, Vereinsmitgliedschaften, Sparten,
Mehrfachzuordnungen und Spartenfunktionen werden weiterverwendet. Pro Sparte
sind Ausbildung und Mannschaften/Wettkampf administrativ konfigurierbar.
Bei Kursen ohne Spartenzuordnung bleibt das bisherige Anmeldeverhalten erhalten;
bei zugeordneten Kursen genügt eine Sparte mit aktivierter Ausbildung.
Neue Kurs-Spartenzuordnungen und Anmeldungen werden bei deaktiviertem Modul
abgelehnt.

Neue Mannschaften, Kaderzuordnungen und Mannschaftstraining sowie neue
Turniere und Turniermeldungen erfordern ein aktiviertes Wettkampfmodul.
Turniere erhalten eine ausdrückliche Sparte des veranstaltenden Vereins;
für offene Altturniere ohne Sparte existiert ein separater Zuordnungsweg.
Mannschaften anderer Vereine bleiben über die gemeinsame Sportkennung
teilnahmefähig. Vorhandene Vorgänge bleiben bearbeitbar; insbesondere sind
Storno und Zahlungen nicht an die Aktivierung neuer Vorgänge gekoppelt.

## 3. Geänderte Bereiche

Betroffen sind Sparten- und Kursmodelle, Mannschafts- und Turnierverwaltung,
zugehörige Formulare und Templates, die Kursanmeldung, die Migrationskette,
Integrationstests und der Übersetzungsprüfer.

## 4. Datenbank und Migration

Die neue Migration ergänzt zwei standardmäßig aktivierte Modulschalter und
eine nullable Turnier-Spartenzuordnung. Bestehende Turniere werden nicht
automatisch zugeordnet. Die Rückmigration ist wegen möglicher Datenverluste
nicht automatisch möglich. Ein frischer Schemaaufbau und ein Upgrade mit
synthetischen Bestandsdaten bestanden gegen eine private MariaDB. Der Nutzer
hat die bestehende DDEV-Datenbank selbst migriert; Mapping und Migrationsstand
wurden anschließend lesend geprüft. Ein danach erstelltes Backup wurde in
einer privaten Datenbank restauriert; die Prüfsummen aller 108 Tabellen
stimmten vor und nach dem Export sowie im Restore überein. Dieses Backup ist
kein Nachweis eines unmittelbar vor der Migration erstellten Backups.

## 5. Sicherheit

Formularoptionen werden vereinsbezogen eingeschränkt; die neue
Altturnier-Zuordnung erfordert Administratorrechte, Vereinskontext und CSRF.
Modulprüfungen erfolgen serverseitig. Vollständigkeit aller Schreibwege und
Nebenläufigkeit sind noch nicht abschließend nachgewiesen.

## 6. Tests

Das Basis-Gate bestand in DDEV mit 453 Tests und 10.765 Assertions; danach
wurden drei Übersetzungseinträge ergänzt und die betroffenen Workflows mit
zwei Tests und 101 Assertions erneut geprüft. Die isolierte Staging-HTTP-Suite
bestand mit 32 Tests und 1.085 Assertions. Acht JavaScript-Tests bestanden in
DDEV. Die Migrationstests in privater MariaDB hatten zunächst einen Fehler,
weil die neue irreversible Migration in einem historischen Rückrolltest nicht
klassifiziert war. Nach Korrektur bestand dieser Test einzeln mit sechs
Assertions. Eine komplette Wiederholung wurde aus Zeitgründen abgebrochen;
ein vollständiger erfolgreicher Gesamtlauf auf dem endgültigen Stand ist daher
nicht belegt.

Der Übersetzungsprüfer erreicht nach Korrektur des FormType-Scans die
Auswertung, meldet jedoch weiterhin zahlreiche fehlende Übersetzungen und
schlägt deshalb fehl. Zwölf betroffene Twig-Vorlagen bestanden den Syntaxcheck;
die lokale Startseite antwortete mit HTTP 200, die geschützte Verwaltung
anonym mit HTTP 302.

## 7. Offene Punkte

Fachliche Gesamtabnahme, lückenlose Prüfung der Modulschreibwege einschließlich
Nebenläufigkeit und umfassender Spartenhistorie sowie der vollständige erneute
Migrationslauf stehen aus.
Auch die weiteren Module des Gesamtplans sind nicht durch diese zwei Schalter
implementiert.

## 8. Abnahmekriterien

Spartenanlage, Mitgliedszuordnung und administrative Modulkonfiguration sind
vorhanden; die vollständige Ende-zu-Ende-Abnahme einschließlich Rollen,
Altbestand, Reaktivierung und aller relevanten Schreibwege ist noch offen.

## 9. Empfehlung

Keine Produktivfreigabe vor Abschluss der offenen Prüfungen. Für künftige
Migrationen vorab ein konsistentes Backup mit Restoreprüfung anlegen und
gleichzeitige Schreibzugriffe ausschließen.

## 10. Nächster Schritt

Testketten vervollständigen, Befunde beheben oder ausdrücklich bewerten und
anschließend die fachliche Abnahme durch die zuständige Person einholen.
Phase 3 wird nicht automatisch begonnen.
