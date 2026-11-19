# DICOMA – Phase 6 – Abschlussbericht

## 1. Status

**Technisch abgeschlossen; fachliche Abnahme noch erforderlich.**

Bestehende Ausbildungsverwaltung wurde erhalten und die offenen Portal-,
Dashboard- und Formulardefizite wurden ergänzt. Die Produktivabnahme der
Ausbildungs- und Prüfungsabläufe durch die Fachverantwortlichen steht aus.

## 2. Implementierte Änderungen

- Gemeinsames, responsives Symfony-/Airspace-Formulardesign für
  `bootstrap_5_layout.html.twig` und vorhandene Bootstrap-3-Seiten.
- Einheitliche Formular-Karte, Pflichtfeldmarkierung, Fokus-, Invalid-,
  Hilfetext- und Fehlermeldungsdarstellung.
- Kursformulare behandeln leere Pflichtfelder als validen Formularfehler
  (HTTP 422), nicht mehr als Setter-Ausnahme (HTTP 500).
- Ausbildungsformulare zeigen Root- und Feldfehler sowie konsistente
  Speichern-/Abbrechen-Aktionen.
- Sammelbewertung, Terminplanversand, Instruktor-Aufgaben und Phase-5-
  Nachrichtenversand wurden in die gemeinsame Darstellung eingebunden.
- Die Ausbildungsleitung erhält:
  - Terminpläne, deren datierte Termine noch nicht freigegeben sind;
  - kommende Prüfungen mit Anzahl erfasster Ergebnisse;
  - Qualifikationen, die abgelaufen sind oder binnen 90 Tagen ablaufen;
  - direkte Navigation in Terminplan und Bewertung;
  - Zugang zur Rollen-/Teilnehmerkommunikation.
- Das Ausbilder-Dashboard verlinkt direkt auf die interne Kommunikation.
- Kursplanung/Freigabe, Teilnehmerverwaltung, Fortschritt, Anwesenheit,
  Bewertungen, Qualifikationsvergabe und Kursabschluss verwenden weiterhin
  die vorhandenen Domain-Services und wurden nicht dupliziert.

## 3. Geänderte Dateien

- `config/packages/twig.yaml`: globale Symfony-Formularsprache.
- `public/css/forms.css`: responsive, barrierearme gemeinsame Formularstile.
- `templates/themes/_assets.html.twig`: Formular-CSS im Frontend.
- `src/Controller/Admin/DashboardController.php`: Formular-CSS im Backend und
  erweiterte Ausbildungsübersicht.
- `templates/training/form.html.twig`: Kartenlayout, Fehler, Aktionen.
- `templates/admin/training/overview.html.twig`: Freigabe-, Prüfungs- und
  Gültigkeitsübersichten.
- `templates/admin/training/timetable.html.twig`,
  `templates/admin/training_results/batch.html.twig`,
  `templates/instructor_portal/tasks.html.twig` und
  `templates/member_portal/messages/new.html.twig`: gemeinsame Formklassen.
- `src/Form/TrainingCourseType.php`: nullsichere Behandlung leerer Kurstitel.
- `tests/Integration/Phase6TrainingPortalHttpTest.php`: Form-, Dashboard- und
  Rollengrenztests.

## 4. Datenbank

Phase 6 benötigt keine neue Entity, Tabelle oder Migration. Freigabezeitpunkt,
Prüfungen, Ergebnisse und Qualifikationsfristen werden aus bestehenden
Eigenschaften berechnet.

## 5. Sicherheit

- Das Ausbilderportal zeigt nur Kurse, bei denen das Mitglied als verantwortlicher
  oder Termin-Ausbilder zugewiesen ist.
- Direkte Bearbeitungs-URLs bleiben durch `COURSE_MANAGE` abgesichert.
- Die Ausbildungsübersicht enthält nur Kurse, die der angemeldete Nutzer
  verwalten darf.
- Bewertungen bleiben an `TRAINING_ASSESS` gebunden.
- Terminplanfreigaben verlangen weiterhin bestehende CSRF-, Kurs- und
  Vereinsprüfung.
