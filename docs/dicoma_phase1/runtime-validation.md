# Laufzeit- und Syntaxvalidierung

Stand: 21.10.2026. Unveränderte Quelldateien aus `dicoma2110.zip`; PHP CLI 8.4.24.

- PHP-Dateien aus `src/`, `config/`, `tests/`, `migrations/`: **447**
- PHP-Syntaxfehler: **0**
- Syntaxprüfung ist kein funktionaler Test und keine Symfony-Containerprüfung.
- `vendor/autoload.php` im Archiv: **nicht vorhanden**. Ohne installierte Composer-Abhängigkeiten kann `bin/console` nicht zuverlässig starten.
- Datenbankzugang/Testdatenbank nicht bereitgestellt. Keine Doctrine-Migration ausgeführt.


## Offen

- Composer-Abhängigkeiten in isolierter Umgebung installieren (ohne produktive Daten).
- `php bin/console lint:container`, `debug:router`, Doctrine-Mapping-Validierung und PHPUnit ausführen.
- Migrationsprüfung ausschließlich mit Testdatenbank und Backup.
