# Frontend und EasyAdmin: Navigationsvertrag

Stand: 28.10.2026, nach fachlicher Präzisierung. Dies ist eine Teilphase der
Master Specification, keine Vollständigkeitsfreigabe sämtlicher Fachmodule.
Der zunächst vorgesehene gemeinsame Portalwechsel wurde ausdrücklich verworfen.

## Anmeldung und Darstellungsgrenzen

Eine gemeinsame Anmeldung, aber zwei getrennte Arbeitsbereiche:

- Mitglieder landen unter `/my`, Gäste unter `/training`.
- Reine Verwaltungsrollen landen in ihrem EasyAdmin-Bereich: Vorstand/Admin
  im Dashboard, Ausbildungsleitung in der Ausbildungsübersicht, Kursverwaltung
  in `/admin/training`, Clubheimverwaltung in `/admin/bar-duty`, Pressewart in
  der Redaktion und Mannschaftsbetreuung in der Sportverwaltung.
- Explizit vergebene Ausbilder-/Gerätewartfunktionen behalten bei Mehrfachrollen
  den Frontendeinstieg. Geerbte Funktionen machen reine Admins nicht zu
  Frontendmitarbeitern.
- Gespeicherte geschützte Zielpfade bleiben vorrangig. Logout bleibt
  CSRF-geschützter POST.
- Das Frontend verlinkt nicht ins Backend; das Backend nicht ins Frontend.
  Direkte URLs bleiben unabhängig davon durch Rollen und Objektprüfungen gesichert.
- `/portal` ist nur eine zusätzliche persönliche Frontendübersicht, kein
  Bereichswechsler.

Alle Frontendseiten erben `base.html.twig` und damit das konfigurierte
`SiteTheme`. Auch Ausbilder, Gerätewart und Thekendienst verwenden diesen
Rahmen; nur das Menü ändert sich. Die vorhandene Mitgliederseite bleibt
gestalterisch erhalten. EasyAdmin verwendet unabhängig von der Kontorolle
seinen eigenen Rahmen.

## Frontendnavigation

| Gruppe | Funktionen |
|---|---|
| Öffentlich | Startseite, Verein, Kontakt, Aktuelles, Veranstaltungen, Kursangebot; Mitgliedsantrag nur für Gäste und andere Konten ohne aktive Mitgliedschaft |
| Mitglied | Dashboard, Meine Daten, Meine Mitgliedschaft, Meine Ausrüstung, Meine Buchungen, Meine Kurse; weitere vorhandene Ausbildungs-/Reservierungsfunktionen |
| Ausbilder | Meine zugeordneten Kurse, Ausbilder Dashboard, Meine Aufgaben |
| Gerätewart | Übersicht, TÜV Prüfung, Equipmentverleih, Inventar/Wartung, Kasse, Kompressorlogbuch, Füllgebühren |
| Thekendienst | Dienstkalender und mein Konto, Thekenkasse, Gemeinsamer Kalender |

`PortalNavigation` bündelt öffentliche und rollenabhängige Frontendlinks.
Die Buchungsübersicht zeigt eigene TÜV-Buchungen und Einstiege zu vorhandenen
Reservierungen/Kursanmeldungen sowie zu den eigenen Veranstaltungsanmeldungen.
Redaktionelle Ankündigungen sind davon unabhängige Inhalte und erzeugen keine
Buchungen; siehe [Veranstaltungsbuchungen](phase9-events.md).

## Clubheimverwaltung und eigene Thekenkasse

Dieselbe Fachimplementierung erhält unter `/admin/bar-duty` expliziten
EasyAdmin-Kontext. Backendnavigation, Formulare, Exporte und Redirects behalten
diesen Pfad. Planung, Serien, Vorlagen, Pflichtstunden, Anwesenheit und
Gesamtauswertungen sind im Frontend auch für Leitungsrollen gesperrt.

Aktive Mitglieder dürfen im Frontend ausschließlich Kassen ihrer eigenen
aktiven Einteilung bedienen. Zurückgezogene/ersetzte Einteilungen, bestätigte
Abwesenheit, inaktive Mitgliedschaft und fremde Vereine verleihen keinen
Zugriff. Die Berechtigung wird beim Aufruf und innerhalb der Transaktion
erneut geprüft. Die Leitungsrolle allein erlaubt keine Frontendkasse ohne
eigene Einteilung; die Backendverwaltung bleibt möglich.

Centgenaue Buchführung, Verbot negativer Bestände, Gegenbuchungen,
Jahresfreischaltung, Abschlussnachweis und unveränderliche abgeschlossene
Kassen bleiben bestehen. Die Thekenkasse bleibt von der Gerätewartkasse getrennt.

## Kalender und öffentliche Informationen

`/calendar` bleibt Frontend; `/admin/calendar` bleibt Backend. Eine gemeinsame
Projektion vermeidet doppelte Termine. Frontendkalendereinträge verlinken
nicht in Verwaltungsseiten.

`/club` und `/contact` veröffentlichen nur Organisationsdaten des aktiven,
über `PUBLIC_SITE_CLUB_ID` ausgewählten Vereins. Keine internen Notizen oder
Personenlisten; nur HTTP(S)-Websiteadressen werden verlinkt. Ohne konfigurierten
aktiven Verein fehlen Links und direkte Aufrufe liefern 404.

## Validierung und verbleibender Umfang

Die HTTP-Regression prüft gemeinsame Themes, bereichstreue Links/Formulare,
Loginziele, erreichbare Funktionsseiten und eigene/fremde Thekenkassen.
Bestehende Fachtests sichern Buchhaltung, Dienste und Vereinsgrenzen ab.

```sh
ddev exec "php8.5 vendor/bin/phpunit --testsuite unit,isolated --no-progress --display-errors --display-skipped"
ddev exec php8.5 bin/console lint:twig templates --env=test
ddev exec php8.5 bin/console lint:container --env=test
php tests/translation_health.php lint:translations
```

Keine Abhängigkeitsänderung, Datenmigration oder Veränderung produktiver
Geschäftsdaten erforderlich. Ergebnisse und Grenzen stehen im
[Prüfbericht](testing/test-report.md). Clubreisen, automatische Veranstaltungs-
Erinnerungen und weitere noch fehlende Geschäftsprozesse bleiben im
[Umsetzungsplan](analysis/implementation-roadmap.md) ausdrücklich offen.
