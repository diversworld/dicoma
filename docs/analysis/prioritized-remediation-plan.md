# DICOMA – Priorisierte Maßnahmen- und Abnahmematrix

**Stand:** 22.10.2026. **Phase 1, keine Implementierungsfreigabe.**

**Umfang:** 20 Maßnahmen, davon 3 Kritisch, 15 Hoch, 2 Mittel. Die 291 Anforderungen aus der Master-Spezifikation bleiben im ursprünglichen Status „nicht verifiziert“. 51 Anforderungen erhielten ausschließlich schlagwortbasierte Maßnahmen-Hinweise; diese sind keine Erfüllungsnachweise.

## Bewertung und Vorgehen

Prioritäten sind Vorschläge aus statischen Berichten; keine bestätigten Angriffe und keine produktiven Laufzeittests. Kritisch bedeutet vorrangig abzuklären, nicht automatisch ausnutzbar. Bedingte Befunde hängen von Rollenvertrag und Setup-Konfiguration ab.

## Abnahmematrix

| ID | Priorität | Thema | Nachweisstand | Abnahmekriterium |
|---|---|---|---|---|
| P0-01 | Kritisch | Nicht vorhandener Member::setPostal-Aufruf | Quellcodefehler bestätigt; Laufzeitfolge nicht getestet | Registrierung und Setup-Registrierung mit gültigen Daten ausführen; keine 500-Antwort |
| P0-02 | Kritisch | E-Mail-Verifizierung gegen UserChecker und Authentifizierungspflicht | Konfigurationswiderspruch bestätigt; E2E offen | Unbestätigter Benutzer kann Signaturlink erfolgreich bestätigen und danach anmelden |
| P0-03 | Kritisch | Fake-Repository für Passwort-Reset | Konfigurationsbefund bestätigt; Laufzeit offen | Reset-Link persistiert; einmalig nutzbar; abgelaufen abgelehnt |
| P0-04 | Hoch | Mögliche Super-Admin-Vergabe durch normalen Admin | Bedingt: Rollenvertrag offen | Normaler Admin kann Super-Admin weder erstellen noch sich selbst zuweisen, sofern fachlich verboten |
| P0-05 | Hoch | Installationsrouten nach Erstinstallation dauerhaft schließen | Bedingt: Setup-Flag standardmäßig aus | Bei abgeschlossener Installation Setup-Routen unabhängig von Flag gesperrt |
| P0-06 | Hoch | Konfigurationsänderung über GET und fehlende CSRF-/Login-Prüfung bei aktiviertem Setup | Bedingt: Setup muss aktiviert sein | GET ändert nichts; nicht autorisierte POST-Anfrage abgelehnt; CSRF geschützt |
| P0-07 | Hoch | ROLE_ADMIN global oder vereinsgebunden verbindlich entscheiden | Fachentscheidung offen | Rollenvertrag dokumentiert; Tests für Admins zweier Vereine spezifiziert |
| P0-08 | Hoch | EasyAdmin-CRUD-Listen und Objektaktionen auf Club-Grenzen prüfen | Bedingt: abhängig von P0-07 | Zwei-Vereine-Tests für Index/Detail/Edit/Delete je Kernentität |
| P0-09 | Hoch | Rechnungseinstellungen und Übersichten im Dashboard | Vereinsübergreifende Abfrage bestätigt; Zulässigkeit offen | Admin kann fremde Vereinseinstellungen nicht ändern, sofern clubgebunden |
| P0-10 | Hoch | Admin-Sonderaktion equipmentBookingItemAction und Service-Aufrufer | Bedingt: Admin-Reichweite offen | Unberechtigte Buchungs-ID wird vor Mutation abgewiesen |
| P1-01 | Hoch | ROLE_MEMBER ohne Member-Beziehung | Rollenverhalten bestätigt; konkrete Folgewirkung offen | Benutzer ohne Mitgliedschaft erhält keine mitgliedsspezifischen Rechte |
| P1-02 | Hoch | Status und membershipStatus sowie automatische ACTIVE-Vorgabe | Datenmodell-/Ablaufkonflikt bestätigt | Antrag ist bis Freigabe nicht aktiv; Statuswechsel nachvollziehbar |
| P1-03 | Hoch | Gastanmeldung ohne verpflichtendes Member-Objekt ermöglichen | Bestandsgrenze bestätigt | Gast besucht Kurs und wird später Mitglied; Ausbildungsdaten bleiben erhalten |
| P1-04 | Hoch | Kursbearbeitung und -löschung mit Vereinsprüfung | Controller-Prüflücke statisch bestätigt; Exploit offen | Leiter von Verein A kann Kurs von B nicht ändern oder löschen |
| P1-05 | Hoch | Bildlöschung: CSRF und objektbezogene Autorisierung | Statische Prüflücke bestätigt; Exploit offen | Fremdes Kursbild und Cross-Site-Request können keine Datei löschen |
| P1-06 | Hoch | Transaktionaler Sammelvorgang und konkurrierende Ausgaben | Schutzmechanismen statisch bestätigt; Belastungstest offen | Fehler in letzter Position rollt alle Positionen zurück; Parallelverleih verhindert |
| P1-07 | Hoch | Rückdatierte Buchung in abgeschlossene Periode | Prüffall offen | Abgeschlossene Periode bleibt auch bei Rückdatierung unveränderlich |
| P1-08 | Mittel | Logout-GET-Link bei POST-only Route | Widerspruch statisch bestätigt | Logout funktioniert mit POST und gültigem CSRF; GET verändert Session nicht |
| P1-09 | Mittel | DATABASE_URL-Encoding und kontrollierte Migrationen | Statischer Befund bestätigt | Sonderzeichen in Zugangsdaten korrekt; kein schema:update --force |
| P1-10 | Hoch | Symfony-, Doctrine- und PHPUnit-Validierung reproduzieren | Bisher blockiert durch fehlende Abhängigkeiten/intl | Container bootet, Router und Mapping validieren, Tests laufen, Migrationsprobe dokumentiert |

## Freigabe-Gates

**Gate A – Fachentscheidung:** Reichweite von `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`, Mitgliedschaft vs. Gast, Statusübergänge.

**Gate B – Testbarkeit:** Composer-Abhängigkeiten und PHP-Erweiterungen bereitstellen, isolierte Testdatenbank, Mailer-Sandbox, PHPUnit/HTTP-Tests.

**Gate C – Befundverifikation:** Kritische Abläufe mit positiven/negativen HTTP- und DB-Assertions testen; anschließend die 291 Anforderungen fachlich bewerten.

**Gate D – Umsetzungsfreigabe:** Änderungsdateien, Migrationen, Sicherheitskonzept, Rollback und ausdrückliche Freigabe durch den Auftraggeber.

## Symfony-Referenzen

- Security und Autorisierung: https://symfony.com/doc/current/security.html
- Voter: https://symfony.com/doc/current/security/voters.html
- Tests: https://symfony.com/doc/current/testing.html
- Doctrine: https://symfony.com/doc/current/doctrine.html

Die Links sind technische Orientierung, kein Ersatz für die noch ausstehenden Laufzeittests.
