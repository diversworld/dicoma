# Betrieb und Wiederherstellung – Phase 2 bis 6 und Phase 8

## Airspace-Theme und öffentliche Redaktion

Die additive Migration `Version20261025190000` ergänzt
`website_publication`. Bestehende Mitglieder-, Kurs-, Turnier- und
Thekendienstdaten werden nicht verändert oder automatisch veröffentlicht.
Ein destruktiver Rückbau ist zum Schutz der redaktionellen Nachweise
gesperrt. **Es wurde keine produktive Migration durchgeführt.** Vor
Freigabe Snapshot, Zielkontrolle, Migrations-Dry-run, ausdrückliche
Freigabe und Schema-/Cacheprüfung wie unten beschrieben durchführen.
Neue Redaktionsansichten erst nach dem Schema bereitstellen.

In der lokalen Umgebungskonfiguration ausdrücklich setzen:

```dotenv
SITE_THEME=airspace
PUBLIC_SITE_CLUB_ID=123
```

`123` ist nur ein Beispiel: die vorhandene ID des gewünschten Website-
Vereins verwenden. Es wird absichtlich kein Verein automatisch ausgewählt.
Standard `0` deaktiviert die neue Redaktion und lässt die neuen
öffentlichen Inhaltslisten leer. Eine nicht existierende konfigurierte
Vereins-ID ist ein ausdrücklicher Konfigurationsfehler. Das bisherige
Kursportal bleibt ohne Website-Konfiguration unverändert; nach
Konfiguration wird das öffentliche Kursangebot auf den Website-Verein
begrenzt. Nach Änderungen den passenden Umgebungscache erneuern.

Vorstand erhält Redaktionsrechte durch die Rollenhierarchie. Alternativ
`ROLE_PRESS_OFFICER` über die bestehende Benutzer-/Rollenverwaltung
vergeben; die betroffene Person muss dem Website-Verein zugeordnet sein.
Artikel und Veranstaltungen werden im Backend unter
`/admin/{_locale}/editorial` verwaltet. Auch Pressewarte ohne Adminrolle
erhalten ausschließlich Zugriff auf diese Redaktionsseiten, nicht auf das
übrige EasyAdmin. Die öffentliche Navigation enthält keinen Redaktionslink.
Unter **Konfiguration → Website-Anzeige**
können Pressewart und Vorstand für den Website-Verein die Anzahl der Artikel
und Veranstaltungen pro Seite getrennt auf 1 bis 100 setzen. Ohne gespeicherte
Einstellung gelten jeweils sechs Karten. Die Werte gelten auf Startseite und
Übersichtsseiten; auf der Startseite wird je Rubrik unabhängig geblättert.
Die additive Migration `Version20261029120000` muss vor Bereitstellung dieser
Funktion nach Backup und Freigabe angewendet werden.
Beiträge zunächst als Entwurf speichern. Nur freigegebene Beiträge werden ab dem gewählten
Zeitpunkt öffentlich. In der Redaktionsübersicht kann die Freigabe mit dem
Schalter gesetzt oder zurückgenommen werden; ohne JavaScript über die
Schaltfläche „Freigabe speichern“. Beim Freigeben gelten die gleichen
Inhaltsprüfungen wie im Formular; Änderungen werden protokolliert.
Alternativ lässt sich die Freigabe beim Bearbeiten ändern. Kein Cronjob für zeitgesteuerte Sichtbarkeit
erforderlich. Veranstaltungstexte und Termine sind redaktionelle Angaben;
Quelländerungen übernehmen keine internen Daten automatisch. Abgesagte
oder auf Entwurf gesetzte Quellen werden nicht mehr öffentlich angezeigt.

### Dateiverwaltung und Beitragsbilder

Die additive Migration `Version20261029130000` ergänzt eine vereinsbezogene
Dateiverwaltung und optionale Bildzuordnungen für Beiträge und buchbare
Veranstaltungen. Vor Bereitstellung Backup und Schemafreigabe durchführen.
Die Anwendung benötigt die PHP-Erweiterungen `fileinfo` und `zip`.
Server und PHP müssen Uploads von mindestens 10 MB zulassen; `post_max_size`
muss dabei größer als `upload_max_filesize` sein. Office-Dateien können
schädliche Inhalte enthalten und sollten vor dem Öffnen nach den
Sicherheitsvorgaben des Vereins geprüft werden.

Vereinsadmins und die zur Website-Redaktion berechtigten Pressewarte laden
unter **Dateiverwaltung** JPG, PNG, WebP, PDF oder Office-Dateien
(`docx`, `xlsx`, `pptx`) bis 10 MB hoch. Dateiname und Alternativtext können
bearbeitet werden; Bilddateien sind in den Formularen auswählbar. Das Bild
einer buchbaren Veranstaltung lässt sich auch nach Anmeldungen separat ändern,
ohne Preis oder Termin zu verändern. Genutzte Dateien können erst nach dem
Entfernen aller Zuordnungen gelöscht werden. Beim Hochladen sind Urheberrechte
und gegebenenfalls personenbezogene Bildmetadaten zu prüfen.

