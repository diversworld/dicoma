# DICOMA Phase 1 – Veranstaltungs- und Anmeldeprozesse: vertiefte Quellcodeprüfung

**Prüfgrundlage:** `dicoma2110.zip`, statische Analyse. **Keine produktiven Änderungen, keine ausgeführten HTTP-/DB-Tests.**

## Abgrenzung

Im Archiv sind Kurs- und Kurstermin-Anmeldungen nachvollziehbar. Diese dürfen **nicht** ohne weitere Belege als vollständige Implementierung der fachlichen Module „Veranstaltungen“, „Clubreisen“ oder „Thekendienste“ gewertet werden. In `src/Entity`, `src/Controller` und `src/Service` wurden keine eigenständigen Klassen mit Dateinamensmustern `Event`, `Trip`, `Duty`, `Shift` oder `Tournament` gefunden. Das ist ein Strukturindiz, **kein Beweis der Abwesenheit**.

## Bestätigte positive Mechanismen

1. `FrontendCourseRegistrationService::register()` sperrt die Kurszeile pessimistisch und verwendet `wrapInTransaction` (`src/Service/FrontendCourseRegistrationService.php:20–59`). Geprüft werden Kursstatus, Anmeldefrist, gleiche Vereins-ID, Dubletten und Kapazität. Die Kapazitätsabfrage zählt Anmeldungen mit Status ungleich `CANCELLED`.
2. `CourseParticipant` besitzt einen Datenbank-Unique-Constraint auf `(course_id, member_id)` (`src/Entity/CourseParticipant.php:12–20`).
3. `CourseRegistrationController::register()` akzeptiert POST und prüft den CSRF-Token (`src/Controller/Frontend/CourseRegistrationController.php:80–89`). Die Bestätigungsseite fragt anhand **ID und aktuellem Mitglied** ab (`:63–76`).
4. `BookingController::bookCourse()` nutzt POST, CSRF, eine gesperrte Schedule-Zeile, eine Transaktion sowie Kurs-, Teilnehmer- und Vereinsprüfungen (`src/Controller/BookingController.php:126–190`).

## Konkrete Inkonsistenzen und Risiken

**EV-02 – Kapazitätsanzeige und Kapazitätsentscheidung divergieren (mittel).** `Courses::hasFreePlaces()` und `getFreePlaces()` rechnen mit `getParticipantCount()` und damit der Größe der gesamten Collection (`src/Entity/Courses.php:295–313,379–381`). Der Registrierungsservice schließt `CANCELLED` ausdrücklich aus (`src/Service/FrontendCourseRegistrationService.php:43–53`). Ein abgesagter Teilnehmer kann deshalb die Anzeige „ausgebucht“ bewirken, obwohl der Service wieder einen Platz vergeben würde. Ob dies im konkreten Template sichtbar wird, ist gesondert zu testen.

**EV-03 – Erneute Anmeldung nach Stornierung gesperrt (mittel).** `findOneBy(course, member)` führt bei jedem bereits vorhandenen Datensatz zum Abbruch, unabhängig vom Status (`src/Service/FrontendCourseRegistrationService.php:38–40`). Wegen des Unique-Constraints kann keine zweite Anmeldung für dieselbe Person und denselben Kurs angelegt werden. Es fehlt in diesem Service ein Reaktivierungsablauf. Ob eine alternative Admin-Funktion dafür existiert, ist offen.

**EV-10 – Keine Wartelistenaufnahme in diesem Registrierungsablauf (offen).** Bei Erreichen der maximalen Teilnehmerzahl wirft der Service eine `DomainException` (`:51–54`), ohne einen Wartelisteneintrag zu erstellen. Eine separate Wartelistenfunktion wurde hier nicht nachgewiesen.

**EV-09 – Fachmodule noch nicht bestätigt.** Termin- und Kursbuchungen dürfen nicht mit Veranstaltungs-, Reise- oder Dienstplanung gleichgesetzt werden. Eigenständige Workflows für Gebühren, Stornierung, Wartelisten-Nachrücken und Teilnahme an Clubreisen bleiben unbestätigt.

## Vorgeschlagene Abnahmetests

| ID | Szenario | Erwartung |
|---|---|---|
| REG-20 | Zwei gleichzeitige Anmeldungen auf letzten Kursplatz | Maximal eine erfolgreiche Anmeldung; keine Überbuchung |
| REG-21 | Stornierte Anmeldung und danach Kapazitätsanzeige | Anzeige und serverseitige Platzentscheidung stimmen überein |
| REG-22 | Stornierter Teilnehmer meldet sich erneut an | Verhalten folgt festgelegter Fachregel (Reaktivierung oder begründete Sperre) |
| REG-23 | Fremdes Mitglied ruft Bestätigung über ID auf | Keine fremden Daten sichtbar |
| REG-24 | Anmeldung auf Kurs eines anderen Vereins | Server weist ab |
| REG-25 | Wartelistenfall bei vollem Kurs | Fachlich definierter Ablauf; derzeit nicht nachgewiesen |
| EVT-01 | Eigenständige Veranstaltung anlegen, anmelden, stornieren | Erst nach Zuordnung konkreter Implementierung prüfbar |
| TRIP-01 | Clubreise mit Plätzen, Gebühren und Stornierung | Erst nach Zuordnung konkreter Implementierung prüfbar |

## Bewertung und nächste Schritte

Die Kursanmeldung weist mehrere gute Sicherheits- und Konsistenzmechanismen auf. Für die eigentlichen Module **Veranstaltungen, Clubreisen und Thekendienste** bleibt die Soll-Ist-Abnahme offen. Nächster sinnvoller Schritt: Anforderungen aus der Master-Spezifikation einzeln den vorhandenen Fachobjekten zuordnen, um belegte Lücken von anders benannten Funktionen zu trennen.

Für spätere Tests: https://symfony.com/doc/current/testing.html und https://symfony.com/doc/current/doctrine.html.
