# Phase 11 – Qualität und Betrieb

## Regression und Tests

Die vorhandenen fokussierten Suites decken Rollen, Mitgliedschaft, Ausbildung,
Website/CMS, Veranstaltungen, Geräte, Thekendienst und Finanzen. Phase 11 nutzt
zusätzlich die neue rollenbezogene Dashboardprüfung und bestehende
Migrationssuites.

## Datenbank und Deployment

- Migrationen bleiben additiv und reversibel, sofern Datenverlust ausgeschlossen
  ist.
- `doctrine:schema:update --dump-sql --complete` dient nur zur Inspektion und
  nicht als automatischer Produktivpfad.
- Bekannte, vorbestehende Schemaabweichungen bei `tank` und
  `equipment_cash_correction` sind getrennt zu bearbeiten.

## Backup und Restore

Für Phase 11 und jeden späteren Cutover:

1. Datenbankdump unter Schreibpause erstellen.
2. `files/` und `var/private/` archivieren.
3. Beide Quellen auf getrennte Ziele wiederherstellen.
4. Normalisierte Dumps, Dateiprüfsummen und Dateimodi vergleichen.
5. Migrationsstand und Anwendungssmoke-Test ausführen.
6. Nachweis mit Zeitstempel und verantwortlicher Person dokumentieren.

Die bisher belegten lokalen Restore-Nachweise ersetzen kein aktuelles
Produktivbackup unmittelbar vor einem echten Deployment.

## Datenschutz und Aufbewahrung

Vor Produktivabnahme ist eine fachliche Aufbewahrungsmatrix zu entscheiden:

- Mitglieder- und Finanzunterlagen,
- Rechnungen und Zahlungsbelege,
- SEPA-Mandate und Nachweise,
- private Dokumente,
- Nachrichten und Übergabeprotokolle,
- technische Logs und personenbezogene Exporte.

Die Anwendung erzwingt Löschungen nur dort, wo Geschäftsregeln dies bereits
erlauben. Eine pauschale Löschung personenbezogener Finanz- oder
Vertragsunterlagen wäre fachlich und rechtlich nicht zulässig.

## Performance

Die Finanzansicht ist paginiert und lädt Zahlungsdaten pro Seite gebündelt.
Dashboardkennzahlen verwenden Aggregatabfragen. Vor Betrieb mit größerem
Datenbestand sind Datenbankindizes und Abfragepläne anhand realer Volumina zu
prüfen.

## Barrierefreiheit und responsives Verhalten

Kernseiten nutzen Responsive-Tabellen, eindeutige Überschriften, Label und
native Buttons/Links. Vor Release sind Tastaturbedienung, Farbkontrast,
Mobilbreiten und Formularfehler je kritischer Oberfläche fachlich zu prüfen.
