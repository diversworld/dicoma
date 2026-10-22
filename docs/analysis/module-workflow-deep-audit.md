# DICOMA – vertiefte Modulprüfung (Phase 1)

Quelle: Originalarchiv `dicoma2110.zip`; statische Prüfung am 2026-10-22. Keine produktiven Änderungen.

## Nachgewiesener Mannschaftsablauf

Die vorhandenen Klassen `Team` und `TeamMember` bilden Mannschaften und ihre Mitgliedschaften ab. `Team` verweist verpflichtend auf eine Sportart; `TeamMember` auf Team und Mitglied. Ein Unique Constraint auf `(team_id, member_id)` verhindert doppelte Teamzuordnungen auf Datenbankebene.

Die Validierung von `TeamMember` kontrolliert die Vereinsgleichheit von Sportart und Mitglied und verlangt grundsätzlich eine passende Sportartenzuordnung. Die EasyAdmin-Verwaltung kann bei ausdrücklicher Auswahl die fehlende Sportartenzuordnung ergänzen; die Speicherung verwendet `EntityManager::wrapInTransaction()`. Der separate Sportcheck-Endpunkt erfordert `ROLE_ADMIN` und liefert bei Vereinsabweichung `club_mismatch`.

## Fundstellen

| ID | Nachweis | Datei:Zeile | Status |
|---|---|---|---|
| TM-01 | Team-Sportart ist Pflicht | `src/Entity/Team.php:30` | bestätigt |
| TM-02 | Team-Mitglied eindeutig | `src/Entity/TeamMember.php:17` | bestätigt |
| TM-03 | Vereinsabgleich bei Teammitglied | `src/Entity/TeamMember.php:159` | bestätigt |
| TM-04 | Fehlende Sportart kann optional zugeordnet werden | `src/Controller/Admin/TeamMemberCrudController.php:267` | bestätigt |
| TM-05 | Speichern erfolgt in Transaktion | `src/Controller/Admin/TeamMemberCrudController.php:259` | bestätigt |
| TM-06 | Sportcheck-Endpunkt nur für Admins | `src/Controller/Admin/TeamMemberSportCheckController.php:17` | bestätigt |
| TM-07 | Team-Crud nur für Admins | `src/Controller/Admin/TeamCrudController.php:27` | bestätigt |
| TM-08 | Sportcheck weist Vereinsabweichung aus | `src/Controller/Admin/TeamMemberSportCheckController.php:42` | bestätigt |

## Grenzen und offene Risiken

- Die `ROLE_ADMIN`-Prüfung beweist keine Beschränkung auf den eigenen Verein. Die Team- und Teammitglied-CRUD-Controller benötigen einen gesonderten Mandantentrennungstest entsprechend dem fachlichen Rollenmodell.
- `TeamMember::validateAssignment()` vergleicht die `Club`-Objekte mit `!==`; der tatsächliche Umgang mit Doctrine-Proxies und unterschiedlichen Objektinstanzen ist durch Integrationstests zu prüfen.
- Die Unique-Constraint auf `(team_id, member_id)` erlaubt keine zweite Mitgliedschaft derselben Person im selben Team, auch nicht nach einem Austritt. Ob Wiedereintritt mit Historisierung erforderlich ist, muss fachlich geklärt werden.
- Eine Suche nach fachlich naheliegenden Dateinamen liefert für Thekendienste und Clubreisen keine eigenständigen Klassen; dies ist **kein Beweis**, dass sämtliche Funktionen fehlen.
- Für Turniermeldungen, Spielpläne, Ergebnisse, Veranstaltungsanmeldungen, Wartelisten, Clubreisen und Thekendienste wurde in dieser Etappe keine vollständige Controller–Service–Entity–Template-Aufrufkette nachgewiesen. Die betreffenden Anforderungen bleiben **nicht verifiziert**.

## Empfohlene Abnahmetests

1. Mitglied und Team aus unterschiedlichen Vereinen: Speichern wird abgelehnt.
2. Fehlende Sportart ohne Einwilligung: Validierungsfehler.
3. Fehlende Sportart mit Einwilligung: Teamzuordnung und Sportart werden atomar gespeichert.
4. Fehler während der Sportartzuordnung: keine Teilpersistenz.
5. Derselbe Benutzer zweimal im selben Team: Datenbank-Constraint greift.
6. Wiedereintritt nach Austritt: fachlich erwartetes Verhalten festlegen und testen.
7. Administrator aus Verein A versucht Team B einzusehen/zu bearbeiten: erwartetes Verhalten abhängig vom Rollenmodell testen.

## Abnahmestand

Diese Ergebnisse sind statische Quellcode-Nachweise. Es wurden keine HTTP-, Datenbank- oder PHPUnit-Tests ausgeführt. Phase 1 bleibt offen; Phase 2 wurde nicht begonnen.
