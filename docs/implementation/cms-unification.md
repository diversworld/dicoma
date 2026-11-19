# CMS- und Veranstaltungsvereinheitlichung

## Ergebnis

Die Anwendung verwendet ab diesem Stand genau einen Erstellungsweg für neue
öffentliche Veranstaltungen:

- Kurse, Turniere, Vereinsveranstaltungen, Tauchgänge, Ausfahrten und sonstige
  Ereignisse werden als `ClubEvent` angelegt und verwaltet.
- Anmeldungen, Warteliste, Zahlungen, Dokumente, Storno und Erinnerungen bleiben
  Teil dieses einen Workflows.
- Ein `ClubEvent` kann optional genau einen Kurs oder ein Turnier als Fachmodul
  referenzieren. Dessen bestehende Ausbildung-/Turnierlogik wird nicht
  dupliziert.
- Vorhandene `WebsitePublication(kind=event)` bleiben ausschließlich als
  Legacy-Bestand les- und bearbeitbar. Über die Redaktion können keine neuen
  solchen Doppel-Ereignisse angelegt werden.

## CMS-Struktur

- `WebsitePublication(kind=page)` bildet Seiten und Unterseiten.
- Eine Seite hat Sortierreihenfolge, Menüsichtbarkeit und optionale Sparte.
- Hauptseiten und erste Unterseiten erscheinen im öffentlichen Menü als
  Dropdown.
- Artikel können einer Seite zugeordnet werden.
- Ist beim Artikel keine eigene Sparte gesetzt, erbt er die Sparte der Seite.
- Eine eigene Artikelsparte überschreibt die Vererbung.
- Jede Seite zeigt ihre sichtbaren Unterseiten und zugeordneten Artikel.
- Nur veröffentlichte, fällige und einer aktiven Sparte zugeordnete Inhalte
  erscheinen öffentlich.

## EasyAdmin-UX

- Redaktionsformular: einklappbare Bereiche für Grunddaten/Hierarchie und
  Sparte/Medien/Veröffentlichung.
- Veranstaltungsformular: einklappbare Bereiche für Inhalt, Termin,
  Wiederholung, Fachmodul/Anmeldung/Preise und Bild.
- Responsive Spalten, gemeinsame Formularstile, Feldfehler und Aktionen.
- Bestehende Kurs-CRUD-Formulare behalten ihre vorhandenen Fieldsets und
  Spalten.

## Fehler `Member id(1) was not found`

Die Kursliste dereferenzierte den `leadInstructor`-Proxy in EasyAdmin. Bei
inkonsistenten oder verwaisten Testdaten führte das zu HTTP 500.

Korrektur:

- Der Index verwendet eine LEFT-JOIN-hydrierte Instructor-Zuordnung.
- `leadInstructor` bleibt in Formularen eine normale Association.
- In der Indexansicht wird nur ein skalarer Name gerendert.
- `app:courses:check-references` meldet verwaiste Kursreferenzen lesend.
- Der Prüfbefehl ändert keine Daten.

Die lokale Datenbank meldet aktuell null verwaiste Ausbilderreferenzen. Auf
`fakt.diversworld.eu` muss die Korrektur zuerst deployed und danach der
Prüfbefehl ausgeführt werden. Falls dort weiterhin verwaiste IDs gemeldet
werden, sind diese anhand der betroffenen Kurs-IDs fachlich zu klären und
nicht pauschal zu löschen.

## Migration

`Version20261119130000` ergänzt:

- Seiteneltern, Sortierung und Menüsichtbarkeit;
- optionale Anmeldefreigabe;
- optionale Kurs-/Turnierverknüpfung einer Veranstaltung.

Die Migration wurde auf isolierter MariaDB vorwärts, rückwärts und erneut
vorwärts geprüft.

## Tests

- CMS-Hierarchie und Spartenerbung: bestanden.
- Kursliste mit künstlicher verwaister Member-ID: 200 und Anzeige erhalten.
- Konsistenzbefehl: verwaiste ID gemeldet, Daten unverändert.
- CMS-Seiten- und Artikelformulare: bestehende Workflows bestanden.
- Zentrale Veranstaltungs- und vorhandene Buchungssuite:
  19 Tests, 183 Assertions bestanden.
- Zusammenfassung CMS/Event/Orphan: 21 Tests, 195 Assertions bestanden.

Vor einem Testsystem-Deployment sind Datenbankbackup und Restore-Nachweis
erneut erforderlich. Productivdaten werden nicht automatisch repariert.
