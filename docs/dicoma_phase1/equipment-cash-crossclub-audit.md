# DICOMA Phase 1 – Geräteverleih und Gerätewartkasse: Quellcodeprüfung

Quelle: `dicoma2110.zip`, statische Inspektion am 21.10.2026. Keine produktiven Codeänderungen, keine Laufzeittests. Alle Zeilen beziehen sich auf die Dateien im Originalarchiv.

## Nachgewiesene Schutzmechanismen

| ID | Fundstelle | Nachweis | Einordnung |
|---|---|---|---|
| EQ-01 | `src/Service/EquipmentLoanService.php:15-31,34-55,58-75` | Ausgabe, Rückgabe und Storno laufen in `wrapInTransaction`; Reservierung und Gegenstand werden pessimistisch gesperrt. | Positiver Befund; konkurrierende Reservierungserstellung separat prüfen. |
| EQ-02 | `src/Service/EquipmentBookingLoanService.php:21-32,36-53,56-69` | Positionsänderungen und Sammelausgabe transaktional; Buchung, Position und Inventargegenstand pessimistisch gesperrt. | Positiver Befund. |
| EQ-03 | `src/Service/EquipmentBookingLoanService.php:70-101` | Verfügbarkeit, Vereins-ID des Mitglieds, Ausleihbarkeit, Zustand sowie Flaschen-/Atemreglerprüffristen werden vor Ausgabe geprüft. | Positiver Befund; Club-Zuordnung des konkreten Assets und Aufruferberechtigung gesondert validieren. |
| EQ-04 | `src/Service/EquipmentCashGuard.php:15-27` | Kassenoperationen verlangen aktive DB-Transaktion, sperren die Club-Zeile und kontrollieren den jüngsten Kassenabschluss. | Positiver Befund. |
| EQ-05 | `src/Service/EquipmentCashGuard.php:30-45` | Kassenbestand wird vereinsbezogen aus Abschluss, Bareinnahmen, Auszahlungen und Korrekturen ermittelt. | Positiver Befund; SQL-Schema und Zeitgrenzen prüfen. |

## Weiter zu prüfende Risiken

| ID | Fundstelle | Sachverhalt | Prüfauftrag / Abnahmekriterium | Priorität |
|---|---|---|---|---|
| EQ-06 | `src/Service/EquipmentLoanService.php:15-75` | Der Service akzeptiert ausschließlich eine Reservierungs-ID; im Service selbst keine Benutzer-/Vereinsberechtigungsprüfung. | Aufrufende Controller, Voter und Club-Filter prüfen; fremde Reservierung darf nicht geändert werden. | Hoch |
| EQ-07 | `src/Service/EquipmentBookingLoanService.php:21-76` | Der Service akzeptiert Buchungs-/Positions-IDs; die Club-ID wird aus dem Mitglied ermittelt, aber die Berechtigung des ausführenden Benutzers nicht im Service geprüft. | Sämtliche Aufrufer und Tenant-Grenzen prüfen; Test mit zwei Vereinen. | Hoch |
| EQ-08 | `src/Service/EquipmentCashGuard.php:22-26` | Abschlusssperre vergleicht letzten Abschluss mit `today`; dies belegt nicht automatisch einen Schutz gegen jede rückdatierte Buchung. | Buchungszeitpunkte und Backdating-Regeln an allen Schreibstellen nachweisen. | Hoch |
| EQ-09 | `src/Service/EquipmentCashGuard.php:34-45` | Roh-SQL referenziert konkrete Tabellen/Spalten und trennt Bewegungen anhand von Zeitstempeln. | Mapping/Migrationen gegen SQL prüfen, Grenzfälle bei Zeitstempeln und Zeitzonen testen. | Mittel–hoch |
| EQ-10 | `src/Service/EquipmentBookingLoanService.php:38-53` | Sammelausgabe verarbeitet Positionen innerhalb einer Transaktion; `changeWithinTransaction` ruft zusätzlich `flush()` auf. | Rollback bei Fehler in späterer Position in Integrationstest nachweisen. | Hoch |

## Vorgeschlagene Tests

1. Zwei parallele Ausgaben desselben Gegenstands; keine Doppelvergabe.
2. Benutzer aus Verein A versucht Reservierung/Buchung aus Verein B zu bearbeiten; Zugriff verweigert.
3. Gesperrte Ausrüstung, abgelaufene Flaschenprüfung und abgelaufene Atemreglerrevision verhindern Ausgabe.
4. Sammelausgabe mit Fehler in letzter Position rollt alle Änderungen zurück.
5. Kassenbuchung nach Abschluss wird verweigert; rückdatierte Buchung ebenso gemäß fachlicher Vorgabe.
6. Kassenbestand stimmt mit Belegen, Auszahlungen, Korrekturen und Abschlussständen überein.

## Grenzen

Die Inspektion umfasst die drei genannten Services. Eine endgültige Aussage über das Gesamtsystem erfordert Aufrufer-, Entity-, Migrations- und Laufzeittests. Keine der offenen Fragen wird als bestätigte Sicherheitslücke ausgegeben.
