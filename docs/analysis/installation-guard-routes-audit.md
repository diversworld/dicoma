# DICOMA Phase 1 – Installationsrouten und InstallationGuard

Statische Quellcodeprüfung am Originalarchiv `dicoma2110.zip`; keine Laufzeittests und keine Änderungen am Quellcode.


## Bestätigte Befunde

### INST-01 – Standardmäßig gesperrt

`src/Security/InstallationGuard.php:12–18`: Nur der exakte Wert `DICOMA_INSTALL_ENABLED=1` lässt die Installation zu; andernfalls wird HTTP 404 ausgelöst. Beide InstallController-Aktionen und die Installationsregistrierung rufen den Guard auf (`InstallController.php:30,110`; `RegistrationController.php:95`).

### INST-02 – Öffentlich und ohne Firewall

`config/packages/security.yaml:35–37` setzt `security: false` für Pfade mit Präfix `/install` oder `/save_db_config`; `:79–80` erlaubt sie zusätzlich öffentlich. Auch `/register/install/{adminUser}` ist wegen `^/register` öffentlich, besitzt aber einen Guard. Bei aktivierter Installation ist daher keine Authentisierung vorgeschrieben.

### INST-03 – Kein Abschlusszustand im Guard

Der Guard kontrolliert nur die Umgebungsvariable, nicht ob bereits eine Installation oder ein Administrator existiert. Bei versehentlich weiterhin aktivierter Variable bleiben die geschützten Installationsfunktionen grundsätzlich erreichbar.

### INST-04 – GET/POST für schreibende Konfiguration

`InstallController.php:107–110` erlaubt GET und POST für `/save_db_config`; `:111–155` liest POST-Parameter und schreibt `.env.local` ohne sichtbare CSRF-Prüfung. Bei gesetztem Install-Flag kann der Endpunkt Konfigurationswerte überschreiben.

### INST-05 – Datenbank-Schemaänderung

`InstallController.php:160–174` führt `doctrine:database:create` und `doctrine:schema:update --force` aus. Dies ist ein besonders folgenreicher Installationspfad; für produktive Migrationen ist laut Doctrine/Symfony-Praxis ein kontrollierter Migrationsprozess vorzuziehen.

### INST-06 – Zweiter Konfigurationsweg

`InstallController.php:27–104` erzeugt ein Symfony-Formular und schreibt DATABASE_URL in `.env.local`; `/save_db_config` implementiert einen separaten direkten POST-Weg. Die Schutz- und Validierungsregeln sind nicht gleichwertig.

### INST-07 – Installationsregistrierung

`RegistrationController.php:92–95` definiert `/register/install/{adminUser}` als öffentliche GET/POST-Route und ruft den Guard auf. Die genaue Wirkung von `adminUser` muss zusammen mit dem Registrierungsprozess und Rollenmodell getestet werden.


## Bewertung und Grenzen

**Hohes bedingtes Risiko:** Bei `DICOMA_INSTALL_ENABLED=1` sind Konfigurationsänderungen ohne Anmeldung erreichbar; nach regulärer Deaktivierung blockiert der Guard diese Wege. Ob die Variable in einer konkreten Produktivumgebung aktiviert ist, wurde nicht geprüft. Keine Aussage über tatsächliche Ausnutzung.


## Empfohlene Abnahmetests

1. Ohne Install-Flag: GET/POST auf `/install`, `/save_db_config`, `/register/install` müssen 404 liefern.
2. Mit Install-Flag in isolierter Testumgebung: Zugriffsrechte und CSRF-Verhalten kontrollieren; niemals mit produktiven Daten testen.
3. Nach erfolgter Installation: Installationsrouten trotz versehentlich aktivem Flag auf erneute Konfiguration prüfen.
4. Mit Sonderzeichen in Datenbank-Zugangsdaten: korrekte DATABASE_URL-Kodierung und keine Offenlegung von Secrets prüfen.
5. Installationsregistrierung: Vergabe von Adminrechten, doppelte Einrichtung und Race Conditions testen.


## Empfehlung für Phase 2 (noch nicht umsetzen)

Installationsrouten nach Abschluss dauerhaft deaktivieren oder entfernen; Installationszustand serverseitig verifizieren; schreibende Setup-Aktionen auf POST mit CSRF begrenzen; Datenbankkonfiguration nicht über öffentlich erreichbare Produktionsrouten ändern; Doctrine-Migrationen statt `schema:update --force` verwenden.
