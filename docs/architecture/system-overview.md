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

## Verwaltungsnavigation und Dashboard

Das EasyAdmin-Menü trennt tägliche Aufgaben von seltener gepflegten Grundlagen:

- **Tägliche Verwaltung:** Mitglieder & Verein, Ausbildung & Kurse,
  Ausrüstung & Verleih sowie Flaschenprüfungen (TÜV).
- **Stammdaten & Einstellungen:** Ausbildungsgrundlagen,
  Ausrüstungsstammdaten sowie System & Einstellungen.

Mitgliedsqualifikationen stehen bei den Mitgliedern; der
Qualifikationskatalog steht bei den Ausbildungsgrundlagen. Planungsvorlagen
und Ausbildungseinheiten sind ebenfalls Grundlagen, keine konkurrierenden
Einstiege ins Tagesgeschäft. Der erste Ausbildungseinstieg ist das
Ausbildungsportal; Kursverwaltung, Kalender und analytische Übersicht
behalten ihre getrennten Aufgaben. Einzelne Lieferanten- und
Administrations-Untermenüs entfallen zugunsten der passenden Gruppen.

Globale Ausrüstungs-, Prüfungs- und Stammdatenbereiche bleiben
Superadmin-only und werden anderen Administratoren nicht angeboten.
Menüsichtbarkeit ersetzt keine Controller-/Voter-Prüfung. Funktionale
Ausbildungsrollen behalten ihre eingeschränkte Portalnavigation; sie erhalten
keine zusätzlichen allgemeinen Administrationsrechte.

Das Dashboard bietet vier direkte Schnellzugriffe und zeigt:

- die Anzahl offener und laufender Kurse;
- offene Mitgliedsanträge, mit den fünf ältesten zur direkten Bearbeitung;
- die nächsten fünf zukünftigen Termine offener/laufender Kurse, mit
  direktem Kurseinstieg und Kalenderlink;
- abgelaufene und innerhalb von 30 Tagen ablaufende Qualifikationen mit
  jeweils höchstens fünf Einträgen und vollständigen Gesamtzahlen.

Vergangene Termine sowie Entwürfe, abgeschlossene und abgesagte Kurse
erscheinen nicht in der Terminliste. Eine fehlende Uhrzeit wird ausdrücklich
als offen dargestellt. Reguläre Administratoren sehen nur ihren Verein,
Superadmins den globalen Bestand; der Kontext ist sichtbar beschriftet.

Qualifikationslisten verlinken berechtigte Mitgliedsdetails. Links auf die
globale Qualifikationsverwaltung erscheinen nur für Superadmins.
„Alle Qualifikationen anzeigen“ übernimmt weder den Ablauf-Filter noch
andere Filter des aktuellen Requests.

Prüfstand vom 24.10.2026: 111 Unit-/isolierte Tests mit 1828 Assertions,
einschließlich Rollen-Menü-Smoke-Test aller sichtbaren Ziele, Vereinsgrenzen,
Listenlimits, Statusauswahl, Filterisolation und englischer neuer
Beschriftungen. Die Browserprüfung mit ausschließlich synthetischen
SQLite-Testdaten bestätigte auf-/zuklappbare Untermenüs und keinen
horizontalen Seitenüberlauf bei 390, 768 und 1440 Pixeln. Die neuen Texte
liegen in den gemeinsamen deutschen, englischen, französischen und
spanischen Katalogen.

## Laufzeit und Prüfungen

- Verifiziert: PHP 8.4.24 in DDEV, Symfony 7.4.21, PHPUnit 13.4.3, MariaDB 11.8.
- Die Produktionsanforderung `php >=8.2` wurde nicht geändert. Diese Umsetzung zertifiziert PHP 8.2 nicht neu; die installierte PHPUnit-13-Entwicklungsumgebung benötigt mindestens PHP 8.4.1.
- CI verwendet deshalb PHP 8.4 und 8.5 mit `intl`, `mbstring`, `pdo_mysql` und `pdo_sqlite`. Ein konfigurierter CI-Job ist noch kein nachgewiesener GitHub-Actions-Lauf.
- Isolierte Persistenz-/HTTP-Tests verwenden ausschließlich SQLite im Speicher. Historische Migrationstests erzeugen zufällig benannte Datenbanken auf einem explizit gewählten Testserver und entfernen diese im Test-Teardown.
- Caches und Logs dürfen bei technischen Prüfungen entstehen; Anwendungsdaten der ausgewählten Entwicklungs-/Produktivdatenbank werden vom Basis-Gate nicht geschrieben.
