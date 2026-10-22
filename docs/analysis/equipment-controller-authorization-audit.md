# DICOMA Phase 1 – Controller-Autorisierung Geräteverleih und Kasse

Quelle: `dicoma2110.zip`, statische Prüfung vom 21.10.2026. Zeilenangaben beziehen sich auf Originaldateien. Keine Codeänderung, keine Ausführung von Symfony/Doctrine-Integrationstests.

## Nachgewiesene Aufruferprüfungen

| ID | Datei:Zeilen | Nachweis | Bewertung |
|---|---|---|---|
| AUTH-01 | `src/Controller/EquipmentManagerLoanController.php:20,23-29` | Klassenweite `#[IsGranted('ROLE_EQUIPMENT_MANAGER')]`; `clubId()` leitet Vereins-ID aus angemeldetem Benutzer und Mitglied ab. | Schutz vorhanden. |
| AUTH-02 | `src/Controller/EquipmentManagerLoanController.php:170-180` | Sammelausgabe prüft CSRF und Mitgliedsverein der Buchung gegen aktuellen Verein **vor** `service->issueAll`. | Vorherige offene Frage EQ-07 für diesen HTTP-Pfad teilweise entkräftet. |
| AUTH-03 | `src/Controller/EquipmentManagerLoanController.php:188-221` | Einzelausgabe/-rücknahme prüft CSRF, Buchung, Positionszuordnung, Mitgliedsverein und Gegenstandsverein vor Serviceaufruf. | Schutz vorhanden; Service bleibt auf Aufruferprüfung angewiesen. |
| AUTH-04 | `src/Controller/EquipmentManagerLoanController.php:129-154` | Storno sperrt Buchung pessimistisch und prüft innerhalb der Transaktion den Mitgliedsverein. | Schutz vorhanden. |
| AUTH-05 | `src/Controller/MemberEquipmentReservationController.php:12,16-38,52-53` | Mitgliederreservierung benötigt zugeordnetes Mitglied mit Verein, CSRF; Gegenstände werden nach Verein und Verleihbarkeit geprüft. | Schutz vorhanden. |
| AUTH-06 | `src/Controller/MemberEquipmentReservationController.php:59-67` | Storno einer Reservierung prüft CSRF und **Mitglieds-ID** des Buchungseigentümers. | Eigentümerprüfung vorhanden. |
| AUTH-07 | `src/Controller/EquipmentManagerCashbookController.php:19,81-85,113-117` | Kasseneinzug ist rollenbeschränkt, mit CSRF und Vereinsprüfung der zugehörigen Buchung. | Schutz vorhanden. |
| AUTH-08 | `src/Controller/EquipmentManagerCashCorrectionController.php:15,21-26,34-50` | Korrektur ist rollenbeschränkt, CSRF-geschützt, in Transaktion und prüft Belegverein über parametrisierte SQL-Abfragen. | Schutz vorhanden. |
| AUTH-09 | `src/Controller/EquipmentManagerCashPayoutController.php:17,20-24,49-52` | Auszahlung mit Rollenprüfung, Vereinszuordnung und CSRF. | Schutz vorhanden. |
| AUTH-10 | `src/Controller/EquipmentManagerCashClosingController.php:16,19-23,63-81` | Abschluss mit Rollen-/Vereins-/CSRF-Prüfung; Vereinssperre und erneute Prüfung auf doppelte/spätere Abschlüsse innerhalb der Transaktion. | Schutz vorhanden. |

## Restfragen und Grenzen

1. **Servicegrenzen (hoch):** `EquipmentBookingLoanService::change()` und `::issueAll()` erhalten IDs ohne Benutzerkontext (`src/Service/EquipmentBookingLoanService.php:21-53`). Die untersuchten HTTP-Aufrufer prüfen die Vereinszugehörigkeit. Es muss dennoch eine vollständige Aufruferliste erstellt werden, bevor eine serviceweite Garantie behauptet werden kann.
2. **Zeitpunkt der Berechtigungsprüfung (mittel):** Die `issueAll()`-Berechtigung wird vor Eintritt in die Service-Transaktion geprüft (`EquipmentManagerLoanController.php:175-180`). Ein hypothetischer paralleler Vereinswechsel zwischen Prüfung und gesperrtem Service-Lesezugriff ist nicht durch einen Integrationstest ausgeschlossen. Ob dieser Zustand im Fachmodell möglich ist, ist offen.
3. **Rückgabe-Zweig (mittel):** `changeWithinTransaction()` validiert den Mitgliedsverein ausdrücklich im `issue`-Zweig (`EquipmentBookingLoanService.php:70-77`); beim `return`-Zweig stützt sich die Vereinsprüfung auf den Controller. Direkte Serviceaufrufe müssen separat betrachtet werden.
4. **Kassenabschluss (hoch, Testpflicht):** Die Sperre gegen parallele Abschlüsse ist nachgewiesen; ob Bewegungen mit rückdatierten Zeitstempeln oder aus anderen Schreibpfaden nach einem Abschluss möglich sind, bleibt offen.
5. **Rollenmodell (hoch, übergreifend):** `ROLE_EQUIPMENT_MANAGER` schützt die geprüften Verwaltungscontroller, ersetzt aber keine objektbezogene Prüfung. Für `ROLE_MEMBER` gilt der zuvor dokumentierte automatische Rollen-Zusatz; im Reservierungscontroller existiert eine zusätzliche Mitgliedsprüfung.

## Aktualisierte Bewertung der vorherigen offenen Befunde

- **EQ-06 / EQ-07:** Für die hier untersuchten Verleih-HTTP-Endpunkte ist eine Vereins-/Eigentümerprüfung **positiv nachgewiesen**. Ein pauschaler Befund „Controller prüfen nicht“ wäre falsch. Restfrage: weitere Aufrufer und Service-internes Sicherheitsmodell.
- **EQ-08:** Nicht abgeschlossen. Zeitstempel-/Abschlusslogik erfordert End-to-End-Tests.
- **EQ-10:** Transaktionaler Sammelvorgang statisch nachgewiesen; Rollback unter realer Datenbanklast nicht getestet.

## Abnahmetests für Phase 2

- Verein A versucht per POST mit gültigem eigenem CSRF-Token die Buchung/Position aus Verein B zu ändern: keine Mutation.
- Mitglied A versucht die Reservierung von Mitglied B zu stornieren: keine Mutation.
- Gerätewart A versucht den Kasseneinzug für eine Buchung aus Verein B: keine Mutation.
- Parallel laufender Kassenabschluss und rückdatierte Zahlung: Konsistenz des Abschlusses beweisen.
- Sammelausgabe mit Fehler in späterer Position: vollständiger Rollback.

## Status

Gezielte Controllerprüfung abgeschlossen. Kein Beleg für einen fehlenden Vereinscheck in den hier untersuchten Geräteverleih-HTTP-Pfaden. Keine umfassende Sicherheitsfreigabe, keine Symfony-Laufzeittests, keine produktiven Änderungen. Phase 1 bleibt offen.
