# DICOMA – Vertiefte Codeprüfung: Kursverwaltung

Stand 21.10.2026. Quelle: `dicoma2110.zip`, `src/Controller/CoursesController.php`, `config/packages/security.yaml`. Rein statische Prüfung; keine produktiven Änderungen.

## Belegte Befunde

1. **Vereinsgrenzen bei Änderungen (hoch, potenziell):** `show()` prüft ausdrücklich `$course->getClub()?->getId()` gegen `currentClub()->getId()`. `edit()` und `delete()` nehmen ein durch Symfony aufgelöstes `Courses $course` entgegen, besitzen jedoch keine entsprechende sichtbare Clubprüfung und keinen objektbezogenen `denyAccessUnlessGranted`-Aufruf. Beide verlangen `ROLE_ADMIN`, was eine Vereinsgrenzenprüfung nicht ersetzt, sofern Admins vereinsbezogen arbeiten sollen. Ein tatsächlicher Cross-Club-Exploit ist ohne Rollen-/Datenmodell- und Laufzeitprüfung nicht bewiesen.
2. **Kursneuanlage ohne sichtbare Clubzuordnung (hoch):** `new()` erstellt `new Courses()` und persistiert nach Formvalidierung; im Controller erfolgt kein `setClub(currentClub())`. Zu prüfen ist, ob `CoursesType`, Entity-Listener oder Doctrine-Ereignisse die Zuordnung sicher und unveränderbar herstellen.
3. **Dateilöschung (hoch, potenziell):** `delete_image()` erlaubt einem `ROLE_ADMIN` das Löschen einer Datei aus dem gemeinsamen Verzeichnis `bilder_ordner`, nachdem der Dateiname auf einfache Zeichen beschränkt wurde. Eine Prüfung, ob die Datei einem autorisierten Kurs/Verein gehört, ist nicht sichtbar. Eine CSRF-Prüfung ist im Controller nicht sichtbar. Die Dateinamenprüfung verhindert einfache Pfad-Traversal-Muster, ersetzt aber keine Eigentumsprüfung.
4. **Upload-Namen (mittel):** `new()` bildet den Dateinamen aus `$imageFile->getFilename()` und einer geratenen Erweiterung; es fehlt im Controller eine erkennbare zufällige, kollisionsresistente Benennung. Der separate `upload()`-Pfad verwendet dagegen einen bereinigten Namen mit `uniqid()`. Unterschiede sollten konsolidiert werden, nachdem Formularvalidierung und Speicher-Services geprüft wurden.
5. **Zugriffskontrolle differenziert (bestätigt):** `security.yaml` erlaubt `^/courses` als `PUBLIC_ACCESS`; `index`/`show` verlangen `ROLE_MEMBER`, `new`/`edit`/`delete`/`upload`/`delete_image` `ROLE_ADMIN`. Daraus folgt nicht, dass diese Aktionen öffentlich nutzbar sind. Die öffentliche Zugriffsliste und die tatsächlich geschützten Controller müssen jedoch gegen die gewünschte öffentliche Kursanmeldung abgeglichen werden.
6. **CSRF bei Kurslöschung (positiv):** `delete()` verwendet `isCsrfTokenValid('delete'.$course->getId(), ...)`. Das ist ein belegter Schutz auf dieser Route, anders als beim Dateilösch-Endpunkt.

## Konkrete Phase-2-Arbeitspakete (nach Abnahme)

- Für `Courses` clubbezogene Zugriffsvoter/Service-Prüfungen für Lesen, Bearbeiten, Löschen definieren; Rollenmodell und Admin-Reichweite vorher klären.
- Clubzuordnung bei Neuanlage serverseitig erzwingen; Form-Mass-Assignment prüfen.
- Dateioperationen in einen autorisierenden Storage-Service überführen, mit CSRF-Schutz und Eigentumsprüfung.
- Integrationstests: Admin aus Club A darf Kurs/Datei von Club B nicht verändern; Neuanlage ist korrekt zugeordnet; Löschung ohne CSRF scheitert.

## Grenzen der Prüfung

Keine Laufzeit- oder Exploit-Tests durchgeführt; Doctrine-Listener, Voter, Form-Typen und globale Listener wurden hier nicht abschließend ausgeschlossen. Befunde sind als überprüfbare Risiken und nicht als bestätigte Sicherheitsvorfälle einzuordnen.
