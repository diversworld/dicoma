# Migration-Mapping: Contao Diveclub → DiveClub Manager

**Status:** fachliches Mapping, noch kein datenbankgenaues ETL-Mapping. Das Referenzarchiv enthält DCA-Definitionen und eine dokumentarische Tabellenübersicht, aber keinen produktiven Datenbankdump. Vor einer produktiven Migration sind echte Spalten, Contao-`tl_member`-IDs, Dateiverweise und Mehrfachwerte zu inventarisieren.

| Contao-Tabelle | Ziel-Entity(s) | Transformations-/Prüfregeln |
|---|---|---|
| `tl_dc_tanks` | `Equipment`, `CylinderDetails`, ggf. `InspectionRecord` | Inventarnummer neu vergeben, falls nicht vorhanden; Seriennummer nicht global unique erzwingen; letzte/nächste Prüfung nur mit Herkunft übernehmen; Statuswerte normalisieren |
| `tl_dc_equipment` | `Equipment` | Typ/Kategorie auf `EquipmentCategory` abbilden; technische Sperre von Verfügbarkeitsstatus trennen |
| `tl_dc_regulators` | `Equipment`, `RegulatorDetails`, `MaintenanceRecord` | Regler-Set und Komponentenbeziehungen klären; historische Revisionen nicht in ein einzelnes Datumsfeld verdichten |
| `tl_dc_regulator_control` | `MaintenanceRecord` oder `InspectionRecord` | fachliche Bedeutung der Felder prüfen; Dokumente/Prüfer/Ergebnis nachvollziehbar erhalten |
| `tl_dc_reservation` | `RentalReservation` | Mitglied, Zeitraum, Gesamtstatus und ursprüngliche Gebühren übernehmen; Altstatus in explizite Zielstatusdimensionen mappen |
| `tl_dc_reservation_items` | `RentalReservationItem`, ggf. `RentalHandover`/`RentalReturn` | konkrete Geräte und Zeitpunkte zuordnen; keine historische Ausgabe/Rückgabe überschreiben; doppelte bzw. verwaiste Items melden |
| `tl_dc_check_proposal` | `InspectionOffer`, `InspectionEvent` | Angebot und konkreten Termin trennen; Kalender-Referenzen aufgelöst migrieren |
| `tl_dc_check_booking` | `InspectionBooking` | Buchungskopf und Mitglied zuordnen; Nummernkreis kollisionsfrei fortführen oder neu nummerieren mit Crosswalk |
| `tl_dc_check_order` | `InspectionBookingItem`, `InspectionResult`, ggf. `InspectionHandover` | Flasche und Buchung verknüpfen; Einzelresultate rekonstruieren, wenn Felder vorliegen; keine fehlenden Resultate erfinden |
| `tl_dc_check_articles` | `InspectionOfferItem`, `InspectionBookingService` | Artikel/Preise migrieren; historische Buchungspreise aus vorhandenen Belegen rekonstruieren, niemals aktuelle Preise als historische Preise ausgeben |
| `tl_dc_config` | typisierte `DiveClubSettings` / Symfony-Konfiguration | Schlüssel inventarisieren; Geheimnisse und Pfade nicht blind übernehmen; unbekannte Schlüssel in Migrationsbericht ausgeben |
| `tl_calendar`, `tl_calendar_events` | `InspectionEvent` oder andere explizite Termine | nur relevante Kalender-Events migrieren; Verknüpfungen anhand der tatsächlichen DCA-Felder verifizieren |
| `tl_member` | `User`/`Member` | Identitätsabgleich und E-Mail-Duplikate prüfen; Passwörter nur übernehmen, wenn Hashformat kompatibel und rechtlich/technisch zulässig, sonst Passwort-Reset erzwingen |
| Contao-Dateien/UUIDs | `EquipmentDocument`, `InspectionResult`-Dokumente, `RentalConditionReport` | Dateiinhalt und Metadaten getrennt kopieren; Hash, MIME, Größe, Quelle und Berechtigungen erfassen; fehlende Dateien als Fehler protokollieren |

## Migrationsablauf

1. Quell-Schema und Datenbestand nur lesend inventarisieren.
2. Mapping-Manifest mit Tabellen, Spalten, Nullregeln, Werteübersetzungen und Datenverantwortlichen freigeben.
3. Dry-Run erzeugt CSV-Berichte: unbekannte Statuswerte, fehlende Referenzen, Duplikate, verwaiste Datensätze, ungültige Datums-/Preiswerte.
4. Idempotente Migration mit stabiler Crosswalk-Tabelle `legacy_source`, `legacy_table`, `legacy_id`, `target_type`, `target_id`.
5. Dokumente kopieren und mit SHA-256 verifizieren.
6. Summen-/Referenzprüfung: Quell- und Zielzählungen, Buchungssummen, Gerätezuordnungen, Prüfprotokolle und Dokumente.
7. Fachliche Abnahme durch Gerätewart, Prüfungskoordinator und Kassenwart; erst dann Umschaltung.
8. Quellsystem während der Umschaltung schreibgeschützt halten; Rollbackplan und Backup testen.

## Daten, die nicht automatisch abgeleitet werden dürfen

- Eine aktuelle Prüffrist aus einem fehlgeschlagenen oder unklaren Prüfergebnis.
- Historische vereinbarte Preise aus einer aktuellen Preisliste.
- Eigentümer aus einer bloßen letzten Reservierung.
- Physischer Aufenthaltsort aus einem Verleihstatus, wenn Aus-/Rückgabezeitpunkte fehlen.
- Offizielle Prüfergebnisse aus Freitext oder bloßen Prüfterminen.
- Steuerliche Angaben oder Aufbewahrungsfristen ohne bestätigte Geschäftsregel.