- Manipulierte oder leere Formularwerte werden serverseitig abgewiesen.
- Fehlermeldungen werden in normalem Symfony-Markup ausgegeben und escaped.

## 6. Tests und Browserprüfung

Automatisiert ausgeführt:

- `Phase6TrainingPortalHttpTest`: 3 Tests, 23 Assertions bestanden.
- Bestehende Ausbildungsregressionen mit deaktiviertem Legacy-Altprofil:
  - Kursformulare und Nachholtermin: 22 Assertions;
  - benutzerdefinierte Kursfelder: 3 Assertions;
  - Rollen- und Vereinsgrenzen: 26 Assertions;
  - Anwesenheit, Prüfung, Zertifikat und Identität: 29 Assertions.
- PHP-Syntax: erfolgreich.
- Symfony-Container: erfolgreich.
- Twig-Lint für Ausbildungs-, Instruktor-, Ergebnis- und Portaltemplates:
  17 Templates erfolgreich.

Browserprüfung auf der aktiven lokalen Adminsitzung:

- `forms.css` wird auf der Kursbearbeitung geladen.
- `.dw-form` und `.dw-form-card` sind vorhanden.
- Eingabefeld: Mindesthöhe 44 px, Radius 8 px.
- Karteninnenabstand: 32 px.
- Leerer Pflichtwert antwortet mit 422 und sichtbar als invalid markiert;
  Feldfehlertext „Dieser Wert sollte nicht leer sein.“ wird angezeigt.
- Ausbildungsübersicht zeigt Kommunikation, Freigabewarteschlange,
  Prüfungen/Ergebnisse und Kursbelegung.

## 7. Bekannte Einschränkungen

- Die im Master-Prompt genannten dedizierten Kursfreigaben werden durch die
  bestehende Terminplanfreigabe (`schedulePublishedAt`) abgedeckt; ein separates
  allgemeines Kurs-Genehmigungsobjekt wurde nicht eingeführt.
- Kursbezogene Kommunikation nutzt den vorhandenen Phase-5-Versand und ist
  noch kein Nachrichten-Thread mit automatisch vorselektierter Teilnehmerliste.
- Zeitkonflikte und Ausbilderüberlappungen werden weiterhin separat in der
  Planungsprüfung behandelt und nur im Überblick benannt.
- Die breite ältere Testsuite enthält Fixtures mit direkt gespeicherten
  Rollen, die unter aktiviertem Funktionsmodell nicht als wirksam gelten.

## 8. Hinweise zur Inbetriebnahme

1. Cache der Zielumgebung leeren, damit Twig-Formtheme und CSS aktiv sind.
2. Formularoberflächen in Desktop-, Tablet- und Mobilbreite fachlich prüfen.
3. Pro Ausbildungsleiter und Ausbilder mindestens einen Terminplan freigeben,
   eine Prüfung erfassen und eine Qualifikationswarnung kontrollieren.
4. E-Mail-Versand des Terminplans bleibt an gültige Transport- und
  Absenderkonfiguration gebunden.

## 9. Abnahmekriterien

- Bestehende Kursverwaltung erhalten: erfüllt.
- Ausbildungsleiter-Dashboard: erfüllt.
- Trainer-/Ausbilder-Dashboard: bestehend, ergänzt und negativ getestet.
- Kursplanung und Freigaben: erfüllt.
- Teilnehmerverwaltung, Fortschritt und Anwesenheit: bestehende Workflows
  erhalten und mit fokussierten Regressionen geprüft.
- Qualifikationen und Gültigkeiten: bestehendes Modell; Warnanzeige ergänzt.
- Prüfungstermine und Ergebnisse: Terminübersicht und Ergebnisstand ergänzt.
- Rollenbezogene Kommunikation: über Phase-5-Nachrichten eingebunden.
- Theme-konsistente Formulare: technisch und im Browser nachgewiesen.

Fachliche Abnahme bleibt erforderlich.

## 10. Nächster Schritt

Phase-7-Freigabe erst nach fachlicher Abnahme anfordern. Phase 7 wird nicht
automatisch begonnen.
