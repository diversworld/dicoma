# DICOMA Phase 1 – Vertiefung Setup und Datenbankkonfiguration

Statische Quellcodeprüfung von `dicoma2110.zip`. Keine HTTP- oder Datenbanktests; keine produktiven Codeänderungen.

## Nachgewiesene Befunde

### SETUP-08: Installationsregistrierung (Hoch (bei aktiviertem Setup))
**Fundstelle:** `src/Controller/RegistrationController.php`, Zeilen 92-115.
Die Bedingung adminUser !== null wird für jeden erfolgreich gebundenen nicht-null-Wert erfüllt; die Route besitzt keine Prüfung eines einmaligen Setup-Tokens oder vorhandenen Admins.

**Einordnung:** Ein aktiviertes Setup erlaubt über den Admin-Registrierungsweg die Vergabe von ROLE_ADMIN; wegen setPostal()-Aufruf ist die erfolgreiche Persistierung derzeit nicht belegt.

### SETUP-09: DB-URL-Sonderzeichen (Mittel)
**Fundstelle:** `src/Controller/InstallController.php`, Zeilen 59-63,122-130.
Zugangsdaten und DB-Name werden ungefiltert in eine URL interpoliert; keine URL-Kodierung sichtbar.

**Einordnung:** Sonderzeichen wie @, /, #, ? oder : können zu ungültiger oder falsch interpretierter DATABASE_URL führen.

### SETUP-10: Konfigurationsdatei-Injektion (Hoch (bei aktiviertem Setup))
**Fundstelle:** `src/Controller/InstallController.php`, Zeilen 111-155.
Der zweite Setup-Endpunkt übernimmt POST-Felder ohne Formularvalidierung und setzt sie direkt in eine dotenv-Zeile ein.

**Einordnung:** Anführungszeichen und Zeilenumbrüche können das dotenv-Format beschädigen oder weitere Einstellungen beeinflussen; tatsächliche Auswirkungen nicht getestet.

### SETUP-11: Fehlerbehandlung (Mittel)
**Fundstelle:** `src/Controller/InstallController.php`, Zeilen 160-185.
ProcessFailedException wird bei fehlgeschlagenen Konsolenbefehlen geworfen, aber nur IOExceptionInterface abgefangen.

**Einordnung:** Fehlgeschlagene DB-Befehle können als unbehandelte Exception enden; DB-Konfiguration wurde zuvor schon gespeichert.

### SETUP-12: HTTP-Methoden (Mittel)
**Fundstelle:** `src/Controller/InstallController.php`, Zeilen 107-117.
saveDbConfig erlaubt GET und POST, liest jedoch ausschließlich request->request (POST-Body).

**Einordnung:** GET-Aufruf kann mit fehlenden Eingabewerten trotzdem den Konfigurationspfad betreten; konkrete PHP-/Symfony-Reaktion nicht ausgeführt.

### SETUP-13: Doppelte Implementierung (Mittel)
**Fundstelle:** `src/Controller/InstallController.php`, Zeilen 27-104,107-189.
Zwei unabhängige Wege zum Schreiben von DATABASE_URL mit unterschiedlicher Validierung und Ablaufsteuerung.

**Einordnung:** Die Setup-Flows können sich unterschiedlich verhalten und erfordern getrennte Regressionstests.

### SETUP-14: Installationsgeheimnisse (Offen)
**Fundstelle:** `/.gitignore`, Zeilen 1-25.
Die .gitignore ignoriert .env.local und private Produktions-Secrets; im ZIP sind .env und .env.test vorhanden, aber keine .env.local.

**Einordnung:** Dies ist kein Beweis für sichere produktive Secret-Verwaltung; Deployment- und Hosting-Konfiguration fehlen.

## Abgrenzung

Die Sicherheitsrelevanz der öffentlich erreichbaren Setup-Funktionen besteht nur bei aktiviertem `DICOMA_INSTALL_ENABLED=1`. Der Guard blockiert andernfalls mit 404. Ein erfolgreicher Angriff oder eine tatsächlich verwendete Produktivkonfiguration ist nicht nachgewiesen.

Der `setPostal()`-Aufruf in der Installationsregistrierung ist nach vorangegangener Analyse ein eigenständiger Laufzeitblocker; deshalb darf aus der sichtbaren Rollenvergabe nicht auf eine erfolgreich angelegte Administratoridentität geschlossen werden.

## Konkrete Phase-2-Arbeitspakete (nur Vorschlag)

1. Setup-Zugang nach erfolgreicher Erstinstallation dauerhaft und serverseitig sperren; Installation nicht öffentlich über eine dauerhaft aktivierbare Variable anbieten.
2. Zweiten Setup-Endpunkt entfernen oder beide Wege auf einen validierten POST-/CSRF-geschützten Ablauf konsolidieren.
3. Doctrine-DBAL-kompatible Kodierung der URL-Komponenten und robuste dotenv-Schreiblogik verwenden; Zugangsdaten nicht in Logs schreiben.
4. Schemaänderungen über versionierte Doctrine-Migrationen durchführen; Fehler vor und nach Konfigurationsänderungen nachvollziehbar behandeln.
5. Isolierte Integrationstests für deaktiviertes/aktiviertes Setup, Sonderzeichen, Fehler-Rollback und einmalige Admin-Anlage.

## Referenz

Symfony: https://symfony.com/doc/current/security.html und https://symfony.com/doc/current/configuration.html
