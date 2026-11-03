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

Unter `/admin/settings` führen getrennte Seiten zu Website-Anzeige
(`/admin/settings/website`), Mitgliedschaftsregeln
(`/admin/settings/membership`), Beitragsklassen
(`/admin/settings/contributions`) und Thekendienst-Jahresregel
(`/admin/settings/bar-services`). Die frühere zentrale Seite
`/admin/configuration` leitet bei GET zur Übersicht weiter; alte POSTs werden
nicht übernommen. Die FormTypes für Mitgliedschaftsregeln und Beitragsklassen
werden auch auf den bisherigen Mitgliedschaftsseiten verwendet. Schreibende
Formulare sind CSRF-geschützt; ungültige Eingaben werden direkt an den
Formularfeldern angezeigt. Für Superadmins gilt der ausgewählte Verein auch
für die Website-Anzeige und die Jahresregel; die Veröffentlichung der
öffentlichen Website bleibt an `PUBLIC_SITE_CLUB_ID` gebunden.
Die TÜV-Rechnungseinstellungen sind ebenfalls über `/admin/settings`
erreichbar und bearbeiten ausschließlich den ausgewählten Verein. Die
früheren eigenständigen TÜV-Rechnungsseiten leiten GET-Anfragen nach
EasyAdmin weiter; alte POST-Anfragen werden nicht übernommen.

