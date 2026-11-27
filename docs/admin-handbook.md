# DICOMA – Administratorenhandbuch

## Rollenprinzip

- Berechtigungen werden über effektive Vereinsfunktionen und Modulrechte
  bewertet.
- Finanz-, Mitglieder-, Website-, Ausbildungs- und Geräterecht sind getrennte
  Module.
- Ein Superadministrator ist die technische Notfallrolle; operative Funktionen
  sollen trotzdem fachlich zugeordnet bleiben.
- In der Dateiverwaltung benötigt jede Vereinsfunktion die passende
  Modulberechtigung und eine Ordnerfreigabe für den Ordner direkt unter dem
  Hauptordner. Die Freigabe gilt auch für dessen Unterordner.
  Superadministratoren können unabhängig von diesen Freigaben alle Ordner
  des aktuellen Vereins verwalten.

## Arbeitsübersicht

Die Übersicht priorisiert nach effektiver Rolle:

- Kassenwart: Mitgliedsfinanzen, offene Forderungen, Zahlungseingänge.
- Vorstand: Mitglieder, Beiträge, Ausbildung und Vereinsereignisse.
- Fachrollen: ihre bestehenden operativen Portale.
- Superadministrator: zusätzlich alle System- und Stammdatenbereiche.

Direkte URL-Aufrufe ersetzen die serverseitige Rechteprüfung nicht.

## Mitgliederverwaltung

1. „Mitgliederzentrale“ ist der operative Einstieg für Mitgliederdaten.
2. Beitragsart, Familienzahler, Ermäßigung und Ermäßigungsgrund werden direkt
   im Mitgliedsdatensatz gepflegt.
3. Die Detailseite bündelt Mitgliedschaft, Forderungen, Zahlungen, Kündigung,
   Statuswechsel und Vorgangshistorie.
4. Aufnahmeverfahren bleiben unter „Aufnahmeverfahren“ getrennt, weil hier noch
   keine bestehende Mitgliedschaft vorliegt.
5. „Beitragsarten“ und Mitgliedschaftsregeln sind Stammdaten; sie gehören
   nicht in den täglichen Workflow des einzelnen Mitglieds.
6. Technische Personen-, Rollen- und Systemlisten sind Superadministratoren
   vorbehalten.

## Aufnahme und Onboarding

1. In den Mitgliedschaftsregeln werden 1 bis 10 erforderliche Bestätigungen
   festgelegt.
2. Jede berechtigte Prüferin bzw. jeder berechtigte Prüfer dokumentiert
   Identität, Antrag und die konfigurierten Unterlagen einmalig.
3. Eine Person kann denselben Antrag nicht mehrfach bestätigen.
4. Die letzte erforderliche Bestätigung führt atomar den Aufnahmevorgang aus:
   Person, Mitgliedschaft, Mitgliedsnummer, Status, Verlauf und Benachrichtigung.
5. Optional werden eine voreingestellte Beitragsart und die erste
   Jahresforderung mit konfiguriertem Fälligkeitsdatum angelegt.
6. Eine Ablehnung beendet den Antrag und ersetzt keine Löschung oder
   Identitätsänderung.

## Mitgliederfinanzen

1. Spartenzuschläge je Jahr anlegen.
2. Beitragslauf zuerst mit `--dry-run` prüfen.
3. Nur nach fachlicher Freigabe `--confirm` verwenden.
4. Mahnungen ausschließlich über den Vorbereitungsbefehl erzeugen.
5. Export mit neuer Datei, `--confirm` und geschütztem Speicherziel erzeugen.
6. SEPA-Mandate verwalten keine automatische Lastschrift aus.

## Veranstaltung und CMS

- Neue Veranstaltungen ausschließlich im Bereich „Veranstaltungsbuchungen“
  anlegen.
- Redaktionelle CMS-Events sind Legacy und nicht für neue Ereignisse verwenden.
- Seiten bilden Navigation und Hierarchie.
- Artikel einer Seite erben deren Sparte, sofern keine eigene Sparte gewählt ist.

## Geräte und Theke

- Gerätewart öffnet und schließt Wartungsaufträge; technische Freigabe erfolgt
  getrennt vom Bearbeitungsabschluss.
- QR-Codes verweisen auf geschützte Inventarakten.
- Prüferinnerungen über den vorhandenen Outboxprozess versenden.
- Thekendienst-Checkliste und Verbrauch sind von der Kassenführung getrennt.

## Betrieb und Datenschutz

- Vor Migrationen und Cutover Datenbank sowie private Dateien sichern und
  isoliert wiederherstellen.
- Exportdateien enthalten personenbezogene Daten; nur in `var/private` oder
  einem gleichwertig geschützten Ziel ablegen und nach Zweckablauf entfernen.
- Finanz-, Mandats-, Dokumenten- und Nachrichtenbestände nur entsprechend
  dokumentierter Aufbewahrungsdauer verwalten.
- Keine Geheimnisse in Git, Logs oder Exportdateien außerhalb geschützter
  Speicherorte veröffentlichen.

## Wiederherstellung

1. Schreibzugriffe und Hintergrundjobs stoppen.
2. Anwendung, Konfiguration, Migrationstand und private Dateien sichern.
3. Datenbank in isolierter Instanz wiederherstellen.
4. Dateiinventar und Prüfsummen vergleichen.
5. `doctrine:migrations:status`, Syntax-, Container-, Schema- und Smoke-Checks
   ausführen.
6. Dienst erst nach dokumentierter Prüfung wieder freigeben.
