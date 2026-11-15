# Server-Deployment-Checkliste

## Vor dem Kopieren
- [ ] Backup des Symfony-Projektverzeichnisses
- [ ] MySQL-Dump und Wiederherstellbarkeit geprüft
- [ ] Tatsächliche PHP-, Symfony-, Doctrine- und MySQL/MariaDB-Version dokumentiert
- [ ] `.env.local` und produktive Secrets gesichert und nicht in den Patch aufgenommen

## Nach dem Kopieren
- [ ] `php bin/console about`
- [ ] `php bin/console doctrine:query:sql 'SELECT 1'`
- [ ] `php bin/phpunit tests/Unit/DiveClub` (wenn PHPUnit und benötigte PHP-Erweiterungen vorhanden sind)
- [ ] `APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear`
- [ ] `APP_ENV=prod APP_DEBUG=0 php bin/console lint:container`
- [ ] Website, Login, Mitgliederbereich und bestehende Administration smoke-testen

Den Produktionscache nach jeder Änderung am Anwendungscode neu aufbauen,
insbesondere nach Änderungen an Service-Konstruktoren. Andernfalls kann der
alte Symfony-Container HTTP-500-Fehler verursachen. Die Befehle mit der PHP-Version
des Webservers ausführen; lokal in DDEV beispielsweise:

```sh
ddev exec php bin/console cache:clear --env=prod --no-debug
ddev exec php bin/console lint:container --env=prod --no-debug
```

## Rollback
Bei Problemen ausschließlich die neu hinzugefügten `src/DiveClub`, `tests/Unit/DiveClub` und `docs` entfernen bzw. aus dem Backup wiederherstellen. Da dieser Patch keine Datenbankänderung ausführt, ist keine Schema-Rückmigration erforderlich.
