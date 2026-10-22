# DICOMA Phase 1 – Authentifizierung, Verifizierung und Passwort-Reset

Quellcodebasis: `dicoma2110.zip`. Statische Prüfung vom 2026-10-22; keine HTTP- oder Integrationstests ausgeführt.

## Belegte Ergebnisse

### AUTH-01 – Reset-Passwort-Repository verweist auf Fake-Implementierung
- Bewertung: **Hoch**
- Fundstelle: `config/packages/reset_password.yaml` Zeile(n) 5
- Einordnung: Echtes Repository vorhanden, aber nicht konfiguriert; Tokenpersistenz und Tokenvalidierung im Laufzeittest klären.

### AUTH-02 – Verifizierungsroute verlangt vollständige Authentifizierung
- Bewertung: **Hoch**
- Fundstelle: `src/Controller/RegistrationController.php` Zeile(n) 166
- Einordnung: Neu registrierte unbestätigte Benutzer können die Voraussetzung möglicherweise nicht erfüllen.

### AUTH-03 – UserChecker sperrt unverifizierte Benutzer bereits vor Anmeldung
- Bewertung: **Hoch**
- Fundstelle: `src/Security/UserChecker.php` Zeile(n) 14
- Einordnung: Zusammen mit AUTH-02 ergibt sich ein widersprüchlicher Verifizierungsablauf.

### AUTH-04 – Logout ist POST-only, Login-Template enthält GET-Link
- Bewertung: **Mittel**
- Fundstelle: `src/Controller/SecurityController.php` Zeile(n) 27
- Einordnung: Der Link in templates/security/login.html.twig ist kein POST-Formular; außerdem ist Logout-CSRF aktiviert.

### AUTH-05 – Login-Passport verwendet CSRF-Token-Badge
- Bewertung: **Positiv**
- Fundstelle: `src/Security/AppCustomAuthenticator.php` Zeile(n) 47
- Einordnung: CSRF-Schutz im benutzerdefinierten Authenticator sichtbar.

### AUTH-06 – Passwort-Reset entfernt Token vor Passwortänderung
- Bewertung: **Positiv**
- Fundstelle: `src/Controller/ResetPasswordController.php` Zeile(n) 111
- Einordnung: Einmalverwendung ist im Controller beabsichtigt; Wirksamkeit hängt von Repository-Konfiguration ab.

### AUTH-07 – Passwort-Reset-Formular erlaubt mindestens sechs Zeichen
- Bewertung: **Mittel**
- Fundstelle: `src/Form/ChangePasswordFormType.php` Zeile(n) 31
- Einordnung: Passwortrichtlinie fachlich und sicherheitstechnisch abstimmen.

### AUTH-08 – Absenderadresse für Reset-Mails fest kodiert
- Bewertung: **Mittel**
- Fundstelle: `src/Controller/ResetPasswordController.php` Zeile(n) 161
- Einordnung: Mandanten-/Deploymentkonfiguration des Absenders prüfen.

### AUTH-09 – Reset-Anfrage gibt für unbekannte E-Mail keine direkte Auskunft
- Bewertung: **Positiv**
- Fundstelle: `src/Controller/ResetPasswordController.php` Zeile(n) 140
- Einordnung: Unbekannte Konten werden auf dieselbe Bestätigungsseite weitergeleitet.

### AUTH-10 – Login besitzt Custom Authenticator und form_login parallel
- Bewertung: **Offen**
- Fundstelle: `config/packages/security.yaml` Zeile(n) 42
- Einordnung: Zusammenspiel, Entry-Point und tatsächlich verwendeter Login-Flow im Runtime-Test verifizieren.

## Empfohlene Integrationstests

1. Registrieren → Bestätigungsmail erhalten → Link als unverifizierter Benutzer öffnen → erfolgreicher Wechsel von `isVerified` auf `true` → anmelden.
2. Reset-Mail an vorhandenes Konto → Token in echter Datenbank gespeichert → Passwortänderung → zweiter Tokenversuch abgelehnt.
3. Reset-Anfrage für unbekanntes Konto: gleiche öffentliche Antwort wie bei bekanntem Konto.
4. Logout per POST mit gültigem/ungültigem CSRF-Token; GET darf keinen Logout auslösen; sämtliche Twig-Links und Formulare überprüfen.
5. Authenticator-Verhalten bei falschem Passwort, unverifiziertem Konto und bereits angemeldetem Benutzer testen.

## Architekturentscheidung und Phase-2-Vorbereitung

Die Verifizierungsroute muss zum `UserChecker` und zum öffentlichen Registrierungsablauf passen. Die offizielle Symfony-Dokumentation zu Security und Login-CSRF sowie die SymfonyCasts-Bundle-Konfiguration sind als Referenz für eine spätere Änderung heranzuziehen. Produktivcode wurde nicht verändert.

**Status:** Phase 1 offen; Symfony-Container, Doctrine-Mapping und Integrationstests bleiben ausstehend.
