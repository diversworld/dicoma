# Systemübersicht – Phase 2

DICOMA bleibt eine bestehende Symfony-7.4-Anwendung mit Doctrine ORM 2, Twig, Symfony Security und EasyAdmin. Fachlogik bleibt in den vorhandenen Services, HTTP-Orchestrierung in den vorhandenen Controllern. MySQL/MariaDB ist die von der vorhandenen Migrationshistorie unterstützte Datenbank.

```text
HTTP / Symfony Security / CSRF
  -> Controller und Symfony Forms / EasyAdmin
     -> Voter und fachliche Services
        -> Doctrine-Repositories und Datenbank-Constraints
```

Personenstammdaten werden zentral gehalten; fachliche Mitgliedschaften, Benutzerkonto und Rollen bleiben getrennt. Siehe [Datenmodell](data-model.md) und [Rollenvertrag](roles-permissions.md).

## Vereinskontext

Der Verein eines regulären Administrators wird weiterhin über `User.member.club` bestimmt. Es wird kein frei manipulierbarer Vereinsparameter als Berechtigungsquelle eingeführt. Ein fehlender Kontext führt zur Verweigerung, nicht zu einer globalen Standardabfrage.

Superadmins dürfen vereinsübergreifend arbeiten. Vorhandene Superadmin-only-Sperren globaler Legacy-Verwaltungen werden nicht pauschal aufgehoben. Insbesondere wird keine vermeintliche vollständige Mandantentrennung allein durch eine neue `ClubContext`-Klasse oder einen globalen Doctrine-Filter behauptet.

## Laufzeit und Prüfungen

- Verifiziert: PHP 8.4.24 in DDEV, Symfony 7.4.21, PHPUnit 13.4.3, MariaDB 11.8.
- Die Produktionsanforderung `php >=8.2` wurde nicht geändert. Diese Umsetzung zertifiziert PHP 8.2 nicht neu; die installierte PHPUnit-13-Entwicklungsumgebung benötigt mindestens PHP 8.4.1.
- CI verwendet deshalb PHP 8.4 und 8.5 mit `intl`, `mbstring`, `pdo_mysql` und `pdo_sqlite`. Ein konfigurierter CI-Job ist noch kein nachgewiesener GitHub-Actions-Lauf.
- Isolierte Persistenz-/HTTP-Tests verwenden ausschließlich SQLite im Speicher. Historische Migrationstests erzeugen zufällig benannte Datenbanken auf einem explizit gewählten Testserver und entfernen diese im Test-Teardown.
- Caches und Logs dürfen bei technischen Prüfungen entstehen; Anwendungsdaten der ausgewählten Entwicklungs-/Produktivdatenbank werden vom Basis-Gate nicht geschrieben.
