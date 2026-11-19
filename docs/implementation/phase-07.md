# DICOMA – Phase 7 – Abschlussbericht

## 1. Status

**Technisch abgeschlossen; fachliche Abnahme noch erforderlich.**

Vor Phase 7 wurde der fehlerhafte Einzelltermin-Buchungsablauf korrigiert.
Eine Kursanmeldung umfasst damit automatisch alle Kurstermine. Darauf aufbauend
wurden Veranstaltungs-, Termin- und Buchungsfunktionen erweitert.

## 2. Implementierte Änderungen

### Korrektur Kursbeteiligung

- Eine aktive Kursanmeldung erzeugt fehlende Terminzuordnungen für alle
  vorhandenen Kurstermine.
- Später freigeschaltete Wartelistenteilnehmer erhalten dieselbe Zuordnung.
- Der frühere Self-Service-Endpunkt für einzelne Terminbuchungen ist mit
  HTTP 410 deaktiviert.
- Kurs-, Termin- und Kalenderansichten bieten keine Einzelbuchung mehr an.
- Teilnehmer können sich über das Portal mit Begründung für einen Termin
  entschuldigen.
- Ausbilder entscheiden atomar zwischen „kein Nachholen erforderlich“ und
  „Nachholtermin erforderlich“.
- Anwesenheit bleibt getrennt als anwesend, entschuldigt/unentschuldigt
  beziehungsweise Nachholbedarf dokumentierbar.

### Veranstaltungen, Training und Buchungen

- `ClubEvent` unterstützt Kategorien Veranstaltung, Tauchgang und Ausfahrt.
- Veranstaltungen können einer Sparte und einer verantwortlichen Person
  zugeordnet werden.
- Wöchentliche und monatliche Serien erzeugen eigenständige Veranstaltungen
  mit Bezug zur Ursprungsveranstaltung.
- Verantwortliche und Veranstaltungsort werden bei Überlappungen geprüft;
  verantwortliche Personen werden auch gegen Ausbildungstermine geprüft.
- Der Vereinskalender kann nach aktiven Sparten gefiltert werden.
- Bestätigungen, Wartelisten-Nachrücken und Stornierungen erzeugen
  Benachrichtigungen in der bestehenden sicheren E-Mail-Outbox.
- Veranstaltungs-Erinnerungen der nächsten 72 Stunden werden idempotent
  durch `app:events:queue-reminders` vorbereitet.
- Bestehende Kapazitäts-, Wartelisten-, Anmeldefrist- und Stornierungsregeln
  bleiben erhalten und werden weiterverwendet.
- Formular und Detailseiten zeigen Kategorie, Sparte und Verantwortung in der
  gemeinsamen, responsiven Formular-/Themendarstellung.

## 3. Geänderte Dateien

- `src/Entity/Booking.php`: Entschuldigungsantrag und Instruktorentscheidung.
- `src/Service/FrontendCourseRegistrationService.php`: automatische
  Terminzuordnung.
- `src/Service/ScheduleParticipantSyncService.php`: Synchronisierung aller
  Kurstermine.
- `src/Controller/Frontend/StudentAttendanceController.php`: Portalentschuldigung.
- `src/Controller/Admin/TrainingUnitBatchController.php` und
  `src/Service/TrainingAssessmentService.php`: atomare Entscheidung.
- `src/Controller/BookingController.php`: Alt-Endpunkt deaktiviert.
- `templates/student/attendance.html.twig`: Entschuldigungsformular.
- `src/Entity/ClubEvent.php`: Art, Sparte, Verantwortung und Wiederholung.
- `src/Form/ClubEventType.php`: neue Veranstaltungsfelder.
- `src/Service/ClubEventSeries.php`: Serienmaterialisierung.
- `src/Service/ClubEventConflictService.php`: Konfliktprüfung.
- `src/Service/ClubCalendar.php`: Spartenfilter.
- `src/Service/ClubEventBooking.php`: Benachrichtigungen.
- `src/Command/QueueClubEventRemindersCommand.php`: Erinnerungen.
- `tests/Integration/CourseAttendanceFlowHttpTest.php`.
- `tests/Integration/Phase7EventHttpTest.php`.

## 4. Datenbank

Neue Migrationen:

- `Version20261119100000`: Entschuldigungsgrund, Anfrage, Entscheidung,
  Prüfzeitpunkt und entscheidendes Mitglied.
