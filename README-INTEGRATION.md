# DiveClub Manager – Server-Integrationspatch für dicoma1510

Dieser Patch ist für die bestehende Symfony-Installation auf dem Server bestimmt. Er enthält **keine Docker-Konfiguration**, setzt keine lokale Entwicklungsumgebung voraus und ändert weder `DATABASE_URL` noch vorhandene Controller/Entities/Migrationen.

## Kompatibilität und Datenbank

- Namespace `App\DiveClub\...` wird durch den bestehenden Composer-Autoloader `App\: src/` geladen.
- Die vorhandene Doctrine-Konfiguration bleibt erhalten. MySQL/MariaDB wird weiterhin über die vorhandene `DATABASE_URL` in `.env.local` bzw. der Server-Umgebung konfiguriert.
- Dieser Patch fügt noch keine Tabellen hinzu, daher ist dafür keine Datenbankmigration erforderlich.
- Bestehende Entities wie `EquipmentItem`, `Tank`, `TankCheck`, `EquipmentReservation` und `EquipmentBooking` werden bewusst nicht dupliziert. Die nächsten Arbeitspakete müssen diese Modelle anhand der realen Beziehungen erweitern.
- Die bestehende Symfony-/Composer-Version wird nicht hochgestuft.

## Inhalt

- `src/DiveClub/Shared/Domain/TimeRange.php`: halb offenes Zeitintervall `[start, end)` und Pufferberechnung.
- `src/DiveClub/Inspection/Domain/InspectionIntervalPolicy.php`: Prüfintervall von 30 Kalendermonaten nach erfolgreicher Prüfung.
- `src/DiveClub/Billing/Domain/ClubPricingPolicy.php`: centbasierte Preisberechnung ohne ausgewiesene Umsatzsteuer; dies ist eine technische Vorgabe, keine steuerrechtliche Bestätigung.
- `tests/Unit/...`: PHPUnit-Tests für die Domain-Bausteine.
- `docs/`: Analyse, Mapping, Architektur, Risiken und Implementierungsplan.

## Sicheres Einspielen auf dem Server

1. Vor dem Einspielen ein Backup des Anwendungsverzeichnisses und der MySQL-Datenbank erstellen.
2. Patch lokal entpacken und den Inhalt des Patch-Verzeichnisses in das Root-Verzeichnis der vorhandenen Symfony-Anwendung kopieren. Vorhandene gleichnamige Dateien nicht blind überschreiben; dieser Patch ist als additive Ergänzung ausgelegt.
3. Im Symfony-Projektverzeichnis prüfen:

   ```bash
   php -v
   php bin/console about
   php bin/console doctrine:query:sql 'SELECT 1'
   ```

4. Tests ausführen (falls PHPUnit in der installierten Server-Umgebung verfügbar ist):

   ```bash
   php bin/phpunit tests/Unit/DiveClub
   ```

5. Cache leeren und Produktionscontainer prüfen:

   ```bash
   APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear
   APP_ENV=prod APP_DEBUG=0 php bin/console lint:container
   ```

6. Die Anwendung über die bestehende Deployment-Methode neu laden. `.env.local`, Zugangsdaten, `vendor/`, Uploads und sonstige Serverdateien nicht durch Dateien aus dem Patch ersetzen.

## MySQL-Hinweise

- Vor späteren Migrationen die genaue MySQL-/MariaDB-Version sowie `DATABASE_URL` prüfen. Beispiel (nur Vorlage, Zugangsdaten nicht übernehmen):

  `mysql://USER:PASSWORD@127.0.0.1:3306/DATABASE?serverVersion=8.0.37&charset=utf8mb4`

- Bei MariaDB muss `serverVersion` zur tatsächlichen Serverversion passen.
- Migrationen künftig erst mit `php bin/console doctrine:migrations:status` und `php bin/console doctrine:migrations:migrate --dry-run` prüfen, dann mit Backup und Wartungsfenster ausführen.
- Keine `doctrine:schema:update --force`-Aufrufe in Produktion verwenden.

## Noch offen – nicht als fertig implementiert ansehen

Dieser Patch ist ein geprüfter erster Domain-Baustein, nicht die vollständige Anwendung. Noch fehlen u. a. die vollständige Geräte-Domäne, Doctrine-Entities/Migrationen, transaktionssichere Buchungs- und Reservierungsdienste, Workflows, Formulare/Controller, Voters, Dokumente, E-Mail/Messenger und Ende-zu-Ende-Tests. Die bestehende MySQL-Datenbank und Tabellen müssen vor einer strukturellen Migration tatsächlich inspiziert werden.
