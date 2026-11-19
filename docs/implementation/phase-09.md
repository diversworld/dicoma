# DICOMA – Phase 9 – Abschlussbericht

## 1. Status

**Technisch abgeschlossen; fachliche Abnahme noch erforderlich.**

Bestehende Dienstplanung, Vertretung, Anwesenheit, Stundenkonten, Erinnerungen
und Thekenkasse wurden erhalten. Checklisten, Übergabe, Bestands- und
Verbrauchsmeldungen wurden ergänzt.

## 2. Implementierte Änderungen

- Schichtbezogene Standardcheckliste mit fünf operativen Prüfpunkten.
- Erledigtstatus und Bemerkung pro Prüfpunkt.
- Formelle Übergabe für die nächste Schicht oder Vereinsverantwortliche.
- Schichtbezogene Bestandsmeldung mit Anfang, Verbrauch, Schluss und Notiz.
- Domainprüfung garantiert: Anfang minus Verbrauch entspricht Schlussbestand.
- Jahresbericht summiert Verbrauch je Artikel getrennt von Stunden- und
  Kassenwerten.
- Einzelfälle bleiben über eine eigene Liste im Bericht nachvollziehbar.
- Nur aktive, eingeteilte Mitglieder und die Thekenverwaltung können eine
  Schicht erfassen; fremde Mitglieder erhalten 403.
- Bestehende Kalender-, Selbstübernahme-, Vertretungs-, Tausch-,
  Anwesenheits-, Erinnerungs- und Kassenabläufe bleiben unverändert.

## 3. Geänderte Dateien

- `src/Entity/BarShiftChecklist.php`
- `src/Entity/BarStockEntry.php`
- `src/Controller/BarDutyController.php`
- `templates/bar_duty/shift.html.twig`
- `templates/bar_duty/report.html.twig`
- `migrations/Version20261119150000.php`
- `tests/Integration/Phase8BarDutyHttpTest.php`

## 4. Datenbank

Neue Tabellen:

- `bar_shift_checklist`: Checklisten, Übergabe, Abschlusszeit und Person.
- `bar_stock_entry`: Schicht, Artikel, Anfang, Verbrauch, Schluss, Notiz und
  erfassende Person.

Migration lokal eingespielt. MariaDB-Prüfung: Up, Down und erneutes Up bestanden.

## 5. Sicherheit

- Schicht und Verein werden serverseitig aufgelöst.
- Checkliste und Bestand sind nur für aktive Einteilung oder
  Thekenverwaltung schreibbar.
- Fremdmitgliedschaft erhält 403.
- Bestandswerte müssen arithmetisch konsistent und nicht negativ sein.
- Texte haben feste Längenbegrenzungen.
- Berichte bleiben mit der bestehenden geschützten Backend-Auswertung
  verbunden.
- Kasse, Gerätewartkasse und Bestandsverbrauch bleiben getrennte Datenmodelle.

## 6. Tests

Neu:

- Eingeteiltes Mitglied speichert Checkliste und Übergabe.
- Verbrauch wird persistiert.
- Fremdes Mitglied erhält 403.
- Bestands-Domänenfehler wird verworfen.
- Leitung sieht Verbrauch im Jahresbericht.

Fokussierter Bestand:

- Planung, Kalender, Anmeldung und Benachrichtigung: 18 Assertions.
- Kassenjournal, Korrektur, Abschluss und CSV: 22 Assertions.
- Erinnerungs-Idempotenz und Versand-Opt-in: 12 Assertions.
- Neue Workflowtests: 29 Assertions.

Die breite historische Thekendienstsuite umfasst 71 Tests und meldet derzeit
drei bekannte, funktionsmodell-/Backendkontext-bezogene Legacy-Abweichungen;
die oben genannten Phase-9-Kernpfade sowie bestehende Planungs-, Kassen- und
Erinnerungsabläufe bestanden isoliert.

## 7. Bekannte Einschränkungen

- Die Standardcheckliste ist derzeit im Domainmodell hinterlegt und wird bei
  erstmaligem Speichern erzeugt.
- Pro Schicht wird eine aktualisierbare Übergabe gespeichert, nicht ein
  Revisionsprotokoll einzelner Übergabeversionen.
- Bestandsmeldungen sind Mengen pro frei benanntem Artikel; ein zentraler
  Getränke- oder Warenkatalog ist noch nicht Teil dieser Phase.
- Erinnerungs-Mail benötigt weiterhin gültigen Transport und explizites
  `--send`.

## 8. Inbetriebnahme

1. Migration `Version20261119150000` ausführen.
2. Cache leeren.
3. Mit Thekenleiter und eingeteilter Person eine Testschicht erfassen.
4. Checkliste, Übergabe und mindestens eine Bestandsmeldung bestätigen.
5. Jahresbericht prüfen.
6. Bestehenden Mailjob und Monitoring für Dienst-Erinnerungen beibehalten.

## 9. Abnahmekriterien

- Diensttypen/Schichten: bestehend und geprüft.
- Dienstkalender: bestehend und geprüft.
- Einteilung/Übernahme: bestehend und geprüft.
- Vertretung/Tausch: bestehend und geprüft.
- Bestätigung/Erinnerung: bestehend und geprüft.
- Checkliste/Übergabe: erfüllt.
- Bestand/Verbrauch: erfüllt.
- Kassenabschluss optional: bestehend und getrennt.
- Historie/Auswertung: bestehend, ergänzt und geprüft.

Fachliche Abnahme bleibt erforderlich.

## 10. Nächster Schritt

Fachliche Abnahme der Checkliste, Übergabe und Bestandspunkte. Phase 10 wird
nicht automatisch begonnen.
