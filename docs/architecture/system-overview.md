# Systemübersicht – Phase 2 bis 6 und Phase 8

DICOMA bleibt eine bestehende Symfony-7.4-Anwendung mit Doctrine ORM 2, Twig, Symfony Security und EasyAdmin. Fachlogik bleibt in den vorhandenen Services, HTTP-Orchestrierung in den vorhandenen Controllern. MySQL/MariaDB ist die von der vorhandenen Migrationshistorie unterstützte Datenbank.

```text
HTTP / Symfony Security / CSRF
  -> Controller und Symfony Forms / EasyAdmin
     -> Voter und fachliche Services
        -> Doctrine-Repositories und Datenbank-Constraints
```

Personenstammdaten werden zentral gehalten; fachliche Mitgliedschaften, Benutzerkonto und Rollen bleiben getrennt. Siehe [Datenmodell](data-model.md) und [Rollenvertrag](roles-permissions.md).

## Austauschbares Airspace-Theme und öffentliche Redaktion

Das vorhandene Themefisher-[Airspace-Bootstrap-Theme](https://github.com/themefisher/airspace-bootstrap/)
ist als Standardtheme `airspace` integriert. Die bereits vorhandenen lokalen
Bootstrap-4-/Airspace-Assets werden weiterverwendet, nicht mit einer zweiten
Bootstrap-Version vermischt. Der MIT-Lizenztext liegt unter
`public/themes/airspace/LICENSE`; der Themefisher-Nachweis bleibt erhalten.
Die gemeinsame Primärfarbe ist **#003366**, einschließlich Bootstrap-
Primärbuttons, öffentlichem Frontend und Funktionsportalen.

`SiteTheme` wählt ausschließlich registrierte Themes aus `site.themes`
in `config/services.yaml`. `SITE_THEME=airspace` ist die Voreinstellung.
`templates/base.html.twig` ist nur der stabile Einstieg und erbt das
konfigurierte Layout; die Airspace-Implementierung liegt unter
`templates/themes/airspace/`. Gemeinsame Assets werden durch
`templates/themes/_assets.html.twig` eingebunden. Mitgliederbereich,
Gerätewart-, Ausbilder-, Ausbildungsbewertungs- und Thekendienstportal
verwenden dieselbe Auswahl, behalten jedoch ihre Fachnavigation und
Berechtigungsgrenzen. Auch die Mannschafts-/Turnieransichten ohne
Adminrolle verwenden das Theme; Adminansichten bleiben in EasyAdmin.
EasyAdmin und druckbare Ausbildungsnachweise
behalten ihre eigenen Layouts.

Ein neues Theme erhält einen eigenen Layoutpfad und eine registrierte
Stylesheetliste. Das Layout muss die bestehenden Blöcke `title`,
`stylesheets`, `hero`, `body` und `javascripts` unterstützen und die
gemeinsamen Assets einbinden. Portalspezifische Layouts/CSS bleiben getrennt.
Neue Themes müssen mit der vorhandenen Bootstrap-4-Komponentenstruktur
kompatibel sein oder einen expliziten, getesteten Komponentenadapter
mitbringen; ein beliebiger Austausch der Bootstrap-Hauptversion ist
kein unterstützter Konfigurationswechsel. Unbekannte Theme-Namen scheitern
ausdrücklich statt unbemerkt auf Airspace zurückzufallen.

### Veröffentlichungen

Der neue Bereich `/editorial` ist Vorstand und `ROLE_PRESS_OFFICER`
(Pressewart) des konfigurierten Website-Vereins vorbehalten. Es gibt keine
globale Vereinssuche oder automatische Veröffentlichung bestehender
Fachdaten. `PUBLIC_SITE_CLUB_ID` bestimmt den Website-Verein. Ohne
Konfiguration bleiben neue öffentliche Inhaltslisten leer und die
Redaktion ist mit ausdrücklichem Konfigurationshinweis gesperrt.
Bei gesetztem Website-Verein wird auch das öffentliche Kursangebot
auf diesen Verein begrenzt.

`WebsitePublication` speichert Artikel oder ausdrücklich kuratierte
Veranstaltungsankündigungen mit Titel, Kurztext, Text, Entwurf/Freigabe
und Veröffentlichungszeitpunkt. Veranstaltungen benötigen öffentlichen
Ort sowie Beginn/Ende. Optional ist genau ein freigegebener eigener
Tauchkurs oder ein Turnier verknüpfbar. Termine und Texte werden redaktionell
festgelegt, nicht aus internen Beschreibungen kopiert. Absage, Entwurfsstatus
oder Vereinswechsel einer Quelle blendet die Ankündigung bei jedem
öffentlichen Abruf aus. Thekendienste und Diensteinteilungen werden
ausdrücklich nicht veröffentlicht.

Die Startseite zeigt aktuelle Artikel und kommende Veranstaltungen.
`/articles`, `/events` und `/news/{id}` sind ohne Anmeldung lesbar;
Entwürfe, noch nicht fällige Freigaben und fremde Vereinsinhalte liefern
keine Detailansicht. Redaktionelle Texte sind bewusst reiner Text und
werden HTML-escaped ausgegeben. Schreibvorgänge verwenden Symfony Forms,
CSRF, Vereinsprüfung und Versionsschutz gegen veraltete Formulare.
Eine Freigabe lässt sich durch erneutes Speichern als Entwurf zurückziehen;
Änderungen behalten einen Bearbeiter-/Zeit-/Freigabenachweis.

## Phase 8: Thekendienstverwaltung

Der Bereich `/bar-duty` integriert Mitglieder, die neue Funktionsrolle
`ROLE_BAR_MANAGER`, den gemeinsamen Vereinskalender und einen persönlichen
Nachrichtenbereich. Alle Thekendienstansichten verwenden ein eigenständiges
Portal mit eigener Kopfzeile, Bereichsnavigation und Abmeldung, unabhängig
vom öffentlichen Mitgliederlayout und von EasyAdmin. Auch Admins bleiben
im Thekendienstportal; der Rückweg zur Verwaltung ist ausdrücklich verlinkt.
Mitglieder sehen nur die für sie freigegebenen Navigationsziele.
Schichtdefinitionen legen Ort, Uhrzeit, Dauer und
Kapazität fest; geplante Schichten übernehmen diese Werte als historischen
Stand. Definitionen lassen sich deaktivieren, bestehende Dienste bleiben
erhalten. Anmeldungen, Zuweisungen und Übergaben prüfen aktive Mitgliedschaft,
bekanntes Eintritts-/Kündigungsdatum, freie Plätze und Überschneidungen.
Direkt angrenzende Dienste sind zulässig.

Der **Monatskalender** zeigt Veranstaltungen pro Tag einschließlich
eingeteilter Namen und freier Plätze. Mitglieder öffnen die Veranstaltung
per Klick und melden sich dort für den Dienst an. Die Thekenleitung kann
direkt am Kalendertag Veranstaltungen mit Titel, Ort, Beginn, Dienstdauer
und Personalbedarf eintragen. Der Kalender verwendet die vorhandenen
Kalenderstile und ist auf kleinen Bildschirmen horizontal scrollbar.

Clubabende lassen sich als **wöchentliche oder zweiwöchentliche Serie**
an ausgewählten Wochentagen zwischen ausdrücklich angegebenem Beginn
und einschließlich Enddatum planen. Die Woche des Beginns ist die erste
Serienwoche. Es werden nur die passenden Tage innerhalb der Grenzen
angelegt, jeweils zur gewählten Uhrzeit; kein unbegrenzter Hintergrundlauf.
Alle Termine einer Anfrage werden atomar angelegt. Eine identische aktive
Vorlage wird wiederverwendet; ein bereits vorhandener Termin verhindert
die gesamte Neuanlage. Einzelne Serientermine bleiben separat absagbar,
ohne die übrigen Termine oder deren Einteilungen zu verändern.

Alle Änderungen werden vereinsweise durch eine Datenbanksperre serialisiert.
Verbindliche Belegungsprüfungen lesen den aktuellen Datenbankstand unter
Sperre statt einen alten `REPEATABLE READ`-Snapshot. Konflikte werden
ausdrücklich abgewiesen. Tausch und Vertretung ändern die Einteilung erst
nach Zustimmung der betroffenen Zielperson, ohne zusätzliche Freigabe der
Thekenleitung. Beidseitige Wechsel sind atomar; bei Konflikten bleiben
sämtliche ursprünglichen Einteilungen erhalten. Rücknahme und Absage
ziehen offene Anfragen zurück.

### Dienststunden und Auswertung

Pflichtminuten werden **je Verein und Kalenderjahr** ausdrücklich festgelegt.
Individuelle begründete Ausnahmen ersetzen diese Regel: `0` bedeutet
Befreiung, ein leerer Wert stellt die Vereinsregel wieder her. Fehlende
Jahresregeln werden als unbekannt angezeigt, nicht als Nullpflicht.
Geplante Stunden werden nicht gutgeschrieben. Erst die Thekenleitung
bestätigt nach Schichtende Anwesenheit und tatsächliche Minuten; Abwesenheit
hat immer null Minuten. Korrekturen ersetzen die aktuelle Gutschrift mit
zusätzlichem Historieneintrag. Ein abgeschlossener Dienst ist unveränderlich.
Maßgeblich ist das Jahr des Schichtbeginns; **kein automatischer Übertrag**.
Eigene Konten sind persönlich, die vereinsweite Jahresauswertung und
CSV-Exporte nur für die Thekenleitung sichtbar.

### Eigenständige Thekenkasse

Die Jahresregel kann die separate Thekenkasse aktivieren. Jede Schicht
besitzt höchstens eine eigene Kasse mit ausdrücklich gezähltem Anfangsbestand,
Einnahmen, Ausgaben und nachvollziehbaren Gegenbuchungen. Beträge sind ganze
Cent; negative Bestände, mehrfaches Stornieren und fremde Belege werden
verhindert. Ein Abschluss speichert Soll-/Istbestand, Bearbeiter, Zeitpunkt
und Bewegungshash; Differenzen benötigen eine Begründung. Danach sind keine
Änderungen möglich. Stundenabschluss und Kassenabschluss sind unabhängig;
ein Kassenabschluss bleibt auch nach Stundenabschluss möglich. Eine
eröffnete Kasse verhindert die Schichtabsage. Es gibt **keine Vermischung mit
der Gerätewartkasse** und keine automatische Verrechnung von Pflichtstunden.
Die Jahresauswertung verlinkt offene/abgeschlossene Kassen und deren Journal.

### Benachrichtigungen

Einteilung, Rücknahme, Anfrage, Entscheidung, bestätigte Stunden und Absage
erzeugen private Portalnachrichten. `app:bar-duty:remind --hours=48` ergänzt
idempotente Erinnerungen für noch aktive, kommende Dienste. E-Mail ist ein
explizit aktivierter zusätzlicher Versandkanal, nicht Voraussetzung für das
Portal. Versandzustände und Wiederanlauf sind im
[Betriebshandbuch](../deployment/operations.md#phase-8-migration-und-benachrichtigungen)
beschrieben.

## Phase 6: Inventar, Gerätewart und Verleih

Der operative Gerätewartbereich verbindet **Inventar und Wartung**,
**Ausleihe**, **Kompressorlogbuch**, **Füllgebühren** und **Kasse**.
Die bestehenden Stammdatentabellen für Ausrüstungsartikel, Atemregler
und Vereinsflaschen bleiben erhalten. Eine gemeinsame Inventarakte
ergänzt Eigentumsverhältnis, Eigentümer/Leihgeber, Lagerort,
Anschaffungsdatum und -preis, Prüfintervall, Genehmigungspflicht,
Dokumente und Gerätehistorie. Kategorien, Inventar- und Seriennummern
bleiben bei den bestehenden Stammdaten. Die globalen Stammdaten-CRUDs
bleiben Superadmin-only; operative Rechte werden nicht global erweitert.

### Verleihprozess

1. Mitglieder oder Verwaltung reservieren einzelne Inventargegenstände.
   Die Verfügbarkeit wird vor dem Speichern und erneut vor Ausgabe
   unter Datenbanksperren geprüft. Benachbarte Zeiträume dürfen sich
   berühren; Überschneidungen nicht. Noch ausgegebene Positionen
   blockieren unabhängig vom ursprünglichen Buchungsende.
   Die verbindliche Belegungsprüfung verwendet aktuelle, sperrende
   Lesezugriffe statt eines eventuell früher erzeugten
   `REPEATABLE READ`-Snapshots. MariaDB-Snapshotkonflikte führen zu
   einer ausdrücklichen Ablehnung mit Neuladehinweis, niemals zu
   einer vermeintlich erfolgreichen Buchung.
2. Die zusätzliche Gerätewart-Genehmigung ist **je Gegenstand
   konfigurierbar**, standardmäßig nicht erforderlich. Die Buchung
   merkt sich die Pflicht; die Ausgabe prüft auch die aktuelle
   Inventarfreigabe. Ein nachträglich hinzugefügter
   genehmigungspflichtiger Gegenstand hebt die Genehmigung auf.
3. Ausgabe ist nur im reservierten Zeitraum möglich. Gesperrter
   Zustand, fehlende Verleihfreigabe, offene Wartung, deaktivierte
   Kategorie sowie fehlende oder nicht bis zum Rückgabetag gültige
   vorgeschriebene Prüfung verhindern die Ausgabe. Sammelausgabe
   erfolgt vollständig oder gar nicht.
4. Die Summe der hinterlegten einmaligen Einzel-Leihgebühren wird bei
   Buchung in ganzen Cent festgeschrieben. Weitere Positionen ergänzen
   diesen Preis. Es wird kein neuer Tagestarif angenommen. Fehlende
   Altpreise müssen ausdrücklich bestätigt werden, auch bei 0 EUR.
   Die einmalige Bar-/Überweisungsverbuchung muss dem festgeschriebenen
   Gesamtbetrag entsprechen; Teil- und Überzahlungen werden abgewiesen.
5. Rückgabe erfasst Zustand und Bemerkung je Position. Schäden und
   Wartungsbedarf benötigen eine Beschreibung, sperren den Gegenstand
   und erzeugen einen technischen Auftrag mit Verantwortlichkeit.
   Auftragsabschluss und technische Freigabe sind getrennte Schritte.
6. **Strikter Abschluss:** Alle Positionen müssen zurückgegeben,
   die festgeschriebene Gebühr bezahlt oder ausdrücklich kostenlos
   sein. Ein beschädigt zurückgegebenes Gerät muss nach bearbeiteter
   Wartung technisch freigegeben sein. Dokumentierte Bargeld-
   Rückzahlungen werden bei der Zahlungsprüfung abgezogen.

Stornierte Reservierungen belegen keine Verfügbarkeit mehr. Alte
Einzelreservierungen bleiben als Historie erhalten und können noch
zurückgenommen werden; Neuanlage führt in den konsolidierten Verleih.
Genehmigungspflichtige, noch nicht ausgegebene Altreservierungen müssen
dort neu angelegt werden. Eine mit einem allgemeinen Ausrüstungsartikel
verknüpfte Vereinsflasche ist **ein physischer Gegenstand**, nicht zwei:
Reservierungen, Sperren, Wartung, Inventarakte und Dokumente verwenden
dieselbe Flaschenidentität.

### Wartung und Nachweise

Der vorhandene Flaschen-Wartungsauftrag unterstützt nun auch allgemeine
Ausrüstung und Atemregler sowie Wartung, Reparatur und Defektmeldung.
Prüfung/Revision speichert Durchführungsdatum, nächsten Termin und
Prüfer-/Nachweistext. Ohne ausdrücklich eingegebenen nächsten Termin
wird das hinterlegte Monatsintervall angewendet, am Monatsende auf
den letzten gültigen Kalendertag begrenzt. Für Flaschen und Atemregler
bleiben ihre bisherigen Prüf-/Revisionstermine maßgebend; allgemeine
Ausrüstung nutzt den Termin ihrer Inventarakte.

Die technische Freigabe verlangt abgeschlossene Aufträge, keine noch
ausgegebene Ausrüstung und gültige vorgeschriebene Prüfungen.
Ausgemusterte allgemeine Ausrüstung wird nicht automatisch reaktiviert.
Historie und Berichte werden ergänzt, nicht durch eine Freigabe gelöscht.
PDF-, JPG- und PNG-Nachweise bis 5 MiB nutzen die vorhandene private
Dokumentenablage mit vereinsgeprüftem Attachment-Download, `nosniff`
und ohne öffentlichen Cache.

### Flaschen, Füllungen und Kasse

Die bestehende Flaschenverwaltung einschließlich TÜV-Prüfgebühren,
Wartung und Abrechnung bleibt integriert und über die Inventarakte
erreichbar. Einzel- und Gruppenfüllungen benötigen zusätzlich
`ROLE_COMPRESSOR_OPERATOR` im Gerätewartbereich. Vereinsflaschen
brauchen einen einsatzbereiten Zustand, eine gültige Flaschenprüfung
und dürfen keinen offenen Wartungsauftrag haben. Eine fehlende
Verleihfreigabe allein verbietet keine Füllung. Externe Flaschen
behalten ihre manuell bestätigte Sicherheitsprüfung.

Jede neue Einzel- oder Gruppenfüllung hält eine **ausdrücklich
vereinbarte Gesamtfüllgebühr** fest; 0 bedeutet kostenlos.
Es wird kein größenabhängiger Tarif erfunden. Füllung und Gebührenbeleg
werden atomar gespeichert. Doppelte Vereinsflaschen in einer Gruppe
und Füllungen in geschlossenen Füllabenden werden abgewiesen.
Der Abschluss hält den abschließenden Betriebsstundenzähler fest und
ist mit Gruppenfüllungen über dieselbe Datenbanksperre serialisiert.

Bar-/Überweisungszahlungen für Füllungen erscheinen einmalig in
Hauptjournal, Tagesauswertung und CSV. Barzahlungen fließen außerdem
in Kassenbestand, Auszahlungen und Tagesabschluss samt Bewegungshash
ein. Rückzahlungen sind separate Kassenkorrekturen; Originalbelege
bleiben erhalten. Historische Füllungen erhalten nicht nachträglich
erfundene Gebühren. Die Schadensbearbeitung erfolgt technisch über
Defekt-/Reparaturauftrag und Freigabe; eine automatische Berechnung
von Schadenersatz oder eine neue Schadenersatzbuchhaltung wird nicht
eingeführt.

## Phase 5: Mannschaften und Turniere

Der operative Einstieg ist **Mannschaften & Turniere** mit **Mannschaften und
Kader** sowie **Turniere und Historie**. Die bisherigen globalen
Mannschafts-CRUDs bleiben Superadmin-only, werden aber nicht als parallele
operative Menüeinträge angeboten. Berechtigte Trainer finden ihre
Mannschaften auch im Mitgliederbereich.

### Mannschaften und Spielberechtigung

Eine Mannschaft gehört weiterhin über ihre Sportart zu genau einem Verein.
Ihr Kader verwendet die vorhandenen `TeamMember`-Zuordnungen, einschließlich
Trainer, Co-Trainer, Mannschaftsführung und Betreuung. Position,
Rückennummer, ausdrücklich geprüfte Spielberechtigung, Nachweis und
Gültigkeitsdatum ergänzen die Zuordnung. Neue Trainingsdatensätze halten
Beginn, Ende, Trainingsort und Absage fest. Die Sportart einer bestehenden
Mannschaft wird im operativen Formular nicht nachträglich gewechselt.

Bestehende Mitglieder und Zuordnungen bleiben erhalten; die Migration
erteilt **keine automatische Spielberechtigung**. Für einen internen
Turnierspieler werden aktive Mitgliedschaft, aktive Mannschaft, Spieler-
oder Mannschaftsführungsrolle, vollständiger Zuordnungszeitraum sowie
aktive und zeitlich passende Sportzuordnung geprüft. Ein hinterlegtes
Berechtigungsende muss mindestens bis zum letzten Turniertag reichen.
Die Prüfung erfolgt bei der Registrierung und erneut vor Turnierstart.

### Turnierablauf

1. Ein Vereinsadmin erstellt eine Ausschreibung mit Sportkennung,
   Veranstalter, Ort, Zeitraum, optionalem Meldeschluss, Grenzen,
   Mannschaftsgebühr und Sieg-/Unentschieden-/Niederlagenpunkten.
2. Die Turnierleitung öffnet die Meldung. Betreuende Trainer und
   Mannschaftsführungen melden eigene Mannschaften und berechtigte
   Kaderspieler. Veranstalter können zusätzlich externe Mannschaften und
   Spieler manuell erfassen.
3. Der Veranstalter erstellt freie Begegnungen oder einen
   **Jeder-gegen-jeden-Plan je Gruppe**. Gerade und ungerade Gruppengrößen
   sind unterstützt; bei ungerader Zahl gibt es rundenweise Freilose.
   Spiel- einschließlich Wechselzeit muss vollständig in den Zeitraum
   passen. Eine Mannschaft spielt höchstens einmal je Runde;
   Mannschafts- und Spielfeldüberschneidungen werden abgewiesen.
4. Ein noch nicht gestarteter, vollständig ungewerteter Plan kann nach
   ausdrücklicher Bestätigung zurückgesetzt werden. Erst danach ist der
   Rückzug bereits eingeplanter Mannschaften möglich. Ergebnisse und
   deren Protokolle werden dadurch niemals gelöscht.
5. Nach Turnierstart erfasst die Leitung Ergebnisse einschließlich
   Begründung. Korrekturen ergänzen das Ergebnisprotokoll mit Zeitpunkt
   und Bearbeiter; Versionsprüfungen verhindern veraltetes Überschreiben.
6. Abschluss ist erst möglich, wenn jedes aktive Spiel ein Ergebnis hat.
   Das Abschlussarchiv enthält Tabellen, Begegnungen samt Protokollen
   und Zeitangaben sowie Kader- und Nachweissnapshots. Sportliche Daten
   bleiben danach schreibgeschützt; Gebühren können weiterhin beglichen
   oder erstattet werden.

Die Stammdaten und Wertungsregeln sind ab der ersten Mannschaftsmeldung
gesperrt. Registrierungen und Kaderänderungen enden mit Meldeschluss oder
Turnierstart. Absagen sind eigene, unveränderliche Endzustände. Tabellen
ignorieren abgesagte Spiele; die Sortierung ist Wertungspunkte,
Punktdifferenz und erzielte Spielpunkte. Vollständig gleiche Werte erhalten
denselben Rang, Namen sorgen nur für eine stabile Anzeige.

Externe Spieler sind **Turnierregistrierungen, keine künstlichen
Vereinsmitglieder oder Personenstammsätze**. Namen lösen keine automatische
Verknüpfung mit existierenden Personen aus. Die Mannschaftsgebühr wird
bei der Meldung eingefroren. Zahlungen und negative Erstattungen werden
als einzelne Belege geführt; doppelte Belegnummern je Meldung,
Überzahlungen und Erstattungen über die Zahlungssumme sind ausgeschlossen.
Es gibt keine automatische Bank- oder Zahlungsanbieteranbindung.

PDF-, JPG- und PNG-Dokumente bis 5 MiB liegen in der bestehenden privaten
Dokumentenablage. Inhaltstyp und Größe werden serverseitig geprüft;
berechtigte Downloads sind Attachments mit `nosniff` und ohne
öffentlichen Cache. Neue Dokumente sind nach Abschluss/Absage gesperrt.

Sportkennungen entsprechen normalisiert dem Kürzel der bestehenden
Sportart, ersatzweise dem Namen. Weitere Unterwassersportarten benötigen
keine festen Sport-Enums. Die aktuelle Wertung unterstützt ganzzahlige
Ergebnisse und konfigurierbare Tabellenpunkte. Sportartspezifische
Satz-/Strafpunktregeln oder K.-o.-Bäume sind bewusst keine impliziten
Alternativen zur gewählten Gruppen-/Ligastruktur. Die Registrierung ist
bewusst verwaltungsgeführt, nicht öffentlich.

### Gemeinsamer Veranstaltungskalender

`/admin/calendar` projiziert berechtigte Kurstermine, Mannschaftstrainings
und offene/laufende/abgeschlossene Turniere in eine gemeinsame
Monatsansicht. Die Termine bleiben ausschließlich im jeweiligen
Fachmodul gespeichert; Modul-ID und Ereignistyp bilden einen eindeutigen
Schlüssel. Mehrtägige Turniere werden in jedem überlappenden Monat
angezeigt. Abgesagte Veranstaltungen und nicht berechtigte Datensätze
werden ausgeblendet. Die Links führen direkt zur zuständigen
Backendverwaltung, nicht zu einer zweiten Bearbeitungsmaske.

## Vereinskontext

Der Verein eines regulären Administrators wird weiterhin über `User.member.club` bestimmt. Es wird kein frei manipulierbarer Vereinsparameter als Berechtigungsquelle eingeführt. Ein fehlender Kontext führt zur Verweigerung, nicht zu einer globalen Standardabfrage.

Superadmins dürfen vereinsübergreifend arbeiten. Vorhandene Superadmin-only-Sperren globaler Legacy-Verwaltungen werden nicht pauschal aufgehoben. Insbesondere wird keine vermeintliche vollständige Mandantentrennung allein durch eine neue `ClubContext`-Klasse oder einen globalen Doctrine-Filter behauptet.

## Verwaltungsnavigation und Dashboard

Das EasyAdmin-Menü trennt tägliche Aufgaben von seltener gepflegten Grundlagen:

- **Tägliche Verwaltung:** Mitglieder & Verein, Mannschaften & Turniere, Ausbildung & Kurse,
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
Fehlerzustände mitprüfen. Weitere Farben verwenden eine kompakte neutrale,
blaue und semantische Status-Farbskala sowie gemeinsame Overlay- und
Schattenvariablen. Ähnliche bisherige Einzelfarbtöne teilen sich einen
Skalenwert. Bereits vorhandene `--dw-admin-*`-Variablen bleiben als
Bereichsanbindung erhalten;
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
