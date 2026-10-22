# DICOMA Phase 1 – vollständige Aufruferprüfung EquipmentBookingLoanService

Prüfgrundlage: `dicoma2110.zip`, statische Untersuchung aller PHP-Dateien unter `src/`. Stand: 21.10.2026. Keine produktiven Änderungen, keine Laufzeittests.

## Aufruferinventar

Die Klassennennung `EquipmentBookingLoanService` kommt außerhalb der Serviceklasse ausschließlich in zwei Controllern vor:

1. `src/Controller/EquipmentManagerLoanController.php`, Zeilen 169–185 (`issueAll`) und 188–226 (`action` / `change`): `#[IsGranted('ROLE_EQUIPMENT_MANAGER')]` auf Klassenebene (Zeile 20); `clubId()` aus `User->Member->Club` (23–31); POST und CSRF (169–174 bzw. 188–200); vor `issueAll` Buchung-Club-Vergleich (175–178); vor `change` Buchung-Club- und Positionszuordnung (201–205) sowie Asset-Club-Vergleich (206–209). **Controllerseitig nachgewiesene Vereinsschranke.**
2. `src/Controller/Admin/EquipmentBookingCrudController.php`, Zeilen 77–115 (`equipmentBookingItemAction` / `change`): `#[IsGranted('ROLE_ADMIN')]` auf Klassenebene (29); POST- und CSRF-Prüfung (80–88), positive IDs (90–93); **keine sichtbare Prüfung der Buchungs- oder Asset-Vereinszuordnung vor Serviceaufruf (98–104)**. Ein Benutzer mit `ROLE_ADMIN` kann in dieser Methode IDs übermitteln; ob diese Rolle bewusst global vereinsübergreifend autorisiert ist, ist fachlich zu klären. Kein Nachweis, dass Nicht-Admins den Endpunkt erreichen können.

## Serviceinterne Garantien und Grenzen

`src/Service/EquipmentBookingLoanService.php`:
- `change()` prüft Operationsart und Rückgabezustand (21–28), führt Änderung in `wrapInTransaction()` aus (30–32).
- `issueAll()` sperrt Buchung pessimistisch, verarbeitet Positionen sortiert innerhalb einer Transaktion (36–53).
- `changeWithinTransaction()` sperrt Buchung und Position, verifiziert deren Zusammengehörigkeit (56–68), sperrt Asset.
- Bei **Ausgabe** wird der Verein der Buchung aus dem Mitglied abgeleitet und an `EquipmentAssetAvailability::assertAvailable()` übergeben (70–77). Dies ist eine fachliche Verfügbarkeitsprüfung, **keine Prüfung des aktuellen Benutzers**.
- Bei **Rückgabe** (103–116) fehlt im Service ein Benutzer- oder Club-Kontext; der Controller muss den Zugriff autorisieren.

## Befund und Risikoeinstufung

**SEC-LOAN-01 – Admin-CRUD-Aktion ohne objektbezogene Vereinsprüfung (mittel / bedingt).** Bei globalen Administratorrechten ist dies möglicherweise korrekt. Wenn `ROLE_ADMIN` vereinslokal gelten soll, ist eine vereinsübergreifende Zustandsänderung über die Aktion ein ernstes Risiko. Fachentscheidung und Integrationstest erforderlich. Nicht als bestätigte Schwachstelle für unberechtigte Benutzer einstufen.

**SEC-LOAN-02 – Service ohne Actor-Kontext (Architekturrisiko, mittel).** Aktuell existieren zwei aufrufende Controller. Die Gerätewart-Endpunkte prüfen den Club, die Admin-Aktion nur die globale Rolle. Bei späteren Aufrufern ist eine zentralisierte Policy/Voter-Prüfung empfehlenswert, abgestimmt mit Symfony Security (Voters, `isGranted`).

**TEST-LOAN-01:** Benutzer mit `ROLE_EQUIPMENT_MANAGER` von Verein A versucht Ausgabe/Rückgabe von Buchung B -> 404/403, keine Änderung.

**TEST-LOAN-02:** `ROLE_ADMIN` mit Mitgliedschaft Verein A versucht Position von Verein B -> gewünschte globale oder lokale Admin-Policy ausdrücklich festlegen und testen.

**TEST-LOAN-03:** `issueAll()` schlägt bei letzter Position fehl -> alle zuvor geänderten Positionen zurückgerollt.

**TEST-LOAN-04:** Gleichzeitige Ausgabe desselben Assets -> exakt eine zulässige erfolgreiche Buchung.

## Aussagegrenzen

Statische Textsuche nach direkten Serviceaufrufen; keine Symfony-Containerauflösung oder Integrationstests. Dynamische Aufrufe, Events und Drittcode sind damit nicht vollständig ausgeschlossen. Der Befund ersetzt keine vollständige Autorisierungsprüfung aller Module.
