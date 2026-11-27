# Installation und Funktionsprüfung

## Voraussetzungen

- PHP mit `pdo_mysql` oder `pdo_pgsql`, `ctype`, `intl`, `mbstring`, `xml` und `zip`
- Schreibrechte für `var/` sowie `.env.local`
- Eine vorhandene leere Datenbank bzw. Rechte zum Anlegen dieser Datenbank
- Ausreichender Prozess-Timeout zum Ausführen der Doctrine-Migrationen

## Erstinstallation

1. Setzen Sie für den Webprozess einmalig `DICOMA_INSTALL_ENABLED=1`.
2. Rufen Sie `/install` auf.
3. Hinterlegen Sie die Datenbankverbindung. Die Verbindung und ein neu erzeugtes `APP_SECRET` werden mit Modus `0600` in `.env.local` geschrieben.
4. Fortfahren zu `/install/setup`.
5. Verein, Superadministrator und Mitgliedskonto erfassen.
6. Die Anwendung erstellt die Datenbank falls möglich, führt alle Doctrine-Migrationen aus und installiert idempotent:
   - Vereinsfunktionen und Modulrechte
   - SKR42-Konten sowie Kassenkonten
   - Mitgliedschafts- und Mahnrichtlinien
   - Datenschutz- und Aufbewahrungsrichtlinien
   - Website-Anzeigeeinstellungen
7. Melden Sie sich nach erfolgreicher Installation als Superadministrator an.
8. Entfernen Sie `DICOMA_INSTALL_ENABLED=1` bzw. setzen Sie den Wert auf `0`. Der Marker `var/install-completed.marker` verhindert zusätzlich eine zweite Erstinstallation.

Bei eingeschränkten Hosting-Rechten können Datenbank und Migrationen nicht durch den Webprozess ausgeführt werden. Führen Sie dann manuell
`php bin/console doctrine:database:create --if-not-exists` und
`php bin/console doctrine:migrations:migrate` aus, bevor Schritt 5 wiederholt wird.

## Neue Kernfunktionen prüfen

Im Backend steht Superadministratoren unter **Installation & Funktionsprüfung** eine Übersicht aller fehlenden Kernfunktionen zur Verfügung. Die Installation ist idempotent und überschreibt keine vorhandenen individuellen Datensätze.

CLI-Alternative:

```bash
php bin/console app:installation:features
php bin/console app:installation:features --apply
```

Der Prüfmodus beendet sich mit einem Fehlerstatus, wenn Komponenten fehlen. Damit ist er für Deployment-Prüfungen verwendbar. Der öffentliche Erstinstaller bleibt unabhängig von dieser Updateprüfung gesperrt.
