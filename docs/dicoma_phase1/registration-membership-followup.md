# DICOMA Phase 1 – Registrierung, Mitgliedschaft, Gäste

Quelle: `dicoma2110.zip`, statische Prüfung am 21.10.2026. Keine produktiven Änderungen, keine Laufzeittests.

## Nachgewiesene Befunde

| ID | Datei/Zeilen | Nachweis | Einstufung | Vorgeschlagener Folgeschritt |
|---|---|---|---|---|
| REG-01 | `src/Controller/RegistrationController.php:58` | Aufruf `$member->setPostal(...)`; `src/Entity/Member.php` definiert `setPostalCode(...)`, nicht `setPostal(...)` | Kritisch: reguläre Registrierung schlägt bei Ausführung dieser Stelle fehl | Aufruf und Formularfeldzuordnung prüfen; Regressionstest |
| REG-02 | `src/Controller/RegistrationController.php:129` | Derselbe nicht existente Methodenaufruf im Installations-Registrierungsweg | Kritisch, wenn Installationsweg aktiviert | Gleiche Korrektur nach Freigabe, Test des Installationswegs |
| REG-03 | `src/Entity/User.php:108-116` | `getRoles()` ergänzt `ROLE_MEMBER` bedingungslos | Hoch: Rolle belegt Mitgliedschaft nicht | Rollenverwendungen vollständig inventarisieren, Mitgliedschaft separat prüfen |
| REG-04 | `src/Entity/Member.php:54-61` | `membershipStatus` hat Standardwert `ACTIVE`; `RegistrationController.php:50-64` legt Member an und setzt keinen `MembershipStatus` | Hoch: Registrierung führt ohne erkennbaren Aufnahmeprozess zu aktivem Status | Aufnahmeprozess und Statusübergänge spezifizieren |
| REG-05 | `src/Entity/CourseParticipant.php:37-39` | Nicht-nullbare Beziehung zu `Member` | Hoch: Gastmodell nicht abgebildet | Personen-/Gastmodell und Datenmigration entwerfen |
| REG-06 | `src/Service/FrontendCourseRegistrationService.php:20,35-36` | Service nimmt `Member` an und lehnt andere Vereinszuordnung ab | Hoch: Gäste ausgeschlossen | Gastanmeldung fachlich integrieren, Vereinskontext sichern |
| REG-07 | `src/Controller/Frontend/CourseRegistrationController.php:21,107-113` | `ROLE_MEMBER` und `currentMember()` mit vorhandenem Member erforderlich | Hoch: Frontend nur für zugeordnete Member | Gast- und Mitgliederpfade differenzieren |
| REG-08 | `src/Controller/Frontend/CourseRegistrationController.php:63-73` | Bestätigung filtert nach Anmelde-ID und aktuellem Mitglied | Positiv: objektbezogene Filterung | Regressionstest erhalten |
| REG-09 | `src/Service/FrontendCourseRegistrationService.php:22-24,42-53` | Transaktion, pessimistische Sperre und Kapazitätsprüfung vorhanden | Positiv; Laufzeitwirkung nicht getestet | Parallelitäts-/Integrationstest in isolierter DB |

## Empfohlene Tests vor Freigabe der Implementierung

1. Registrierungsformular erfolgreich absenden: erwarteter Member und User werden angelegt, keine undefinierte Methode.
2. Registrierung darf nicht automatisch zu aktiver Vereinsmitgliedschaft führen, sofern kein genehmigter Aufnahmeprozess durchlaufen wurde.
3. Gast registriert sich zu offenem Kurs ohne Mitgliedschaft; spätere Mitgliedschaft erhält Ausbildungsdaten.
4. Fremder Verein darf keine Kursanmeldung oder fremde Bestätigung sehen.
5. Gleichzeitige Anmeldung auf letzten Kursplatz verhindert Überbuchung.
6. `ROLE_MEMBER` ohne gültige Mitgliedschaft ermöglicht keinen Mitgliedschafts- oder Vereinsdatenzugriff.

## Grenzen der Prüfung

Nur statische Inspektion ausgewählter Dateien; keine vollständige Symfony-Container-, Doctrine- oder PHPUnit-Laufzeitvalidierung. Ein finaler Migrationsentwurf benötigt Analyse der produktiven Daten und abhängigen Module. Die Befunde sind keine Implementierungsfreigabe.