- `Version20261119110000`: Veranstaltungsart, Sparte, verantwortliche Person,
  Wiederholung und Serienbezug.

Beide Migrationen wurden lokal sowie auf isolierter MariaDB vorwärts, rückwärts
und erneut vorwärts geprüft. Bestehende Buchungs- und Veranstaltungsdaten
bleiben erhalten.

## 5. Sicherheit

- Teilnehmer können nur Termine ihrer eigenen `CourseParticipant`-Zuordnung
  entschuldigen.
- Fremde Entschuldigungs-URLs liefern 404.
- Entscheidungen sind nur über den bestehenden `TRAINING_ASSESS`-Ablauf
  möglich.
- Veranstaltungsformular und Serien bleiben durch Website-Schreibrechte,
  Vereinskontext und CSRF gesichert.
- Kalenderfilter akzeptieren ausschließlich Sparten des aktiven Vereins.
- Benachrichtigungen enthalten keine internen Teilnehmerlisten.
- Konflikte werden serverseitig erneut vor dem Speichern geprüft.

## 6. Tests

Bestandene Tests:

- Kursbeteiligungs- und Entschuldigungstest: 2 Tests, 22 Assertions.
- Phase-7-Eventtests: 3 Tests, 21 Assertions.
- Bestehende Veranstaltungs-Buchungstests: 16 Tests, 162 Assertions.
- Gemeinsamer Lauf: 21 Tests, 205 Assertions.
- Migrationen: Dry-run, Up, Down und erneutes Up in MariaDB.
- Twig-Lint: 43 Templates.
- Symfony-Container Test/Produktion.
- Doctrine-Schema: Phase-7-spezifische Abweichungen beseitigt; nur bekannte
  `tank`- und `equipment_cash_correction`-Altabweichungen verbleiben.

Browserprüfung:

- Veranstaltungsformular lädt `forms.css`.
- Formular, Karte, Kategorie, Sparte, Verantwortung und Wiederholung sind
  sichtbar.
- Eingaben: 44 px Mindesthöhe und 8 px Radius.

## 7. Bekannte Einschränkungen

- Wiederholungsserien unterstützen wöchentlich und monatlich; Ausnahmen,
  Verschieben einzelner Folgetermine und Kalenderregeln wie „letzter Freitag“
  sind nicht enthalten.
- Teilnehmerkonflikte werden bei Kursplanung und verantwortlichen Personen
  geprüft; ein allgemeiner Personenverfügbarkeitskalender ist nicht Teil der
  Phase.
- Teilnahmehistorie bei allgemeinen Veranstaltungen besteht aus Registrierung,
  Storno und Zahlung; eine Check-in-Anwesenheit wie bei Kursterminen wurde
  nicht eingeführt.
- Erinnerungs-Mails werden vorbereitet; der bestehende tägliche Mailjob mit
  gültigem Absender, Transport und Monitoring bleibt Betriebsaufgabe.

## 8. Inbetriebnahme

1. Migrationen `Version20261119100000` und `Version20261119110000` ausführen.
2. Cache leeren.
3. Cron für `app:events:queue-reminders` einrichten; Versand weiterhin über
   `app:membership:daily --send`.
4. Eine Testveranstaltung als wöchentliche Serie anlegen, prüfen und absagen.
5. Einen Kurs mit mehreren Terminen anmelden und Portalentschuldigung,
   Instruktorentscheidung und Nachholplan fachlich prüfen.

## 9. Abnahmekriterien

- Einleitende Kurskorrektur: erfüllt.
- Wiederkehrende Veranstaltungen: erfüllt.
- Tauchgänge und Ausfahrten: erfüllt.
- Teilnehmerlimits und Wartelisten: bestehend, getestet.
- Buchungs- und Stornierungsregeln: bestehend, ergänzt.
- Verantwortliche Personen: erfüllt.
- Spartenspezifischer Kalender: erfüllt.
- Benachrichtigungen: Outbox und Reminder erfüllt.
- Anwesenheit und Historie: Kurs-Entschuldigungen und Entscheidungen ergänzt.
- Konfliktprüfung: verantwortlich, Ort und Ausbildungstermine geprüft.

Fachliche Abnahme bleibt erforderlich.

## 10. Nächster Schritt

Fachliche Abnahme der Anwesenheits-, Entschuldigungs-, Serien- und
Benachrichtigungsabläufe. Phase 8 wird nicht automatisch begonnen.
