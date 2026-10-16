# Phase 2 – implementierter Grundstand (15.10.2026)

## Tatsächlich erstellt
- Symfony-7.4-Projektkonfiguration mit PHP 8.4, PostgreSQL-Docker-Service und CLI/Web-Entrypoints.
- Doctrine-Entities `Club`, `User`, `ClubMembership`, UUIDv7, eindeutige Vereinszuordnung und eindeutige E-Mail.
- Erste explizite PostgreSQL-Migration, Anmeldung mit CSRF, Health-Route und ClubAccess-Anwendungsservice.
- Zwei PHPUnit-Unit-Testfälle zur Mitgliedschaft und Rollenvalidierung.

## Nicht fertig / nicht nachgewiesen
- `composer install`, Doctrine-Migration, PHPUnit und HTTP-Smoke-Test konnten ohne Composer/Docker und ohne installierte Abhängigkeiten nicht ausgeführt werden. PHP-Syntaxprüfungen sind separat erfolgt.
- Abhängigkeiten sind Zielversionen, keine durch Dependency-Resolution bestätigte Kompatibilitätsmatrix.
- Passwort-Reset, Benutzeranlage mit PasswordHasher, Admin-Dashboard, Mandantenkontext, Voter, echte DB-Isolationstests, UX-Asset-Pipeline und Produktions-Deployment sind offen.
- `ROLE_ADMIN` ist noch keiner vereinsbezogenen Rolle zugeordnet: Admin-Oberfläche ist nicht implementiert.
- `ClubAccess` muss bei allen späteren mandantengebundenen Lese-/Schreiboperationen verbindlich aufgerufen werden. Kein globaler Filter ist aktuell aktiv.
- Noch keine Rechnungs-/Prüffristlogik; diese bleibt bis zur fachlichen Freigabe blockiert.

## Nächste überprüfbare Schritte
1. `composer validate && composer install`, fehlende Symfony-Rezepte/Assets ergänzen.
2. `docker compose up -d --build` und `docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction`.
3. `docker compose exec app vendor/bin/phpunit` und HTTP-Test `/health`.
4. Benutzeranlage über PasswordHasher, Vereinswechsel und club-spezifische Voter inklusive Cross-Tenant-Integrationstests.
5. Erst danach Equipment-Entities und Verwaltungsoberflächen.
