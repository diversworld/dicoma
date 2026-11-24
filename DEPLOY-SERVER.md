# Server-Deployment-Checkliste

## Vor dem Kopieren

- [ ] Backup des Symfony-Projektverzeichnisses
- [ ] MySQL-Dump und konsistenter Dateisnapshot für private Dokumente
- [ ] Wiederherstellbarkeit des Backups geprüft
- [ ] PHP-, Symfony-, Doctrine- und MySQL/MariaDB-Version dokumentiert
- [ ] `.env.local` und produktive Secrets nicht in den Patch übernommen
- [ ] `DICOMA_MEMBERSHIP_BANK_KEY`, `MAILER_DSN`, `MEMBERSHIP_MAIL_FROM`,
      Datenbankzugang und Speicherpfade geprüft
- [ ] `DICOMA_CLAMAV_COMMAND` zeigt auf einen ausführbaren ClamAV-Client;
      EICAR-Testupload wird vor Freigabe abgewiesen
- [ ] Bankprofil für pain.008.001.02 einschließlich BIC-/IBAN-Regeln mit einem
      Testupload durch die Zielbank bestätigt und im Freigabenachweis eingetragen
- [ ] Steuerberater hat die 49 internen SKR42-Unterkonten freigegeben
- [ ] Aufbewahrungsfristen fachlich/rechtlich bestätigt
- [ ] Manuelle WCAG-2.2-AA-Prüfung gemäß `docs/implementation/wcag-22-aa.md`
      mit Bericht dokumentiert

## Deployment

1. Wartungsmodus aktivieren und Dateischreibzugriffe begrenzen.
2. Datenbank- und Dateibackup konsistent erstellen.
3. Anwendungscode bereitstellen.
4. Migrationen prüfen und ausführen:

```sh
php bin/console doctrine:migrations:migrate --dry-run --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:schema:validate
```

5. Produktionscache neu bauen und Services prüfen:

```sh
APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
APP_ENV=prod APP_DEBUG=0 php bin/console lint:container
APP_ENV=prod APP_DEBUG=0 php bin/console lint:twig templates
APP_ENV=prod APP_DEBUG=0 php bin/console app:release-check --fail-on-blocked
```

In DDEV entsprechend `ddev exec php ...` verwenden.

## Abnahme

- [ ] Website, Login und Mitgliederbereich
- [ ] Vorstandssicht, Sitzungen, Aufgaben und Dokumentdownload
- [ ] Finanzen, Jahreslauf, Mahnung und Zahlungsverbuchung
- [ ] SEPA-Entwurf, Vier-Augen-Freigabe und pain.008-Export
- [ ] Datenschutz-Dry-Run ohne gespeicherte oder freigegebene Richtlinie
- [ ] Private Dokumente bleiben außerhalb des öffentlichen Webroots
- [ ] Fokussierte Integrationssuite und Mapping-Validierung erfolgreich

Die Style-Tests können aktuelle, fachlich unabhängige Baselinefehler enthalten.
Neue Fehler müssen trotzdem vor Produktivsetzung bewertet und behoben werden.

## Periodische Jobs

- Täglich, z. B. 05:30: `php bin/console app:membership:daily --send`
- Täglich, z. B. 06:00: `php bin/console app:board:task-reminders`
- Täglich, z. B. 06:15: `php bin/console app:membership:queue-dunning`
- Monatlich nach fachlicher Freigabe:
  `php bin/console app:membership:monthly-billing --period=YYYY-MM --due=YYYY-MM-DD --confirm`
- Datenschutzbereinigung nur nach Fachfreigabe und zunächst als Dry-Run:
  `php bin/console app:privacy:retention`

Jobs mit Output und Exitcode überwachen. Keine Bank-, E-Mail- oder
Verschlüsselungssecrets in Logs aufnehmen. Bei mehreren Ausführungsknoten die
bereits vorhandenen Lock-Mechanismen bzw. externes `flock` nutzen.

## Rollback

1. Anwendung stoppen bzw. Wartungsmodus aktivieren.
2. Datenbank- und Dateirestore aus dem konsistenten Vor-Deployment-Backup.
3. Vorherigen Application-Stand bereitstellen.
4. Produktionscache neu bauen.
5. Keine destruktiven Down-Migrationen ungeprüft produktiv ausführen;
   irreversible Migrationen gemäß ihrer Fehlermeldung separat behandeln.
