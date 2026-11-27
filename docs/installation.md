# Installation und Funktionsprüfung

## Voraussetzungen

- PHP mit `pdo_mysql` oder `pdo_pgsql`, `ctype`, `intl`, `mbstring`, `xml` und `zip`
- Schreibrechte für `var/` sowie `.env.local`
- Eine vorhandene leere Datenbank bzw. Rechte zum Anlegen dieser Datenbank
- Ein Datenbankbenutzer mit Rechten zum Erstellen/Ändern von Tabellen innerhalb dieser vorhandenen Datenbank; das globale Recht zum Anlegen neuer Datenbanken ist nicht erforderlich
- Ausreichender Prozess-Timeout zum Ausführen der Doctrine-Migrationen

## Erstinstallation

1. Setzen Sie für den Webprozess einmalig `DICOMA_INSTALL_ENABLED=1`.
2. Rufen Sie `/install` auf.
3. Hinterlegen Sie die Datenbankverbindung. Die Verbindung und ein neu erzeugtes `APP_SECRET` werden mit Modus `0600` in `.env.local` geschrieben.
4. Fortfahren zu `/install/setup`.
5. Verein, Superadministrator und Mitgliedskonto erfassen.
6. Die Anwendung prüft die Verbindung zur bereits vorhandenen Datenbank, führt alle Doctrine-Migrationen aus und installiert idempotent:
   - Vereinsfunktionen und Modulrechte
   - SKR42-Konten sowie Kassenkonten
   - Mitgliedschafts- und Mahnrichtlinien
   - Datenschutz- und Aufbewahrungsrichtlinien
   - Website-Anzeigeeinstellungen
7. Nach der erfolgreichen Datenanlage werden automatisch `SITE_THEME`, `PUBLIC_SITE_CLUB_ID`, die Absenderadressen, `DICOMA_SINGLE_CLUB_MODE`, `CLUB_FUNCTION_MEMBERSHIP_ENABLED`, der Base64-Schlüssel `DICOMA_MEMBERSHIP_BANK_KEY` sowie die Abschlusswerte `DICOMA_INSTALL_ENABLED=1` und `DICOMA_INSTALL_COMPLETED=1` in `.env.local` geschrieben. Ein bereits vorhandener Bankschlüssel bleibt erhalten.
8. Melden Sie sich nach erfolgreicher Installation als Superadministrator an.

`DICOMA_INSTALL_ENABLED` bleibt standardmäßig `1`, damit Deployments die Konfiguration nicht versehentlich wieder aktivieren müssen. Die Kombination aus `DICOMA_INSTALL_COMPLETED=1` und `var/install-completed.marker` sperrt die Erstinstallation zuverlässig. Die öffentliche Route darf deshalb nicht ausschließlich anhand von `DICOMA_INSTALL_ENABLED` beurteilt werden.

Die Datenbank muss vorab durch den Hosting-Provider oder in der Hosting-Verwaltung angelegt sein. DiCoMa führt absichtlich kein `doctrine:database:create` aus, da verwaltete Hosting-Zugänge meist keine Berechtigung zum globalen Anlegen von Datenbanken besitzen. Bei eingeschränkten Rechten führen Sie
`php bin/console doctrine:migrations:migrate` manuell aus, bevor Schritt 5 wiederholt wird.

## Neue Kernfunktionen prüfen

Im Backend steht Superadministratoren unter **Installation & Funktionsprüfung** eine Übersicht aller fehlenden Kernfunktionen zur Verfügung. Die Installation ist idempotent und überschreibt keine vorhandenen individuellen Datensätze.

CLI-Alternative:

```bash
php bin/console app:installation:features
php bin/console app:installation:features --apply
```

Der Prüfmodus beendet sich mit einem Fehlerstatus, wenn Komponenten fehlen. Damit ist er für Deployment-Prüfungen verwendbar. Der öffentliche Erstinstaller bleibt unabhängig von dieser Updateprüfung gesperrt.
