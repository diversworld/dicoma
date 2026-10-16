# Ausrüstungsverleih – fachliche Umsetzung für Schritt 77ff.

Das Contao-Referenzprojekt beschreibt die Status `available`, `reserved`, `borrowed`, `returned`, `canceled`, `overdue`, `lost`, `damaged`, `missing`. Für DiCoMa sollte der Status einer **Ausleihe** nicht mit dem Zustand eines **Inventarartikels** verwechselt werden.

## Vorgesehenes Datenmodell (noch nicht migriert)

- `EquipmentItem`: Verein, Inventarnummer, Kategorie, Bezeichnung, Seriennummer, Wartungs-/Prüffrist, Betriebszustand, verleihbar.
- `EquipmentReservation`: Artikel, Mitglied, Zeitraum, Status, beantragt/ausgegeben/zurückgenommen am, Bearbeiter, Zustandsprotokoll.
- `EquipmentMovement`: unveränderliches Ereignisprotokoll für Reservierung, Ausgabe, Rücknahme und Stornierung.
- Optional: Kautionen, Gebühren und Dokumente separat.

## Geschäftsregeln

1. Vereinsübergreifende Ausleihe nur nach ausdrücklicher fachlicher Freigabe.
2. Keine überlappenden aktiven Reservierungen; beim Anlegen und Ausgeben transaktionale Sperre (pessimistische Sperre des Inventarartikels).
3. Flaschen ohne dokumentierte gültige Prüfung sind **nicht automatisch verleihbar**.
4. Ausgaben und Rücknahmen als explizite POST-Aktionen mit CSRF-Prüfung, nicht als Statusänderung über GET.
5. Zustand bei Ausgabe und Rücknahme dokumentieren; beschädigte/verlorene Artikel sperren.
6. Überfälligkeit aus Datum ableiten, nicht dauerhaft mit einem Statusfeld synchronisieren.
7. Mitglied kann nur eigene Reservierungen sehen; Administratoren dürfen ausschließlich für berechtigte Vereine handeln.
8. Historische Ausleihen nicht löschen, sondern stornieren/abschließen.

## Abgrenzung

Dieser erste Patch führt eine **lesende Flaschenprüfungszentrale** auf Basis bestehender DiCoMa-Entities ein. Er erzeugt noch keine Ausleihdatensätze und ändert keine Datenbanktabellen. Vor dem Aufbau der Ausleihe müssen die Vereinszuordnung des vorhandenen Tank-Modells und die geltenden Prüfregeln fachlich geklärt werden.
