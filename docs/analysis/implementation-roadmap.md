# DICOMA – aktualisierter Umsetzungsplan

Stand: 28.10.2026. Der Auftraggeber hat den phasenweisen Gesamtumfang gewählt.
Funktionierende Prozesse bleiben erhalten; keine neue Plattform und keine
produktive Migration ohne gesonderte Ausführungsfreigabe.

## 1. Konsolidierung und aktuelle Bestandsprüfung

Umsetzung: [Portal-Teilphase](../phase11-portals.md). Bestehende Analyseberichte
sind als historisch gekennzeichnet und um aktuelle Befunde ergänzt.
Öffentliche Vereinsinformationen/Kontakt wurden zusätzlich mit dem bestehenden
Vereinsmodell ergänzt. Die nachfolgenden Fachmodule sind damit ausdrücklich
**noch nicht implementiert oder abgenommen**.

- Aktuelle statt historischer Funktionsmatrix verwenden.
- Defekte Frontend-Layoutvererbung reparieren.
- Gemeinsame Anmeldung mit rollenabhängigem Ziel; getrennte Navigation ohne
  Bereichswechsel, ein gemeinsames Frontendtheme.
- Eigene Buchungsübersicht und Ausbilderkurse; aktive eingeteilte Mitglieder
  bedienen die Kasse ihrer eigenen Schicht, Clubheimverwaltung bleibt Backend.
- Self-Service/Kalender von Verwaltungsoberflächen trennen; tatsächliche
  Objektberechtigungen unverändert serverseitig durchsetzen.
- Isolierte Regression der bestehenden Module ausführen, Abweichungen offen
  dokumentieren. Kein Schemawechsel für reine Navigation.

## 2. Veranstaltungen

Aufbauend auf vorhandenen Ankündigungen, aber mit eigenständigem Fachprozess:
Vereins-/öffentliche Sichtbarkeit, Anmeldung an `Person`, Kapazität und
Warteliste mit Transaktion/Sperre, Anmeldefrist, Gebühren, Zahlungen, Storno,
Teilnehmerrechte, Dokumente, Erinnerungen und Kalenderprojektion.
Verknüpfte Kurse/Turniere behalten ihre eigenen Anmeldeprozesse; keine
doppelten Teilnehmer-/Kalenderdaten.

Abnahme: gleichzeitige Buchung des letzten Platzes, Nachrücken, doppelte
Zahlung, fremde Teilnehmerliste, fehlender CSRF-Schutz, Absage und Erstattung.

## 3. Clubreisen

Eigenes Fachmodell für Reise, Personenteilnahme und Leistungen;
Qualifikations-/Tauchtauglichkeitsprüfung mit Datenminimierung, Unterkunft und
Transport, Ausrüstung, Anzahlungen/Restzahlungen/Zusatzkosten/Erstattungen,
Dokumente und Kalender. Vorhandene Personen, Qualifikationen und
Ausrüstungsservices wiederverwenden.

Abnahme: Gast/Mitglied, fehlende Voraussetzung, Warteliste, Fristen,
Reisestorno, nachvollziehbare Erstattung, Dokument- und Vereinsgrenzen.

## 4. Querschnitt und Vorstandsprozesse

Gemeinsamer berechtigungsgesteuerter Dokumenteneinstieg, ereignisbasierte
Benachrichtigungen/Inbox, globale Suche, Rollenberichte sowie fehlende
Vorstandsaufgaben/Beschlüsse. Erst vorhandene Fachautorisierung wiederverwenden,
danach Quellen integrieren; keine neue globale Leseberechtigung.

## 5. Fach- und Betriebsabnahme

Je Modul alle Kriterien der Master Specification gegen konkrete Methoden und
Tests abnehmen. MariaDB-Konkurrenz-/Migrationstests, Bestandsupgrade,
Datei-/Datenbankwiederherstellung, responsive Browserprüfung,
Datenschutz-/Aufbewahrungskonzept und freigegebenes Release separat nachweisen.
Ein erfolgreiches SQLite-Schema oder einzelner HTTP-Test ersetzt diese Gates nicht.
