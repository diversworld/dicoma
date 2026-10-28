# Portal- und Navigationskonsolidierung

Stand: 28.10.2026. Dies ist eine abgeschlossene technische Teilphase der
Master Specification, keine Vollständigkeitsfreigabe sämtlicher Fachmodule.

## Einstiege

| Bereich | Route | Darstellung / Voraussetzung |
|---|---|---|
| Gemeinsamer Einstieg | `/portal`, optional `/portal/en`, `/portal/fr`, `/portal/es` | Angemeldete Konten; persönliche und administrative Angebote getrennt |
| Persönliche Ausbildung | `/training` | Eigene Teilnahmen im Frontend, auch für Personal |
| Ausbildungsverwaltung | `/admin/training` | Vorhandene Kursrechte und Vereinszuordnung, Backend |
| Persönlicher Kalender | `/calendar` | Vorhandene Kalenderberechtigung, Frontend auch für Admins |
| Kompatibler Verwaltungskalender | `/admin/calendar` | Identische Datenprojektion; Admins im Backend, bisher berechtigte Mitglieder in Leseansicht |
| Sportverwaltung | `/admin/teams`, `/admin/tournaments` | `CompetitionAccess`; Backend auch für zugeordnete Mannschaftsbetreuung |
| Vereinsinformationen | `/club` | Öffentlich, nur aktiver konfigurierter Website-Verein |
| Kontakt | `/contact` | Öffentlich, Organisationskontakt ohne interne Notizen oder Personenlisten |

Die bestehenden Gerätewart-, Ausbilder-, Thekendienst-, Redaktions- und
EasyAdmin-Einstiege bleiben erhalten. Alle erhalten einen gemeinsamen Rückweg
zur Portalübersicht und zur öffentlichen Website. Ein direkter Aufruf wird
weiterhin im Fachcontroller und gegebenenfalls durch Voter/Services geprüft.

## Navigation und Login

`PortalNavigation` ist die gemeinsame Quelle für Header, Footer und
Portalübersicht. Menüs haben eindeutige IDs, keine leeren Verwaltungsgruppen,
übersetzte Labels und aktive Seitenkennzeichnung. Gäste finden Ausbildung,
Fortschritt, Anwesenheit und eigene Anmeldungen, ohne künstliche Mitgliedschaft.
Eine Funktionsrolle ohne aktive Mitgliedschaft erhält nur die mit ihrem
Vereinskontext tatsächlich erreichbaren Einstiege.

Login-Fallback, Form-Login-Konfiguration und erneuter Aufruf von `/login`
verwenden `/portal`. Ein vor dem Login gespeicherter geschützter Zielpfad
bleibt vorrangig. Logout bleibt ein CSRF-geschützter POST.

Die öffentlichen Vereins-/Kontaktseiten verwenden die bestehenden Felder des
über `PUBLIC_SITE_CLUB_ID` ausgewählten Vereins. Mit dieser Konfiguration sind
Beschreibung und Organisationskontakt öffentlich. `notes`, Personen-/Kontodaten
und fremde Vereine werden niemals angezeigt. Ist kein aktiver Website-Verein
konfiguriert, fehlen die Menüeinträge und die beiden direkten Seiten liefern
404. Die Redaktion und deren Veröffentlichungsregeln bleiben unverändert.

## Layout und Responsive-Verhalten

`base.html.twig` erbt wieder vom konfigurierten `SiteTheme`. Die zuvor leere
Basis hatte unter anderem Startseite, Registrierung und Login unbenutzbar
gemacht. Frontendseiten erhalten kein EasyAdmin-Layout allein aufgrund der
Benutzerrolle; Verwaltungsansichten verwenden keinen öffentlichen Seitenrahmen.

Die zusätzliche, auf die öffentliche Navigation begrenzte CSS-Datei
`public/css/pages/portal-navigation.css` lässt lange Menüs auf Desktopbreiten
umbrechen, begrenzt das Logo auf kleinen Geräten und macht Tastaturfokus
sichtbar. Sie verändert keine Vendor-Dateien und verwendet vorhandene
Farbvariablen. Als neue Komponente ist sie auch bei bereits gecachtem
allgemeinem Anwendungsstylesheet verfügbar.

## Tests und Betrieb

`PortalNavigationHttpTest` prüft reale HTTP-Zugriffe mit synthetischen Konten,
nicht nur die Existenz von Links: Gäste, ehemalige/aktive Mitglieder,
unabhängige Kursverwaltung/Ausbilder/Gerätewart/Thekenleitung/Pressewart,
Vorstand, Admin/Superadmin ohne Vereinskontext, Mehrfachrollen und zugeordnete
Mannschaftsführung. Alle angebotenen Einstiegslinks werden tatsächlich geöffnet.
Zusätzliche Tests sichern fremde Redaktion, verweigerte Direktaufrufe,
Zielpfade, CSRF-Logout, Sprachwechsel und Frontend-/Backendgrenzen ab.

Öffentliche Vereinsinformationen werden gegen fremde Daten, interne Notizen,
unsichere Websiteadressen und HTML-Inhalte getestet. Bestehende Layout- und
Fachtests bleiben Teil der Regression.

```sh
ddev exec "php8.5 vendor/bin/phpunit --testsuite unit,isolated --no-progress --display-errors --display-skipped"
ddev exec php8.5 bin/console lint:twig templates --env=test
ddev exec php8.5 bin/console lint:container --env=test
php tests/translation_health.php lint:translations
```

Keine Abhängigkeitsänderung, Datenmigration oder Veränderung produktiver
Geschäftsdaten erforderlich. Ergebnisse und Grenzen stehen im
[Prüfbericht](testing/test-report.md). Noch fehlende Geschäftsprozesse stehen
in der [aktuellen Matrix](analysis/feature-matrix.md) und im
[Umsetzungsplan](analysis/implementation-roadmap.md).