Vereinsadmins können unter `/admin/settings/sports` für ihren eigenen Verein
Sportarten (Sparten) anlegen und deren
Bezeichnung, Kurzbezeichnung, Beschreibung und Sortierung bearbeiten.
Superadmins wählen den Verein im Benutzermenü; die Auswahl gilt derzeit
für die Sportarten-Einstellungen, Mitglieder-, Mitgliedsqualifikations-,
Sportzuordnungs-, Mannschafts-, Mannschaftsmitglieds-, Kurs-, Kurstermin-,
Teilnehmer-, Ausbildungsnachweis- und Inventargegenstands-CRUDs, die Kategorien, Hersteller,
Unterarten, Größen, Modelle und Kategorie-Merkmale der Ausrüstung, die
EasyAdmin-Ansichten für Einzel- und Sammelreservierungen, die separaten
Flaschen-, Atemregler- und Atemreglermodell-CRUDs samt Flaschenstatus,
Wartung und Prüfung sowie die technischen Atemreglerdaten zu Inventargegenständen,
die Mitgliedschaftsverwaltung, die Veranstaltungs- und Dateiverwaltung,
die oben genannten Einstellungen, das Ausbildungsportal, die Kurs-Spartenprüfung
sowie die Kennzahlen und Listen der Verwaltungsübersicht. Vereinslose
Mitglieder und Kurse können Superadmins über separate Listen in den
Einstellungen prüfen und dem ausgewählten Verein zuordnen. Qualifikationen
vereinsloser Mitglieder erscheinen erst nach der Vereinszuordnung des
Mitglieds in der Qualifikationsverwaltung; vorhandene Daten bleiben erhalten.
Der Qualifikationskatalog selbst ist weiterhin übergreifend. Im
Wettbewerbsportal, Kurskalender und gemeinsamen Kalender gilt für Superadmins ebenfalls der
ausgewählte Verein; bestehende vereinsübergreifende Wettbewerbsteilnahmen
anderer berechtigter Rollen bleiben unverändert. Ausbildungsfortschritt und
Kursmatrix zeigen nur Kurse des ausgewählten Vereins. Im Wettbewerbsportal
werden widersprüchliche Mannschaftsmitgliedschaften ausgeblendet und deren
Bearbeitung abgelehnt. Sportzuordnungen mit
fehlenden oder widersprüchlichen Vereinszuordnungen werden nicht automatisch
verändert und erscheinen nicht in der regulären Vereinsliste.
Im Gerätewartportal gilt für Superadmins der ausgewählte, für andere
Gerätewarte ihr eigener Mitgliedsverein. Alte Direktausleih-, Kompressor-,
Kassen- und TÜV-Planungsformulare sind nach einem Vereinswechsel erneut
zu öffnen. TÜV-Termine bleiben global; inkonsistente Anmeldungen werden
in der Gerätewartansicht ausgeblendet und deren Bearbeitung abgelehnt.
Kompressorbediener können auch ohne Gerätewartrolle vereinsbezogen auf
Füllabende, Fülllogbuch und deren CSV-Exporte zugreifen und Füllvorgänge
erfassen. Die übrigen Gerätewart-, Füllgebühren- und Kassenseiten bleiben
ihnen ohne zusätzliche Rechte verwehrt. Gerätewarte ohne Kompressorrolle
können die Kompressoransichten lesen, aber keine Einträge verändern.
Die Flaschenprüfübersicht in EasyAdmin zählt und zeigt nur Flaschen des
ausgewählten Vereins; vereinslose Flaschen werden dort nicht berücksichtigt.
Kurstermine mit einem vereinsfremden Ausbilder werden in den geprüften
Kalender-, Kursmatrix- und Ausbildungsnachweisansichten ausgeblendet und
bei den zugehörigen Bearbeitungsaktionen abgelehnt. Die Daten werden dabei
nicht automatisch geändert.
Bei Backendanfragen berücksichtigt die Fortschrittsberechnung außerdem
keine Nachweise mit vereinsfremdem Ausbilder oder Prüfer. Dies gilt auch
für die damit verbundenen Abschlussprüfungen; die Berechnung im öffentlichen
Ausbildungsportal bleibt unverändert.
Die zuletzt in der Verwaltung oder im Portal gewählte Sprache bleibt auch
beim Wechsel auf nicht lokalisierte Anwendungsseiten erhalten. Das
Mitglieder-Dashboard und Mitgliederprofil einschließlich Qualifikations- und
Dokumentenzuständen sind in den vier konfigurierten Sprachen übersetzt.
Auch die Mitglieder-Ausrüstungsübersicht mit Flaschenbearbeitung,
TÜV-Einzel- und Mehrfachanmeldung und TÜV-Anmeldedetails, die Reservierungsübersicht sowie Ausbildungsübersicht
und Teilnahmeansicht sind übersetzbar. Automatisierte Vier-Sprachen-Tests
prüfen ausgewählte Leer-, Fehler-, Anmelde-, Wartelisten- und
Stornierungszustände; sie ersetzen keine vollständige Browserprüfung aller
Rollen und Datenzustände. Weitere Rechnungs- und
Ausbildungsansichten sind damit nicht automatisch vollständig übersetzt.
Gerätewart-Übersicht, Direktausleihe,
Flaschen- und Inventarlisten, Flaschendetails, Füllgebühren sowie die Masken
für Flaschenprüfung, Wartung und die bearbeiteten TÜV-Ansichten sind
übersetzt. Auch Kassenjournal, Tagesauswertung samt CSV, Korrekturen,
Auszahlungen, Tagesabschluss, Kompressor-Einzelfülllogbuch und Füllabende
mit ihren CSV-Exporten sowie Inventarakte und Verleihoberflächen sind
übersetzbar. Bereits gespeicherte Freitexte und ältere erzeugte
Historienbeschreibungen werden nicht nachträglich übersetzt.
Der Thekendienstkalender, die Thekenkassenübersicht, die Dateiverwaltung und die EasyAdmin-Ansicht
für die TÜV-Abwicklung stellen ihre bearbeiteten Oberflächentexte in
Deutsch, Englisch, Französisch und Spanisch bereit. Andere Thekendienst-
und TÜV-Ansichten sowie gespeicherte Benachrichtigungstexte sind damit
nicht automatisch vollständig übersetzt.
Die Kopfzeile
weist weiterhin darauf hin, dass die Filterung nicht backendweit gilt:
weitere Kalender- und Sportaktionen sind noch nicht durchgehend auf diesen
Kontext umgestellt. Widersprüchliche Ausrüstungs- und
Mannschaftszuordnungen werden nicht automatisch korrigiert; betroffene
Detailansichten und Aktionen werden abgelehnt. TÜV-Benachrichtigungsvorlagen
und die Übersicht der Vertretungsnachweise gelten weiterhin systemweit
und sind entsprechend gekennzeichnet.
Die Sportartenverwaltung nutzt die EasyAdmin-Liste und -Formulare.
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