Alle Dateien liegen außerhalb von `public/` unter `var/private/website_media`.
Die Dateiverwaltung ist vereinsgebunden und zeigt Redaktions- und
Verwaltungsberechtigten dieses Vereins auch hochgeladene Dokumente; andere
Rollen erhalten keinen Zugriff. Dokumente werden als Download bereitgestellt.
Nur Bilder verknüpfter, für den jeweiligen Besucher sichtbarer Inhalte werden
über geschützte Inhaltsrouten ausgeliefert; Entwürfe und mitgliedervorbehaltene
Veranstaltungen machen ihre Bilder nicht öffentlich. Die Dateien und die
Datenbank müssen zusammen gesichert und wiederhergestellt werden. Das Verzeichnis
muss für den PHP-Prozess beschreibbar sein und darf nicht vom Webserver direkt
ausgeliefert werden. Teilnehmerbezogene Veranstaltungsdokumente liegen
ebenfalls dort, erscheinen aber nicht in der allgemeinen Dateiverwaltung;
ihre Zuordnungen und Zugriffsregeln sind in der Veranstaltungsverwaltung
getrennt erfasst. Dafür ist Migration `Version20261029140000` erforderlich.

Die zentrale Backend-Konfiguration enthält die Website-Seitengrößen,
Mitgliedschaftsregeln und Beitragsklassen sowie die Jahresregeln des
Thekendienstes. Die verfügbaren Bereiche richten sich nach Rolle und
Vereinszuordnung; bei Superadmins gilt die Vereinsauswahl für
Mitgliedschaft und Beiträge, Website und Thekendienst bleiben an den
eigenen berechtigten Verein gebunden. Secrets bleiben in der Serverumgebung.

