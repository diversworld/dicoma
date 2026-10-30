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

## Konfigurationsformulare

Die zentrale Seite `/admin/configuration` verwendet Symfony-FormTypes für
Website-Anzeige, Mitgliedschaftsregeln, Beitragsklassen und die
Thekendienst-Jahresregel. Vereins- und Jahresauswahl sind GET-Formulare;
sie behalten die bestehenden Parameter `club` und `year`. Die FormTypes für
Mitgliedschaftsregeln und Beitragsklassen werden auch auf den bisherigen
Mitgliedschaftsseiten verwendet. Schreibende Formulare sind CSRF-geschützt;
ungültige Eingaben werden direkt an den Formularfeldern angezeigt.
Die Thekendienst-Jahresregel ist zusätzlich als eigenständige Seite
`/admin/settings/bar-services` erreichbar. Sie verwendet momentan weiterhin
den Verein der Thekenleitung, nicht die Sportarten-Vereinsauswahl. Die zentrale
Seite und die übrige Aufteilung der Einstellungen sind noch nicht entfernt.

Vereinsadmins können unter `/admin/settings/sports` für ihren eigenen Verein
Sportarten (Sparten) anlegen und deren
Bezeichnung, Kurzbezeichnung, Beschreibung und Sortierung bearbeiten.
Superadmins wählen den Verein im Benutzermenü; die Auswahl gilt derzeit
für die Sportarten-Einstellungen, die Kurs- und Teilnehmeransichten im
Ausbildungsportal, die Kurs-Spartenprüfung sowie die Kennzahlen
und Listen der Verwaltungsübersicht. Die Kopfzeile zeigt den Verein und weist darauf hin,
dass die Filterung noch nicht backendweit gilt. Direkte Sportarten- und
Funktions-URLs müssen zum ausgewählten Verein passen. Die übrigen
Verwaltungsbereiche und die bisherige Konfigurationsseite verwenden noch
nicht durchgehend diesen Sitzungskontext. Die Sportartenverwaltung nutzt
die EasyAdmin-Liste und -Formulare.
Die Verwaltung verwendet
die bestehende Sportart und erhält damit vorhandene Mitglieder- und
Mannschaftszuordnungen. Den bestehenden Aktiv-Status können ausschließlich
Superadmins ändern; er wird noch nicht fachbereichsübergreifend durchgesetzt.
Vereinsadmins und Superadmins
können für Mitglieder ihres Vereins zeitlich begrenzte Spartenfunktionen
zuordnen. Eine aktuell gültige Spartenleitung kann Mannschaften ihrer Sparte
einschließlich Kader, Training und zugehöriger Turnieranmeldungen verwalten.
Die Person muss aktives Mitglied desselben Vereins sein. Trainerrechte werden
weiterhin pro Mannschaft vergeben; die Funktionen Trainer, Gerätewart und
Jugendwart begründen für sich noch keine zusätzlichen Rechte. Die Zuordnung
ändert keine globalen Symfony-Rollen. Bei mehreren Zuordnungen gilt die
Berechtigung, solange mindestens eine Zuordnung aktuell ist.

Kursverwaltung, Ausbildungsleitung und Vereinsadmins können in der
Ausbildungsverwaltung Kurse als ungeprüft, vereinsweit oder einer bzw.
mehreren Sparten zugeordnet kennzeichnen. Die Prüfliste und die Bearbeitung
verwenden den ausgewählten Verein;
Superadmins können auch Kurse ohne Verein zur Nachbearbeitung anzeigen.
Neue und bestehende Kurse sind zunächst ungeprüft. Inaktive Sparten können
nicht neu zugeordnet werden, vorhandene Zuordnungen bleiben erhalten.
Ein Vereinswechsel von Kurs oder zugeordneter Sparte wird bei widersprechenden
Zuordnungen abgelehnt. Die Zuordnung verändert weder Kursberechtigungen noch
die öffentliche Sichtbarkeit.

Die additiven Migrationen `Version20261030100000` und `Version20261030110000`
sind nach Backup und Schemafreigabe erforderlich. Funktionsfreigaben,
öffentliche Sichtbarkeit, spartenübergreifende Jugendgruppen und Zuordnungen
zu weiteren Fachbereichen sind noch nicht implementiert.

Das Benutzermenü zeigt den vorhandenen Personennamen bzw. Kontonamen und ein
Standardbild. Separate Backendseiten für Profil und Passwort existieren noch
nicht; das Menü weist darauf hin, statt auf nicht vorhandene Routen zu führen.

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

Für die oben beschriebenen Spartenfunktionen und Kurszuordnungen sind die
genannten Migrationen erforderlich; sie wurden nicht auf produktive Daten
angewendet. Ergebnisse und Grenzen stehen im
[Prüfbericht](testing/test-report.md). Clubreisen, automatische Veranstaltungs-
Erinnerungen und weitere noch fehlende Geschäftsprozesse bleiben im
[Umsetzungsplan](analysis/implementation-roadmap.md) ausdrücklich offen.
