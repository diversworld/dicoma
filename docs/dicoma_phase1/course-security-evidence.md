# DICOMA – Nachweisprüfung Kursverwaltung (Phase 1)

Stand: 2026-10-21. Quelle: `dicoma2110.zip`, ausschließlich statische Quellcodeprüfung. Keine produktiven Änderungen; keine Laufzeit- oder Penetrationstests.

## Konkrete Befunde

| ID | Datei und Stelle | Quellcode-Nachweis | Bewertung | Maßnahme / Test |
|---|---|---|---|---|
| C-01 | `src/Controller/CoursesController.php`, `edit()` | `#[IsGranted('ROLE_ADMIN')]`, aber vor Formularbearbeitung keine Prüfung `course->getClub() === currentClub()` | Hoch: möglicher vereinsübergreifender Zugriff für Admins | Voter bzw. Objektprüfung; Test mit Admin A und Kurs B (GET und POST verweigert) |
| C-02 | dieselbe Datei, `delete()` | CSRF-Prüfung vorhanden, Objekt-/Vereinsprüfung vor `remove()` nicht sichtbar | Hoch | Vereinsberechtigung vor Löschung, negativer Integrationstest |
| C-03 | dieselbe Datei, `new()` | Neue `Courses`-Entity wird ohne sichtbaren Aufruf `setClub(currentClub())` persistiert; `Courses::$club` ist nullable | Hoch: möglicher Kurs ohne Vereinszuordnung | Club serverseitig setzen; Test der gespeicherten Zuordnung |
| C-04 | dieselbe Datei, `delete_image()` | Dateiname per Regex eingeschränkt; keine sichtbare CSRF-Prüfung oder Zuordnung der Datei zu einem berechtigten Kurs; `unlink()` direkt | Hoch: unautorisierte Dateilöschung durch berechtigte Admins möglich, sofern Dateiname bekannt | CSRF, referenzierte Datei/Kurs-Eigentümerschaft prüfen, sichere Dateiverwaltung; Negativtests |
| C-05 | dieselbe Datei, `upload()` und `new()` | Zwei unterschiedliche Benennungspfade; in `new()` kein explizites MIME-/Größen-Constraint am Feld (`CoursesType`) | Mittel bis hoch | Zentrale Upload-Policy, erlaubte MIME-Typen/Größe, zufällige Dateinamen, Tests |
| C-06 | `src/Entity/Courses.php` | `setImage()` schreibt Bildwert via `error_log()` | Mittel: unnötige Protokollierung und Log-Hygiene | Debug-Ausgabe entfernen (erst nach Freigabe), Log-Test |
| C-07 | `config/packages/security.yaml` + Controller | `^/courses` ist `PUBLIC_ACCESS`, aber einzelne Methoden haben `#[IsGranted]` | Kein pauschal offener Kurscontroller nachgewiesen | Alle Routen mit Attributen und Votern in Laufzeit abgleichen |
| C-08 | `CoursesController.php`, `show()` | Vereinsvergleich und Status `OPEN` vor Rendering vorhanden | Positiver Kontrollnachweis | Regressionstest für fremden und geschlossenen Kurs |

## Grenzen der Aussage

Andere Controller, Doctrine-Listener, Security-Voter und Form-Events könnten einzelne Risiken abmildern; eine vollständige End-to-End-Bewertung setzt deren Prüfung und Laufzeittests voraus. Die hier festgehaltenen Lücken sind *fehlende sichtbare Prüfungen im untersuchten Controller*, nicht bestätigte ausnutzbare Schwachstellen.

## Phase-1-Abnahme

Diese gezielte Nachweisprüfung verbessert die Beleglage der Sicherheitsanalyse, ersetzt aber weder die vollständige Funktionsmatrix noch Container-, Doctrine-, Migrations- und Berechtigungstests. Phase 1 bleibt offen. Änderungen sind ausdrücklich bis zur Freigabe zurückgestellt.
