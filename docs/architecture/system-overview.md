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

## Zentrale Styles und Farbanpassung

Eigene Styles liegen unter `public/css`, nicht in Twig-Styleblöcken:

- `theme-variables.css`: zentrale Farbdefinitionen für öffentliche Seiten,
  Portale, EasyAdmin und Druckansichten;
- `utilities.css`: gemeinsam genutzte Layout-, Größen-, Abstands- und
  Prioritätsklassen als Ersatz ehemaliger statischer Inline-Deklarationen;
- `pages/*.css`: gezielt geladene Bereichs-/Druckstyles, damit allgemeine
  Selektoren wie `table`, `.panel` und `body` keine anderen Seiten verändern;
- bestehende Stylesheets wie `style.css`, `member-center.css` und `admin.css`:
  verwenden dieselbe zentrale Farbpalette;
- `bootstrap-theme.css`: verbindet die fest kodierten primären
  Bootstrap-4-Komponentenfarben mit Variablen, ohne Vendor-Dateien zu ändern.

Die Farbdatei wird in allen eigenständigen Layouts und über die
EasyAdmin-Assets geladen. Bereichsstyles werden nur dort eingebunden, wo
sie benötigt werden. Die vorhandenen Standardfarbtöne bleiben erhalten;
die unterschiedlichen bisherigen Akzente sind absichtlich getrennt
konfigurierbar:

| Variable | Bedeutung |
|---|---|
| `--dw-primary` | Primärfarbe der eigenen Administrations-/Anwendungsstyles |
| `--dw-public-primary` | Akzentfarbe des öffentlichen Airspace-Themes |
| `--dw-training-primary` | Akzentfarbe der eigenständigen Ausbildungsansichten |
| `--dw-bootstrap-primary` | Primärfarbe der Bootstrap-4-Schaltflächen und Fortschrittsbalken |
| `--dw-bootstrap-primary-hover`, `--dw-bootstrap-primary-active` | Hover-/Aktivzustände; zugehörige Border-/Focus-Variablen mit anpassen |
| `--dw-on-primary` | Beschriftung primärer Bootstrap-Schaltflächen |
| `--dw-background`, `--dw-surface`, `--dw-text`, `--dw-muted`, `--dw-border` | Gemeinsame Grundfarben der darauf angebundenen Komponenten |
| `--dw-success`, `--dw-warning`, `--dw-danger` | Semantische Statusfarben |
| `--dw-calendar-accent`, `--dw-calendar-card` | Kalenderakzent und Kartenhintergrund |

Für eine einheitliche Markenfarbe die gewünschten Akzentvariablen in
`theme-variables.css` gemeinsam ändern; Kontrast sowie Hover-, Fokus- und
Fehlerzustände mitprüfen. Die `--dw-palette-*`-/`--dw-rgba-*`-Variablen
erhalten die vorhandenen weiteren Farbabstufungen, Schatten und Transparenzen.
Auch diese Werte werden ausschließlich zentral gepflegt. Bereits vorhandene
`--dw-admin-*`-Variablen bleiben als Bereichsanbindung erhalten;
`admin-theme.css` wird durch diese Änderung nicht zusätzlich aktiviert.

Die sechs verbliebenen `style`-Attribute enthalten ausschließlich erforderliche
datensatzabhängige CSS-Variablen: vier Fortschrittswerte sowie Stundenoffset
und Ereignisgeometrie im Kalender. Die tatsächlichen CSS-Regeln stehen in
den zentralen Dateien. Statische Farben, Abstände oder andere
Styledefinitionen gehören nicht mehr in Templates. Drittanbieter-CSS und
von EasyAdmin/TinyMCE zur Laufzeit erzeugte Styles bleiben unverändert.

`TemplateStylesTest` schützt diese Trennung und prüft Stylesheet-Verweise,
zentrale Farbdefinitionen und fehlende beziehungsweise selbstreferenzierende
Variablen. Die Browserprüfung lädt alle eigenen CSS-Dateien und prüft die
Themeumschaltung sowie Fortschritts-/Kalendergeometrie ohne Geschäftsdaten
zu ändern.

Prüfstand der CSS-Zentralisierung vom 24.10.2026: 113 Unit-/isolierte Tests
mit 2511 Assertions, vier JavaScript-Tests, alle 202 Twig-Templates sowie
Container-Lint erfolgreich. Die acht bestehenden CSS-Dateien behalten nach
Auflösung der neuen Variablen ihre bisherigen Deklarationen und Farbwerte.
Der Browser las alle 25 eigenen CSS-Dateien ein und bestätigte Prioritäts-/
Hilfsklassen, 37 % Fortschritt als exakt 148 px bei 400 px Bezugsbreite sowie
unveränderte Ereignisposition und -breite im Kalender. Die reale Anmeldeseite
übernimmt eine temporäre Änderung von `--dw-bootstrap-primary` einschließlich
der bestehenden CSS-Transition; nach Rücknahme erscheint wieder die
Originalfarbe. Bei 390, 768 und 1440 px entsteht kein horizontaler
Seitenüberlauf.

## Laufzeit und Prüfungen

- Verifiziert: PHP 8.4.24 in DDEV, Symfony 7.4.21, PHPUnit 13.4.3, MariaDB 11.8.
- Die Produktionsanforderung `php >=8.2` wurde nicht geändert. Diese Umsetzung zertifiziert PHP 8.2 nicht neu; die installierte PHPUnit-13-Entwicklungsumgebung benötigt mindestens PHP 8.4.1.
- CI verwendet deshalb PHP 8.4 und 8.5 mit `intl`, `mbstring`, `pdo_mysql` und `pdo_sqlite`. Ein konfigurierter CI-Job ist noch kein nachgewiesener GitHub-Actions-Lauf.
- Isolierte Persistenz-/HTTP-Tests verwenden ausschließlich SQLite im Speicher. Historische Migrationstests erzeugen zufällig benannte Datenbanken auf einem explizit gewählten Testserver und entfernen diese im Test-Teardown.
- Caches und Logs dürfen bei technischen Prüfungen entstehen; Anwendungsdaten der ausgewählten Entwicklungs-/Produktivdatenbank werden vom Basis-Gate nicht geschrieben.
