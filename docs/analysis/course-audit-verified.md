# Phase 1 – Nachweisprüfung Kursverwaltung und Domänenklassen

Stand: 21.10.2026. Quelle: unverändertes `dicoma2110.zip`, statische Prüfung. Keine Laufzeittests und keine produktiven Codeänderungen.

## Belegte Befunde mit Quellcodebezug

| ID | Quellstelle | Beobachtung | Bewertung | Nachweis für Phase 2 |
|---|---|---|---|---|
| C-01 | `src/Controller/CoursesController.php:116` | `show()` vergleicht Kursverein mit `currentClub()` und verlangt `CourseStatus::OPEN`. | Positiver statischer Schutzbeleg, keine Laufzeitbestätigung. | Zugriff mit eigenem/fremdem Verein testen. |
| C-02 | `src/Controller/CoursesController.php:129` | `edit()` hat `ROLE_ADMIN`, aber keinen expliziten `currentClub()`-Abgleich. | Hoch: möglicher objektbezogener Zugriff über Kurs-ID. | Cross-Club-GET/POST mit Admin-Rolle prüfen. |
| C-03 | `src/Controller/CoursesController.php:176` | `delete()` hat `ROLE_ADMIN` und CSRF-Tokenprüfung, jedoch keinen expliziten Vereinsabgleich. | Hoch: mögliches Cross-Club-Löschen. | Fremden Kurs mit gültigem CSRF-Token testen. |
| C-04 | `src/Controller/CoursesController.php:42` | `new()` persistiert einen Kurs ohne sichtbares `setClub(currentClub())`. | Hoch: Zuordnung nur sicher, falls anderweitig gewährleistet. | Formular/Listener/DB-Constraints prüfen. |
| C-05 | `src/Controller/CoursesController.php:188` | `delete_image()` prüft Dateinamensformat, jedoch weder CSRF-Token noch die Zuordnung der Datei zu einem Kurs. | Hoch: Dateioperation ohne sichtbaren Objektbezug. | Unberechtigte Dateilöschung mit/ohne Token testen. |
| C-06 | `src/Controller/CoursesController.php:73` | `upload()` protokolliert `$request->files->all()` auf INFO-Level und nutzt ein anderes Benennungsschema als `new()`. | Mittel: Datenminimierung und Dateiverwaltung überprüfen. | Log-Inhalte und Dateinamenskollisionen testen. |
| C-07 | `config/packages/security.yaml:85` | `^/courses` ist in `access_control` `PUBLIC_ACCESS`; Controller-Methoden besitzen teils `#[IsGranted]`. | Keine pauschale Schutzlücke ableitbar; wirksame Regelkombination prüfen. | `debug:router` und HTTP-Tests für alle Kursrouten. |

## Klasseninventar

`domain-class-inventory.csv` weist **exakte Dateinamen** nach. Fehlende gleichnamige Dateien beweisen **nicht**, dass ein Fachprozess fehlt: andere Benennungen, dynamische Funktionen und Verknüpfungen müssen geprüft werden.

## Grenzen und Abnahmerelevanz

Die 291 Anforderungen aus `requirements-traceability.csv` sind weiterhin nicht einzeln funktional verifiziert. Diese Prüfung verbessert die Evidenz für ausgewählte Kursfunktionen, ersetzt aber keine vollständige Funktionsmatrix. Laufzeitprüfungen von Symfony/Doctrine und PHPUnit bleiben ausstehend. Für eine Phase-1-Abnahme sind entweder erfolgreiche Prüfergebnisse oder klar dokumentierte Blockaden sowie ein vollständig bewerteter Soll-Ist-Abgleich erforderlich.