Neue Themes unter `site.themes` registrieren und per `SITE_THEME`
auswählen; Struktur und Blockvertrag stehen in der
[Systemübersicht](../architecture/system-overview.md#austauschbares-airspace-theme-und-öffentliche-redaktion).
Airspace verwendet vorhandene lokale Themefisher-Assets, die MIT-Lizenz
liegt unter `public/themes/airspace/LICENSE`. Bootstrap-Primärfarben
werden zentral auf `#003366` abgebildet, nicht in Vendor-Dateien geändert.

Validierung: vollständige Unit-/isolierte Regression mit **256 Tests,
3827 Assertions** erfolgreich. Die abschließende gezielte Prüfung nach
Kurskatalog-/Portalergänzungen umfasst **34 Tests, 1015 Assertions**.
Zwei MariaDB-Tests für Neuinstallation/Mapping und nichtdestruktives
Redaktionsupgrade bestehen mit **14 Assertions**, ausschließlich auf
zufällig benannten Testdatenbanken. Alle **227 Twig-Templates**,
Container-Lint, die drei geänderten YAML-Konfigurationen und PHP-Syntax
sind geprüft. Eine synthetische Browser-Vorschau bestätigt die Airspace-
Darstellung, mobile Navigation bei 390 Pixeln und die berechnete
Primärbuttonfarbe `rgb(0, 51, 102)`. Es wurden keine echten Artikel,
öffentlichen Veranstaltungen oder E-Mails angelegt beziehungsweise versandt.

## Phase 8: Migration und Benachrichtigungen

`Version20261025170000` ergänzt ausschließlich neun neue Tabellen für
Thekendienste und deren eigenständige Kasse. Mitglieder, Gerätewartkasse
und unbekannte Bestandstabellen werden nicht verändert. Es werden weder
Pflichtstunden noch historische Kassenbestände erfunden. Der Rückbau ist
zum Schutz von Anwesenheits-, Zustimmungs- und Finanznachweisen gesperrt.
**Keine produktive Migration oder Aktivierung wurde ausgeführt.**
Snapshot, Zielprüfung, Dry-run, Freigabe und Schema-/Cacheprüfung folgen
denselben unten beschriebenen Regeln wie für Phase 6.

Vor Nutzung Thekenleitung zuweisen und je Verein/Jahr die Pflichtminuten
festlegen; optionale Thekenkasse ausdrücklich aktivieren. Auch null Minuten
sind eine bewusste Regel. Individuelle Ausnahmen erfordern eine Begründung.
Bestehende Jahre werden nicht automatisch kopiert und Salden nicht übertragen.

Die Planung erfolgt im Monatskalender: Veranstaltung öffnen und selbst
anmelden; Namen und freie Plätze sind bereits in der Kalenderzelle sichtbar.
Die Thekenleitung trägt über einen Tag Einzelveranstaltungen oder
Clubabend-Serien ein. Serien benötigen Enddatum und Wochentage, wahlweise
wöchentlich oder alle zwei Wochen ab der Woche des Startdatums. Die
Anlage ist atomar; identische bestehende Termine führen zur Ablehnung
statt zu Duplikaten oder einer unvollständigen Serie. Absagen einzelner
Serientermine betreffen nicht die übrigen Veranstaltungen.

Interne Erinnerungen können beispielsweise stündlich erzeugt werden:

```bash
APP_ENV=prod php bin/console app:bar-duty:remind --hours=48
```

Der Befehl versendet keine E-Mails. Wiederholung erzeugt je Einteilung
höchstens eine Erinnerung; ersetzte und abgesagte Dienste erhalten keine
neue Erinnerung. Bereits gespeicherte Ereignisse bleiben historische
Nachrichten; die aktuelle Portaleinteilung ist verbindlich.

E-Mail wird nur mit gültiger `BAR_DUTY_MAIL_FROM`, eingerichteter
`MAILER_DSN` und ausdrücklichem `--send` aktiviert:

```bash
APP_ENV=prod php bin/console app:bar-duty:send --send --limit=100
APP_ENV=prod php bin/console app:bar-duty:send --send --retry --limit=100
```

Ohne `--send` erfolgt keinerlei Versand. `--retry` verarbeitet ausschließlich
fehlgeschlagene Nachrichten, nicht bereits versandte. Fehlerhafte Empfänger
und Transportfehler werden als `failed` mit Fehlergrund gespeichert und
führen zu einem Fehler-Exitcode. Eine lokale Prozesssperre und ein unter
Datenbanksperre gespeicherter Anspruch (`sending`) verhindern parallelen
Doppelversand. Die SMTP-Übertragung liegt bewusst außerhalb einer langen
Datenbanktransaktion.

Bei Prozessabbruch oder Datenbankfehler nach Versand kann der Zustand
`sending` verbleiben. Dies wird beim nächsten Aufruf ausdrücklich gemeldet
(Fehler-Exitcode), **nicht automatisch erneut versendet**. Zuständige
Administration muss die betroffenen IDs mit Transport-/Providerprotokollen
abgleichen und den Zustand erst nach geklärtem Zustellergebnis kontrolliert
auf `sent` beziehungsweise `failed` berichtigen; bei Unsicherheit nicht
zurücksetzen. SMTP und Datenbank bieten keine gemeinsame Exactly-once-
Transaktion. Der private Portalposteingang bleibt unabhängig davon nutzbar.
Es wurde kein tatsächlicher E-Mail-Versand ausgelöst.

Die Phase-8-HTTP-Tests verwenden ausschließlich synthetische Daten in
SQLite im Arbeitsspeicher und Mailer-Mocks. Migrationsprüfungen verwenden
zufällig benannte, danach gelöschte MariaDB-Testdatenbanken.

Verifiziert mit PHP 8.4: **235 Unit-/isolierte Tests mit 3484 Assertions**
erfolgreich, davon **62 Phase-8-Tests mit 517 Assertions**. Zusätzlich
**zehn MariaDB-Migrations-/Isolationstests mit 90 Assertions** erfolgreich:
Neuinstallation, Bestandserhalt und konkurrierende Verleih-/Dienstbelegungen
bei älterem `REPEATABLE READ`-Snapshot, mit und ohne MariaDB-Snapshotmodus.
Die neuen Kalenderabläufe prüfen echte Links/Formulare, exakte Serientage,
inklusive Enddatum, atomare Konfliktablehnung und sichtbare Einteilungen.
Der Monatskalender wurde außerdem mit synthetischer HTML-Vorschau im
Browser bei Desktop- und 390-Pixel-Mobilbreite geprüft; keine Produktivdaten.
Container-Lint, 17 betroffene Twig-Templates, Security-YAML und PHP-Syntax
sind erfolgreich. Für die vollständige Regression:

```bash
docker exec ddev-dicoma-web php vendor/bin/phpunit --testsuite unit,isolated
```

## Phase 6: Migration und Aktivierungsgrenze

`Version20261025140000` erweitert bestehende Verleihbuchungen um
Genehmigung und Abschluss sowie bestehende Flaschen-Wartungsaufträge
um allgemeine Ausrüstung, Atemregler, Auftragsart und Verantwortliche.
Neu sind `equipment_asset_record`, `equipment_document` und
`equipment_fill_charge`. Bestehende Buchungen, Flaschen, Prüfungen,
Wartungsberichte und Belege bleiben erhalten. Unbekannte
Bestandstabellen werden nicht entfernt; ein destruktives `down`
ist zum Schutz der Nachweise gesperrt.

**Diese Änderung führt keine produktive Migration oder Freigabe aus.**
Vor Aktivierung gelten dieselben Snapshot-, Wiederherstellungs- und
Freigabeschritte wie unten für Phase 5. Erst nach ausdrücklicher
Zielprüfung und Freigabe im vorgesehenen Wartungsfenster:

```bash
APP_ENV=prod php bin/console doctrine:migrations:status
APP_ENV=prod php bin/console doctrine:migrations:migrate --dry-run --no-interaction
APP_ENV=prod php bin/console doctrine:migrations:migrate --no-interaction
APP_ENV=prod php bin/console doctrine:schema:validate
```

Die Befehle sind Betriebshinweise, keine Bestätigung einer Ausführung.
Danach Migrationsstand, Bestandsdaten, PHP-8.4-Prod-Cache und gezielt
den zuständigen PHP-FPM-Prozess prüfen. Neue Ansichten nicht vor
dem Schema aktivieren. Private Inventardokumente gehören in dieselbe
geschützte Sicherung wie die bestehenden privaten Nachweise.

In isolierten SQLite-HTTP-Tests werden der Verleihprozess einschließlich
Genehmigung, Gebühren, Zustandsprüfung, Reparatur und Abschluss,
Überschneidungen, Altreservierungen, gemeinsame Flaschenidentität,
Prüfintervalle, Dokumente, Vereinsgrenzen und CSRF geprüft.
Fülltests prüfen beide Eingabesurfaces, technische Sperren und
Prüffristen, Füllberechtigung, Gruppenabschluss sowie genaue
Gebührensummen in Journal, Tagesauswertung, CSV, Kassenbestand
und Kassenkorrektur. MariaDB-Tests erzeugen ausschließlich zufällig
benannte, danach wieder gelöschte Testdatenbanken und vergleichen
Neuinstallation mit Mapping sowie Bestandswerte vor/nach dem Upgrade.
Zusätzliche Tests mit zwei unabhängigen Datenbankverbindungen prüfen
moderne und alte Reservierungen bei bereits geöffnetem
`REPEATABLE READ`-Snapshot, jeweils mit und ohne MariaDBs
Snapshot-Isolationsmodus. Die Modi werden nur auf der jeweiligen
Testverbindung eingestellt, nicht global oder produktiv.

Verifiziert mit PHP 8.4: **173 Unit-/isolierte Tests mit 2955 Assertions**
sowie **sechs MariaDB-Migrations-/Isolationstests mit 52 Assertions**
erfolgreich. Container-Lint, alle 27 betroffenen Twig-Templates,
PHP-Syntax und `git diff --check` sind ebenfalls erfolgreich.

Für die Regression mit der vorhandenen DDEV-Weblaufzeit:

```bash
docker exec ddev-dicoma-web php vendor/bin/phpunit --testsuite unit,isolated
```

Der vorhandene Migrationsketten-Test benötigt separat
`MIGRATION_TEST_DATABASE_URL` zum **isolierten** MySQL-/MariaDB-Testserver
mit Berechtigung zum Erzeugen eigener Testdatenbanken. Niemals
Produktionszugangsdaten als Testziel verwenden. Fachliche
Kompatibilitäts- und Preisregeln stehen in der
[Phase-6-Systemübersicht](../architecture/system-overview.md#phase-6-inventar-gerätewart-und-verleih).

## Phase 5: Migration und Aktivierungsgrenze

`Version20261024150454` ergänzt sieben Tabellen für Trainingszeiten,
Turniere, Mannschaftsmeldungen, Spieler, Begegnungen, Gebührenbelege und
Dokumente sowie fünf Kaderfelder für Position und Spielberechtigung.
Vorhandene Personen, Mitgliedschaften und Mannschaftszuordnungen werden
nicht umgeschrieben. Bestehende Zuordnungen erhalten ausdrücklich keine
automatische Spielberechtigung.

Die Migration prüft doppelte Mannschaftsnamen je Sportart und doppelte
Kaderzuordnungen vor dem ersten DDL. Konflikte verlangen eine ausdrücklich
entschiedene fachliche Bereinigung; die Migration führt keine Datensätze
automatisch zusammen. Sie entfernt auch keine nicht gemappten
Bestandstabellen. Ein destruktives `down` ist zum Schutz von Ergebnissen,
Nachweisen und Zahlungsbelegen gesperrt.

**Der Code allein ist keine Produktivfreigabe.** Vor lokaler oder externer
Aktivierung sind erforderlich:

1. Ausdrückliche Freigabe des Datenbank-Upgrades.
2. Neuer konsistenter, geschützter Anwendungs-/Datenbanksnapshot mit
   Prüfsummen und tatsächlicher isolierter Wiederherstellungsprüfung.
3. Prüfung der Migrationskette auf dem getrennten MariaDB-Testserver
   und erfolgreicher Unit-/HTTP-Regression.
4. Kontrollierte Migration mit MySQL/MariaDB, danach Vergleich von
   Mapping, Migrationsstand und erhaltenen Bestandsdaten.
5. Erst anschließend Prod-Cache mit der Weblaufzeit PHP 8.4 erneuern
   und den zuvor identifizierten PHP-FPM-Master gezielt sanft neu laden.

Nicht nur den Menüstand vorzeitig aktivieren: Die neuen Ansichten und
Kaderabfragen benötigen die neuen Tabellen beziehungsweise Spalten.
Tests erzeugen ihre Mannschaften, Meldungen, Gebühren und Dokumente
ausschließlich in isolierten Testdatenbanken. Die private Dokumentenablage
ist in Backups einzuschließen.

## Phase 4: lokale Prod-Aktivierung

Am 24.10.2026 wurde der aktuelle Phase-4-Stand nach ausdrücklicher Freigabe
lokal aktiviert. Bei der direkten Prüfung von Dienst `db`/Datenbank `db`
war `Version20261024130000` bereits ausgeführt; Schema und Mapping waren
synchron. Es wurde kein erneutes DDL und keine manuelle Historienänderung
vorgenommen.

Ein geschützter aktueller Snapshot wurde erstellt und auf isolierten Zielen
tatsächlich wiederhergestellt. Alle Originalzeilen in 78 Anwendungstabellen
blieben erhalten. Das Backup des bereits migrierten Bestands liegt unter:

```text
/home/diversworld/.copilot/session-state/fdbd0085-c4f6-44cd-9a89-fff49c0f31f4/files/phase4-local-prod-20261024-1411/
```

Verzeichnisrechte 0700, Backupdateien 0600; enthält Secrets und
personenbezogene Daten, daher nicht veröffentlichen oder committen.
Prüfsummenprüfung: `sha256sum --check SHA256SUMS`.

Prod-Cache mit der Weblaufzeit PHP 8.4 erneuert, PHP-FPM gezielt sanft neu
geladen, anschließend Prod-Container, Schema, Migrationsstand und tatsächliche
HTTPS-Endpunkte geprüft. Die lokale Anwendung läuft mit `APP_ENV=prod`,
`APP_DEBUG=0`; Installationsschutz und vorhandene Daten sind unverändert.
Einzelheiten stehen in [Phase 4: lokale Prod-Aktivierung](../phase4-training.md#lokale-prod-aktivierung-am-24102026).

### Nachlauf: Routen- und Backendbereinigung

Am selben Tag wurde der geprüfte Korrekturstand lokal aktiviert:
108 Unit-/isolierte Tests mit 1602 Assertions, 18 dedizierte
Ausbildungs-HTTP-Tests mit 1198 Assertions sowie vier JavaScript-Tests
waren erfolgreich. Container, alle 22 YAML-Dateien und 202 Twig-Templates
wurden zusätzlich mit PHP 8.4 geprüft.

Der Prod-Cache wurde mit PHP 8.4 erneuert und der zuvor identifizierte
PHP-FPM-Master gezielt mit USR2 sanft neu geladen. Prod läuft weiterhin
ohne Debug; Schema und Migrationsstand sind aktuell. Es wurde keine
Migration ausgeführt. Der Installationsmarker blieb bestehen, der Bestand
bei vier Personen, drei Mitgliedern, einem Kurs und null Kursteilnahmen.

Elf echte HTTPS-Aufrufe lieferten die erwarteten Statuscodes:
Login, Registrierung und öffentlicher Kurskatalog 200; geschützte
Ausbildungs-, Vorlagen- und alte Verwaltungslinks anonym 302 zur Anmeldung;
Installation 404. Der Kurskatalog wurde zusätzlich im Browser geöffnet.
Keine produktiven Anmeldungen, Zahlungen oder externen Mails wurden erzeugt.

Die [Routenbereinigung](../phase4-training.md#routen--und-backendbereinigung)
führt Verwaltungsseiten ins Backend und ersetzt 54 doppelte Alt-Routen.
Alte Leseaufrufe erhalten 303, veraltete Formular-POSTs ausdrücklich 410
ohne Änderung. Dieser Status ist beabsichtigt und kein fehlgeschlagener
Speicherversuch auf dem aktuellen Formular. Der CSRF-geschützte,
nichtlöschende Mitglieder-Archivierungsverweis bleibt erhalten.

Bekannter unabhängiger Asset-Nebenbefund: Das vorhandene Ionicons-Bundle
fordert eine fehlende JS-Chunkdatei an (404). Die Drittanbieter-Assets
wurden bei dieser Routenbereinigung nicht verändert.

### Nachlauf: aufgabenorientiertes Menü und Dashboard

Nach Auswahl der aufgabenorientierten Überarbeitung wurde das EasyAdmin-Menü
in Tagesgeschäft und Stammdaten/Einstellungen gegliedert. Das Dashboard
enthält berechtigte Schnellzugriffe, offene Mitgliedsanträge, kommende
Kurstermine und Qualifikationshinweise. Rollen- und Vereinsgrenzen bleiben
unverändert; globale CRUD-Rechte wurden nicht erweitert.
Die [Navigations- und Dashboardregeln](../architecture/system-overview.md#verwaltungsnavigation-und-dashboard)
beschreiben die Gruppen und Kennzahlendefinitionen.

Prüfung: 111 Unit-/isolierte Tests mit 1828 Assertions, alle 202
Twig-Templates und Container-Lint erfolgreich. Der Menüziel-Smoke-Test
öffnet sämtliche sichtbaren Menüziele und Schnellzugriffe als Vereinsadmin
beziehungsweise Superadmin. Die isolierte Browserprüfung bestätigt
auf-/zuklappbare Untermenüs sowie keinen horizontalen Seitenüberlauf bei
390, 768 und 1440 Pixeln. Der kurzlebige Vorschau-Server und seine
synthetischen Fixtures wurden anschließend entfernt.

Der lokale Prod-Cache wurde mit PHP 8.4 erneuert, der identifizierte
PHP-FPM-Master gezielt sanft neu geladen. Login und Kurskatalog liefern
200, geschützte deutsche/englische Verwaltungs- und Ausbildungszugänge
anonym 302 zur Anmeldung, Installation 404. Der Browser bestätigt die
Anmeldeumleitung. Der Datenbestand blieb unverändert; es wurde keine
Migration ausgeführt und keine produktive Anmeldung, Zahlung oder
Nachricht erzeugt.

### Nachlauf: zentrale Styles und Farbvariablen

Statische Styleblöcke und Inline-Deklarationen wurden aus den Twig-Templates
in eigene zentrale CSS-Dateien ausgelagert. Die sechs verbleibenden
Styleattribute setzen ausschließlich datensatzabhängige Variablen für
Fortschritt und Kalendergeometrie. `public/css/theme-variables.css` definiert
die gemeinsame Palette; auch die acht bestehenden eigenen Stylesheets
verwenden diese Werte. Drittanbieterdateien wurden nicht geändert.
Die [Style- und Farbanpassungsregeln](../architecture/system-overview.md#zentrale-styles-und-farbanpassung)
beschreiben Dateien, Akzentfarben und erforderliche Ausnahmen.

Verifiziert: 113 Unit-/isolierte Tests mit 2511 Assertions, vier
JavaScript-Tests, alle 202 Twig-Templates und Container-Lint erfolgreich.
Alle 25 eigenen CSS-Dateien wurden im Browser geladen; Themeänderung,
Hilfsklassen, Fortschritts- und Kalendergeometrie sind geprüft. Die reale
Loginseite übernimmt eine temporäre Änderung der Primärfarbvariable.
Die Teständerung wurde anschließend vollständig zurückgenommen. Bei
390, 768 und 1440 Pixeln entsteht kein horizontaler Seitenüberlauf.

Der Prod-Cache wurde mit PHP 8.4 erneuert und der zuvor identifizierte
PHP-FPM-Master gezielt sanft neu geladen. Login liefert HTTPS 200.
Der Bestand blieb bei vier Personen, drei Mitgliedern, einem Kurs und
null Kursteilnahmen. Es wurde keine Migration ausgeführt.
Der bekannte, unabhängige Ionicons-Chunk-404 bleibt unverändert.

## Phase 3: lokale Aktivierung und aktueller Snapshot

Die lokale Phase-3-Aktivierung wurde nach vollständigen erfolgreichen Prüfungen ausdrücklich freigegeben. Bei der anschließenden direkten Zielprüfung war `Version20261024102048` auf Dienst `db`/Datenbank `db` bereits verbucht; es wurde deshalb kein erneutes DDL und keine manuelle Änderung der Migrationshistorie vorgenommen. Prod-Cache, Schema, tatsächliches Verbindungsziel und HTTPS wurden geprüft bzw. erneuert.

Der hierbei erstellte und tatsächlich wiederhergestellte aktuelle Snapshot liegt außerhalb Git unter:

```text
/home/diversworld/.copilot/session-state/7ba4614b-3b41-43f7-983c-f277e378a7fc/files/phase3-before-local-prod-20261024/
```

**Trotz Verzeichnisname ist dies kein Vorher-Backup vor der Phase-3-Datenbankmigration:** Das Schema war bereits aktuell. Der Snapshot enthält `database.sql`, `application.tar` und `SHA256SUMS`, mit Verzeichnisrechten 0700 und Dateirechten 0600. Die Wiederherstellung in eine neue isolierte Testdatenbank und eine private Anwendungskopie war erfolgreich; alle ursprünglichen Daten in 77 Anwendungstabellen blieben unverändert. Snapshot und Archiv enthalten personenbezogene Daten/Secrets und dürfen nicht veröffentlicht werden.

Für zukünftige Updates denselben Snapshotablauf mit einem neuen eindeutigen Backupverzeichnis verwenden und die Quelle unmittelbar vor dem Upgrade prüfen. Die Phase-3-Migration ist additiv, prüft doppelte Mitgliedsnummern vor DDL und hat zum Schutz der Finanz-/Vorgangsdaten kein automatisches `down()`. MySQL-DDL kann teilweise committed sein; bei Abbruch den tatsächlichen Stand prüfen, nicht Historieneinträge fingieren. Für einen Rückfall einen zusammengehörigen, geprüften Release-/Datenstand wiederherstellen; alte Anwendungsversionen nicht ungeprüft gegen das neue Schema starten.

SEPA-Schlüssel, PHP-sodium-Anforderung, Senderkonfiguration und täglicher Job werden in [Phase-3-Mitgliederverwaltung](../phase3-membership.md#betrieb-schlüssel-und-nachrichten) beschrieben. Es wurde kein echter Versand eingeschaltet, kein Bankeinzug ausgeführt und kein Cronjob installiert.

## Vorbereitung

1. Release-Version und tatsächlich verwendete PHP-/MariaDB-Version dokumentieren. Entwicklungstests mit dem vorhandenen PHPUnit 13 benötigen PHP >=8.4.1 sowie `pdo_sqlite`; die vorhandenen MySQL-/MariaDB-Migrationen zusätzlich `pdo_mysql`.
2. Secrets ausschließlich außerhalb des Repositorys verwalten. Bestehende `.env.local`, `.env.test.local`, `DATABASE_URL`, Mailer-Konfiguration und private Dokumentpfade nicht durch Beispiele überschreiben.
3. Auf einer isolierten Kopie der Zielumgebung Composer-Abhängigkeiten aus dem Lockfile installieren und das Basis-Gate ausführen.
4. Datenbank, private Dokumente/Uploads, Konfiguration und `var/install-completed.marker` konsistent sichern. Der Installationsmarker darf bei Deployment, Cache-Bereinigung oder Restore nicht versehentlich verschwinden.
5. Restore in eine neue, zugriffsbeschränkte Testdatenbank tatsächlich ausprobieren. Ein existierender Dump allein ist kein Wiederherstellungsnachweis.

## Beispiel für ein Datenbankbackup

Nur mit einer vom Betreiber bereitgestellten, restriktiv geschützten Client-Konfiguration; keine Passwörter in Shell-History oder Prozessargumenten:

```bash
mariadb-dump --defaults-extra-file=/secure/dicoma-backup.cnf \
  --single-transaction --routines --events --triggers \
  --databases dicoma > /secure/backups/dicoma-before-phase2.sql
```

Der Datenbankname und die Pfade sind Platzhalter. Beim Restore eines Dumps mit `--databases` enthaltene `CREATE DATABASE`-/`USE`-Anweisungen berücksichtigen: Ein solcher Dump darf nicht ungeprüft auf einem gemeinsam genutzten Produktionsserver importiert werden. Restore ausschließlich auf einem isolierten Testserver durchführen oder einen hierfür separat erstellten Dump ohne diese Anweisungen verwenden.

Backups enthalten personenbezogene und finanzielle Daten: Zugriff beschränken, verschlüsselt aufbewahren, Integrität prüfen, Aufbewahrungsfristen festlegen und außer Haus nur in freigegebene Infrastruktur übertragen.

## Migrationsablauf

Die Personenmigration verschiebt Daten und entfernt anschließend die bytegenau verifizierten Altspalten. Sie benötigt ein Wartungsfenster:

1. HTTP-Schreibzugriffe, Worker und geplante Jobs stoppen; sicherstellen, dass auch zwischen beiden Migrationsschritten niemand Personendaten schreibt.
2. Geprüftes konsistentes Backup erstellen und Zielverbindung unabhängig kontrollieren.
3. Migration auf Staging zuerst bis `DoctrineMigrations\Version20261023100000` ausführen.
4. Kopierte Personen, Kontozuordnungen und Member-Anzahl kontrollieren. Danach bis `DoctrineMigrations\Version20261023110000` migrieren. Bei einem Prüfabbruch **nicht** mit SQL-Spaltenlöschungen oder manuell gesetzten Migrationsversionen umgehen.
5. `doctrine:schema:validate` und `doctrine:migrations:up-to-date` gegen genau diese Staging-Datenbank ausführen.
6. Cache mit den richtigen Umgebungsparametern neu aufbauen; HTTP- und fachliche Regressionstests ausführen.
7. Erst nach dokumentierter Freigabe denselben kontrollierten Vorgang für das Produktionsrelease planen. Die hier erfolgte Entwicklung führt keine Produktivmigration aus.

Die ergänzende Migration `Version20261024100000` sichert den Unique-Index für `(person_id, club_id)` auch auf bereits normalisierten Installationen ab. Auf dem lokalen `db_test` fehlte dieser Index trotz verbuchter Personenmigrationen. Die neue Migration verändert keine Mitgliederdaten, akzeptiert einen korrekt vorhandenen Index und bricht bei Dubletten oder einer abweichenden gleichnamigen Indexdefinition ab. Nicht durch `schema:update --force` oder manuelles Zurücksetzen der Migrationshistorie ersetzen.

MySQL-/MariaDB-DDL kann bereits vor einem Fehler committed sein. Bei teilweise ausgeführter erster Migration Schema und Daten vor einem erneuten Start ausdrücklich prüfen; ein vorhandenes `person`-Schema führt bewusst zu einem Abbruch.

## Rückfallstrategie

### Tatsächlich ausgeführte lokale Aktualisierung

Am 24.10.2026 wurden mit ausdrücklicher Freigabe zuerst der reale lokale Bestand `db` und die aktuelle Anwendung auf neue temporäre Ziele restauriert und mit unveränderten Originalzeilen verlustfrei migriert. Erst nach erfolgreicher kompletter Migrationssuite und zusätzlicher Freigabe wurden die 24 ausstehenden Migrationen auf lokalem `db` angewendet. Der Prod-Cache wurde neu aufgebaut; die ignorierte lokale Prod-Konfiguration verbindet jetzt ausdrücklich Dienst `db` und Datenbank `db`, deaktiviert Debug/Installation und markiert die Installation als abgeschlossen. Die Testverbindung bleibt auf dem separaten Dienst `db_test`.

Das aufbewahrte Vorher-Backup liegt lokal außerhalb Git unter:

```text
/home/diversworld/.copilot/session-state/7ba4614b-3b41-43f7-983c-f277e378a7fc/files/phase2-before-local-prod-20261024/
```

Es enthält `database.sql`, `application.tar` und `SHA256SUMS`. Verzeichnisrechte 0700, Backupdateien 0600. Das Archiv enthält lokale Secrets und personenbezogene Daten: nicht veröffentlichen oder in Git übernehmen. Integrität vor Benutzung mit `sha256sum -c SHA256SUMS` prüfen. Dieses Vorher-Abbild entspricht dem damaligen tatsächlichen Arbeitsstand einschließlich der damals inkonsistenten Prod-Konfiguration/Cache-Annahmen; es wird nicht als separat freigegebenes älteres lauffähiges Release ausgegeben.

Für zukünftige freigegebene lokale historische Snapshots:

```bash
DICOMA_PHASE2_BACKUP_DIRECTORY=/secure/new-local-backup-directory \
  bash scripts/phase2_target_snapshot_check.sh --confirm-local-db-snapshot --keep-backup
```

Das Zielverzeichnis muss neu und absolut sein. Der Skriptlauf liest ausschließlich Dienst `db`, prüft InnoDB, archiviert vorhandene Anwendung/Abhängigkeiten/Konfiguration/Dateien, restauriert auf neuem `dicoma_snapshot_test_*` in `db_test` sowie neuer privater Anwendungskopie und führt nur dort Migrationen aus. Ziel-Events dürfen nicht aktiv ausgeführt werden. Ohne `--keep-backup` werden auch die temporären Archive entfernt; alle eigens erzeugten temporären Datenbanken/Anwendungskopien werden gezielt bereinigt. Das Skript führt keine Migration auf der Quelldatenbank aus.

Der Snapshotprüfer unterstützt sowohl historische Bestände ohne `person` als auch bereits normalisierte Ausgangsbestände. Bei historischen Beständen prüft er zusätzlich jedes extrahierte Personenfeld; bei normalisierten Beständen vergleicht er sämtliche ursprünglichen Spalten aller Anwendungstabellen einschließlich `person`, Mitgliedern und Konten. Ein erneuter Lauf auf dem aktuellen Bestand ist ein Nachweis des aktuellen additiven Upgrades, nicht eine Wiederholung der historischen Personenmigration. Für einen nachträglichen historischen Nachweis zuerst das Vorher-Backup auf einen **neuen separaten Dienst** wiederherstellen und die Verbindung im Prüfskript dafür bewusst anpassen; niemals `db` mit dem alten Dump überschreiben, um einen Test erneut grün zu bekommen.

Beide Personenmigrationen verweigern automatisches `down()`. Ein Rückfall erfordert entweder eine eigens geprüfte Reverse-Datenmigration oder die Wiederherstellung des zusammengehörigen Datenbank-, Datei- und Anwendungssnapshots. Alten Code nicht gegen das normalisierte Schema starten. Während der Wiederherstellung müssen Schreibzugriffe weiterhin gesperrt bleiben.

Vor Freigabe mindestens Anzahlen und IDs, Personenfelder einschließlich Unicode/NULL/führender PLZ-Nullen, Kontozuordnungen, Kurs-/Mannschaftsreferenzen, Dokumentdownloads und Anmeldung prüfen. Wiederherstellungsdauer und Datenverlustfenster messen und vom Betreiber abnehmen lassen.

## Technisches Basis-Gate

```bash
ddev exec bash scripts/phase2_acceptance_gate.sh
```

Ohne DDEV mit gleichwertiger PHP-Umgebung:

```bash
bash scripts/phase2_acceptance_gate.sh
```

Das Basis-Gate führt Unit- und isolierte SQLite-Tests aus. Es schreibt keine Datensätze in die konfigurierte Anwendungsdatenbank, erstellt keine Staging-Fixtures und führt keine Migration aus.

## Optionale Staging-Abnahme

Vor dem folgenden Aufruf die Umgebungswerte aus [HTTP-Abnahme](../PHASE2-HTTP-ABNAHME.md) und alle weiteren von der vorhandenen HTTP-Suite geforderten Fixtures konfigurieren:

```bash
APP_ENV=test \
DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED=1 \
DICOMA_TEST_EXPECTED_DATABASE=db_test \
DICOMA_RUN_STAGING_DB_CHECKS=1 \
bash scripts/phase2_acceptance_gate.sh
```

Zusätzlich `DICOMA_RUN_STAGING_HTTP_TESTS=1` und `DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED=1` setzen, wenn die komplette HTTP-Suite einschließlich Schreibtests auf ausdrücklich dafür vorgesehenen Wegwerfdatensätzen laufen soll. Fehlende Fixtures führen durch `--fail-on-skipped` zum Fehlschlag, nicht zu einer Freigabe.

Die vorhandene Doctrine-Testkonfiguration hängt `_test` an den Datenbanknamen an. Dienstname, Basisname in `DATABASE_URL` und tatsächlicher Datenbankname sind nicht dasselbe. Bei DDEV kann beispielsweise Dienst `db_test` mit Basisname `db` zum tatsächlichen Namen `db_test` führen. Den tatsächlichen Wert kontrolliert:

```bash
APP_ENV=test php bin/console app:test-database:check --expected=db_test --env=test
```

Die Prüfung ist lesend und verweigert unbekannte Namen, falsche Umgebungen und eine nicht übereinstimmende reale Verbindung. Sie ersetzt keine getrennten Datenbankbenutzer, Server und Backups.

## Reproduzierbare lokale Backup-/Restore-Probe

Nur nach ausdrücklicher Testfreigabe, ohne gleichzeitige Schreibzugriffe auf `db_test`:

```bash
docker exec -i ddev-dicoma-db_test \
  env DICOMA_PHASE2_RESTORE_TEST_CONFIRMED=1 bash -s \
  < scripts/phase2_backup_restore_check.sh
```

Der Containername gehört zum lokalen DDEV-Projekt `dicoma`. Das Skript prüft die Dienstkonfiguration und den tatsächlichen Quelldatenbanknamen. Ein eingeschalteter Event-Scheduler führt zum Abbruch, damit wiederhergestellte Events nicht selbsttätig laufen. Es erstellt einen zugriffsbeschränkten Dump einschließlich Routinen, Triggern und Events, berechnet SHA-256, stellt ihn in einer zufällig benannten neuen Datenbank auf demselben Testdienst wieder her und vergleicht den erneuten Schema-/Daten-Dump bytegenau. Es überschreibt `db_test` nicht. Dumpdateien und die eigens erzeugte Restore-Datenbank werden auch bei Fehlern gezielt entfernt; Bereinigungsfehler führen zum Fehlschlag.

Das ist ein Wiederherstellungsnachweis für den lokalen Testbestand, **nicht** für Produktivdaten, private Dokumentdateien, Konfiguration oder die Wiederanlaufzeit des vollständigen Systems. Die produktionsnahe Snapshot-/Datei-Wiederherstellung aus der Vorbereitung bleibt separat erforderlich. Das Skript niemals gegen den normalen `db`-Dienst oder Produktion ausführen.

## Sicherheitsrelevante Betriebsbeobachtung

Fehlerhafte Anmeldung/Verifizierung, unzulässige Verwaltungszugriffe, Installationsversuche und fehlgeschlagene Konfigurations-/Datenbankzugriffe in den vorhandenen Symfony-/Monolog-Protokollen überwachen. Protokolle restriktiv berechtigen und rotieren; niemals Passwörter, Reset-/Verifizierungstokens oder vollständige Verbindungsstrings protokollieren. Eine zentrale, revisionssichere Audit-/Alarmierungsplattform ist durch diese Phase nicht eingerichtet.
