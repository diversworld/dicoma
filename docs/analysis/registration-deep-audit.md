# DICOMA Phase 1 – Vertiefte Prüfung Registrierung (21.10.2026)

Quelle: unverändertes Archiv `dicoma2110.zip`. Statische Prüfung, keine Laufzeittests und keine Änderungen am Anwendungscode.

## Nachgewiesene Befunde

| ID | Schwere | Beleg (Archivpfad und Zeilen) | Bewertung |
|---|---|---|---|
| REG-01 | Kritisch | `src/Controller/RegistrationController.php:58,129` ruft `Member::setPostal()` auf; `src/Entity/Member.php:376-384` definiert `setPostalCode()` | Registrierungs- und Installationspfad enthalten einen Aufruf einer nicht vorhandenen Member-Methode; bei Erreichen des Aufrufs Laufzeitfehler. |
| REG-02 | Hoch | `src/Controller/RegistrationController.php:61,132` setzt `setStatus('active')`; `src/Entity/Member.php:618-623` besitzt diese Methode, aber `src/Entity/Member.php:60-61` initialisiert `membershipStatus` separat mit `ACTIVE` | Zwei Statuskonzepte; `setStatus` ändert nicht den enum-basierten Mitgliedschaftsstatus. |
| REG-03 | Hoch | `src/Form/RegistrationFormType.php:123-132` bietet `status` zur Auswahl, aber `RegistrationController.php:61,132` ignoriert den Formularwert | Angezeigter Status wird bei der Registrierung nicht übernommen; zugleich unklar, ob ein öffentlicher Nutzer einen Mitgliedschaftsstatus überhaupt wählen dürfen sollte. |
| REG-04 | Hoch | `src/Controller/RegistrationController.php:49-64,120-135` erzeugt sofort einen Member und setzt keinen Club; `src/Entity/Member.php:212-217` stellt Club-Zuordnung bereit | Öffentliche Registrierung ist nicht erkennbar mit einem Aufnahme-/Genehmigungsprozess verbunden. Vereinsbezug bleibt im untersuchten Ablauf leer. |
| REG-05 | Hoch | `src/Controller/RegistrationController.php:92-115` – Installationsroute ruft `InstallationGuard::denyUnlessExplicitlyEnabled()` auf, setzt bei nicht-null `adminUser` `ROLE_ADMIN` und verifiziert den Benutzer | Bei aktivierter Installation muss zusätzlich sichergestellt werden, dass die Admin-Erstellung nur autorisiert und zeitlich begrenzt möglich ist. Keine Behauptung, die Route sei standardmäßig offen. |
| REG-06 | Mittel | `src/Controller/RegistrationController.php:38-84` persistiert und flusht vor dem Versand zweier E-Mails; `:104-155` analog | E-Mail-Versandfehler können nach erfolgreichem Persistieren auftreten. Retry-/Fehlerbehandlung und Benutzerfeedback prüfen. |
| REG-07 | Hoch | `src/Entity/User.php:108-116` ergänzt `ROLE_MEMBER` für alle Benutzer | Rolle ist kein verlässlicher Beleg für eine genehmigte Vereinsmitgliedschaft. |
| REG-08 | Hoch | `src/Service/FrontendCourseRegistrationService.php:20,35-38` erwartet `Member` und identische Club-Zuordnung; `src/Controller/Frontend/CourseRegistrationController.php:107-113` verlangt Member-Profil | Kursanmeldung ohne Mitgliedschaftsprofil ist im geprüften Frontend-Ablauf nicht unterstützt. |

## Empfehlungen für Phase 2 (noch nicht umgesetzt)

1. Registrierungspfad nach minimaler Korrektur von `setPostal()` durch einen Funktionstest absichern.
2. Mitgliedschaftsstatus als fachlichen Workflow modellieren; `status` und `membershipStatus` vor Änderungen auf alle Verwendungen und Migrationen prüfen.
3. Registrierung, Mitgliedsantrag und Gastkonto konzeptionell trennen; bestehende Member-Daten migrationssicher erhalten.
4. Installation-Guard einschließlich Aktivierung, Erstanlage und Wiederholbarkeit funktional testen.
5. Rollenprüfungen durch objekt- und vereinsbezogene Berechtigungen ergänzen.
6. E-Mail-Fehlerfälle und Transaktionsgrenzen dokumentieren und testen.

## Abnahmekriterien der Analyse

- Befunde REG-01 bis REG-08 in die Funktionsmatrix und priorisierte Roadmap aufnehmen.
- Querverweise auf die relevanten Entities, Formulare, Controller und Services ergänzen.
- Symfony-Runtime, Doctrine-Mapping und PHPUnit weiterhin als nicht ausgeführt markieren, solange Abhängigkeiten fehlen.
- Keine produktiven Codeänderungen vor ausdrücklicher Freigabe.
