# Vorbereitung der fachlichen Gesamtfreigabe für Phase 3

## Entscheidungsstand

Eine Gesamt- oder Produktivfreigabe ist noch nicht erteilt. Die fachliche
Freigabe muss durch die zuständige Person erfolgen. Die folgenden Punkte
unterscheiden vorbereitete Nachweise von noch offenen Entscheidungen und
Prüfungen.

## Vereinbarte Rollenregeln

- Der zentrale Kontext ist auf einen Verein umgestellt. Frei anlegbare
  Funktionen und einstellbare Nachfolgepflicht sind vorbereitet. Die
  vereinbarten Modulrechte (Lesen, Anlegen, Bearbeiten, Löschen) können
  getrennt von den bisherigen Rollen gespeichert werden. Ihre serverseitige
  Durchsetzung ist bislang nur für die Mediathek, die redaktionelle
  Bildauswahl, die redaktionellen Beiträge, die Website-Anzeigeeinstellungen,
  Teile der Veranstaltungsverwaltung und die neue Kursbildanzeige
  implementiert, nicht für die übrigen Zugriffswege oder die vollständige
  Kursbildverwaltung.
- Die vorgesehenen Module sind Mitglieder und Mitgliedschaft, Finanzen und
  Beiträge, Ausbildung und Kurse, Sport und Wettkampf, Ausrüstung und TÜV,
  Gerätekasse, Thekendienst, Thekenkasse, Website und Veranstaltungen,
  Dateiverwaltung sowie System- und Rollenverwaltung. Vereinsadministratoren
  dürfen Rechte für Mitglieder, Ausbildung, Sport, Ausrüstung, Website und
  Dateiverwaltung vergeben; die übrigen Module sind bei der Rechtevergabe
  Superadministratoren vorbehalten.
- Die Verwaltung soll eine EasyAdmin-Standardliste der Funktionen und je
  Funktion eine Bearbeitungsseite für Name, Berechtigungen, Nachfolge,
  gemeinsamen Gültigkeitszeitraum sowie Inhaber mit individuellen Zeiträumen
  bieten. Eine Zuordnung ist nur während beider Zeiträume wirksam. Die
  bisherigen individuellen Rollenzeiträume sind bei der Übernahme zu
  erhalten; ein neuer gemeinsamer Zeitraum ist zunächst unbefristet.
- Funktionsrollen sind unabhängig voneinander und optional pro Rolle befristbar.
  Beginn und Ende sind Kalendertage; das Enddatum ist eingeschlossen.
- Bestehende Zuweisungen bleiben ohne ausdrückliche Änderung unbefristet.
  Basisrollen für Konto und Mitgliedschaft werden nicht zeitlich über diese
  Funktion gesteuert.
- Vereinsadministratoren können nur die ausdrücklich delegierbaren Funktionen
  aktiver Mitglieder im eigenen Verein bearbeiten. Geschützte Rollen und
  privilegierte Konten bleiben ihnen verschlossen; Superadministratoren
  verwalten auch deren Gültigkeitszeiträume.
- Vererbte Rechte bleiben wirksam, solange die übergeordnete Rolle gilt. Ein
  Ablauf einer expliziten Unterrolle entzieht daher kein gleiches Recht, das
  weiterhin aus einer anderen gültigen Rolle folgt.
- Trainerrechte für Mannschaften setzen eine ausdrückliche Zuordnung voraus.
  Die Gerätekasse erfordert Gerätewart und Gerätekasse.
- Vor einem manuellen Wechsel in einen nicht aktiven Status ist für jede
  gespeicherte Funktionsrolle eine andere aktive Person desselben Vereins mit
  derselben ausdrücklich und aktuell gültigen Rolle erforderlich. Die Rollen
  werden beim Wechsel dauerhaft entzogen, auch bei späterer Reaktivierung.
  `ROLE_SUPER_ADMIN` ist als Notfallrolle ausgenommen.
- Bei einer Kündigung soll der Vorstand vorab informiert werden; eine offene
  Nachfolge darf den fristgerechten automatischen Austritt nicht verhindern.
  Rollen werden entzogen und beim Austritt bestehende Lücken dokumentiert.

## Fachliche Prüffälle

Die folgenden manuellen Prüffälle sind vor einer Freigabe mit freigegebenen
Testkonten und synthetischen Daten durchzuführen und zu protokollieren:

- [ ] Eine neue Funktion mit mehreren ausdrücklich ausgewählten delegierbaren
      Berechtigungen anlegen, ändern und mehreren Mitgliedern zuordnen. Eine
      Funktion mit privilegierten Rechten darf nur ein Superadministrator
      verwalten; direkte Aufrufe und manipulierte Formulare ebenfalls prüfen.
- [ ] Zwei Funktionen mit derselben Berechtigung und unterschiedlichen
      Zeiträumen zuweisen. Das Entfernen oder der Ablauf einer Funktion darf
      eine weiterhin gültige Berechtigung der anderen nicht entziehen.
- [ ] Gemeinsame und individuelle Gültigkeit unabhängig voneinander und an
      beiden eingeschlossenen Grenztagen prüfen; nur ihre Überschneidung
      gewährt die zugeordneten Rechte. Eine Änderung des gemeinsamen Zeitraums
      darf die gespeicherten individuellen Zeiträume nicht überschreiben.
- [ ] Die Nachfolgepflicht für eine Funktion ein- und ausschalten und den
      Statuswechsel jeweils mit und ohne eine andere aktuell gültige
      Zuordnung derselben Funktion prüfen. Eine andere Funktion mit derselben
      Berechtigung genügt nicht als Nachfolge.
- [ ] Änderungen einer bereits zugewiesenen Funktionsdefinition auf eigene und
      fremde Konten, privilegierte Berechtigungen, offene Formulare und bereits
      angemeldete Sitzungen prüfen.
- [ ] Bestehende direkte Rollen einschließlich Zeiträume, unbekannter Rollen,
      privilegierter Notfallkonten und Konten ohne aktive Mitgliedschaft vor und
      nach der Migration einzeln vergleichen. Backup und Restore vor einem
      Einsatz auf der Ziel-Datenbank nachweisen.
- [ ] Die unter `files/` abgelegten Kursbilder und Mediendateien sind auch
      logisch in einer gemeinsamen, vollständig bearbeitbaren Ordnerstruktur
      zusammenzuführen; die für die Mediathek implementierten Ordnerrechte
      müssen auch für Kursbilder und ihre Bearbeitung gelten.
- [ ] Nach der Zusammenführung sind Kurse und Termine samt Bildanzeige,
      Upload, Ordnern, Umbenennen, Verschieben, Löschen und Verwendungsschutz
      über den Datei-Explorer zu prüfen. Öffentliche Bildauslieferung darf
      geschützte Dateien nicht allgemein verfügbar machen; bestehende Rechte
      der Redaktion dürfen nicht unbeabsichtigt entfallen.
- [ ] Der gemeinsame private Speicher soll unter `files/` außerhalb des
      Webverzeichnisses liegen. Lesen, Hochladen, Bearbeiten und Löschen
      werden getrennt ausschließlich Vereinsfunktionen zugewiesen; auch
      Administratoren dürfen diese vier Rechte zuordnen. Regeln gelten für
      Ordner der ersten Ebene und alle ihre Unterordner ohne Ausnahmen;
      Dateien und Verwaltungsaktionen direkt im Hauptordner sind
      Administratoren vorbehalten. Bei mehreren Funktionen genügt eine
      erlaubende Funktion. Direkte HTTP- und Backend-Zugriffe müssen
      dieselben Regeln durchsetzen.
- [ ] Vereinsadministrator vergibt und entzieht mehrere zulässige Funktionen;
      eigene, fremde, inaktive und privilegierte Konten bleiben ausgeschlossen.
- [ ] Eine Funktion wird vor ihrem Beginn, am Beginn, am Enddatum und nach dem
      Ende geprüft. Andere gültige und vererbte Rechte bleiben erhalten.
- [ ] Superadministrator befristet eine privilegierte Rolle und überprüft das
      Verhalten bei einem eigenen Rollenablauf sowie den administrativen
      Wiederherstellungsweg.
- [ ] Für sämtliche Funktionsrollen Statuswechsel ohne und mit ausdrücklich
      gültiger Nachfolge prüfen; fremde, inaktive, abgelaufene, zukünftige und
      nur vererbte Rollen dürfen nicht genügen. Reaktivierung darf keine
      entfernten Funktionen wiederherstellen.
- [ ] Kündigung mit und ohne Nachfolge sowie ohne zustellbaren Vorstand prüfen:
      rechtzeitig zugestellte Vorabmeldung, fristgerechter Austritt,
      dauerhafter Rollenentzug und sichtbarer Hinweis auf die Nachfolgelücke.
- [ ] Gerätewart ohne Gerätekasse kann operative Vorgänge bearbeiten, aber weder
      kassieren noch Kassenjournal, Korrekturen und Abschlüsse einsehen.
- [ ] Gerätewart mit Gerätekasse kann eine Ausleihe abrechnen und abschließen;
      Zahlungen und Belege stimmen überein.
- [ ] Trainer bearbeitet das Training einer zugewiesenen Mannschaft; andere
      Mannschaften, Sparten und Vereine bleiben gesperrt.
- [ ] Delegierte Funktionen für Ausbildung, Kursverwaltung, Kompressor,
      Theke, Presse und Kassenwart werden je Rolle mit positiven und negativen
      Vereins- und Objektfällen fachlich geprüft.
- [ ] Die zugelassenen Controller und zugehörigen Services einschließlich
      vertraulicher Daten, Exporte, direkter URLs, Schreibwege und
      Nebenläufigkeit sind vollständig nachgewiesen.

## Technische Vorbereitung und offene Gates

- Schema, Funktionsverwaltung, Nachfolgeprüfung, Rollenauflösung und
  Ordnerregeln auf der ersten Ebene sind vorbereitet. Die Mediathek prüft diese Regeln
  für ihre Lese- und Schreibaktionen; bisherige Kursbild-Explorer und
  Kursbild-Uploads sind bei eingeschaltetem Modell vorsorglich gesperrt,
  weil sie noch nicht in dieselbe Ordnerstruktur integriert sind.
  `CLUB_FUNCTION_MEMBERSHIP_ENABLED` ist standardmäßig ausgeschaltet und darf
  vor der vollständigen Umstellung und Abnahme nicht aktiviert werden.
  Das neue Schema ist auf der lokalen Anwendungsdatenbank vorhanden; die
  Rollenübernahme ist dort noch nicht durchgeführt. Die bisherigen Rollenregeln
  bleiben deshalb weiterhin maßgeblich. Funktionen können bereits als
  ausdrücklich gekennzeichnete Vorbereitung angelegt werden; ihre Rechte
  und Inhaberzuordnungen wirken erst nach der vollständigen Umstellung.
- Die Mediathek verlangt bei aktiviertem Funktionsmodell sowohl die jeweilige
  Modulaktion der Dateiverwaltung als auch das entsprechende Ordnerrecht.
  Nur Erstellen ermöglicht das Anlegen untergeordneter Ordner und Hochladen,
  aber kein Lesen oder Bearbeiten vorhandener Dateien. Die Kursbildverwaltung
  ist noch nicht vollständig integriert. Redaktionelle Beiträge und Seiten verlangen bei
  eingeschaltetem Modell das Website-Modulrecht Lesen, Anlegen, Bearbeiten
  beziehungsweise Löschen. Das Löschen ist bei ausgeschaltetem Modell
  nicht verfügbar; bei eingeschaltetem Modell werden Berechtigung und Version
  unter der Vereinssperre geprüft und erfolgreiche Löschungen protokolliert.
  Website-Anzeigeeinstellungen sind mit Lesen sichtbar und mit Bearbeiten
  änderbar; Schreibzugriffe prüfen die Berechtigung unter der Vereinssperre erneut;
  die Veranstaltungsverwaltung prüft Website-Rechte getrennt nach Aktion.
  Teilnehmerdaten erfordern zusätzlich Mitglieder-Leserecht; Zahlungsdaten
  und Veranstaltungsdokumente außerdem Finanz-Leserecht. Zahlungen benötigen
  zusätzlich Finanz-Anlegerecht, Dokument-Uploads Website-Anlegerecht und
  Dokument-Löschungen Website-Löschrecht. Die Auswahl und Anzeige eines
  Veranstaltungsbilds berücksichtigt Dateimodul- und Ordnerleserechte.
  Die übrigen Website- und anderen Modulpfade sind noch nicht vollständig
  umgestellt. Bestehende Pressefunktionen erhalten dadurch nicht automatisch
  Website-Modulrechte und müssen vor einer Aktivierung geprüft werden.
  Altrollen bleiben während des Übergangs getrennt gespeichert. Die additive
  Migration `Version20261115140000` wurde nach Sicherung und Restore-Test auf
  der lokalen Anwendungsdatenbank ausgeführt. Auch `Version20261115150000`
  wurde nach einem Test auf der wiederhergestellten Sicherung lokal ausgeführt.
  Sie ergänzt Dateimodulrechte aus vorhandenen Ordnerregeln und Administrator-
  funktionen, ohne ausdrücklich gespeicherte Modulrechte zu überschreiben.
  Zum Ausführungszeitpunkt waren lokal keine Funktionen oder Ordnerregeln
  vorhanden.
- Redaktionelle Seiten, Artikel und Veranstaltungsankündigungen können einer
  aktiven Sparte des Website-Vereins zugeordnet werden. Öffentliche
  Spartenbereiche zeigen ausschließlich freigegebene, fällige Inhalte;
  interne Spartenbeschreibungen bleiben unveröffentlicht. Bei Deaktivierung
  einer Sparte verschwinden ihre zugeordneten Inhalte einschließlich
  Direktzugriff und Bildern, bis sie wieder aktiviert oder anders zugeordnet
  werden. Die additive Migration `Version20261117100000` wurde zuerst auf
  einer isolierten Sicherung und anschließend auf der lokalen
  Anwendungsdatenbank ausgeführt. In weiteren Umgebungen muss die additive
  Migration vor der Bereitstellung des Codes ausgeführt werden. Nach dieser
  Schemaänderung wurde erneut eine Sicherung mit Restore-Nachweis erstellt;
  vor der späteren Rollenübernahme ist ihre Aktualität erneut zu prüfen.
- `Version20261117110000` ergänzt nullable, löschgeschützte
  Mediathek-Referenzen an Kursen und Terminen. Die Migration wurde auf einer
  isolierten Sicherung getestet und anschließend lokal angewendet. Zugeordnete
  Mediendateien zählen als verwendet und können nicht gelöscht werden. Bei
  aktiviertem Funktionsmodell prüft die Kursbildanzeige Dateimodul- und
  Ordnerleserecht; ohne diese Rechte wird das Bild weder angezeigt noch über
  seine direkte URL ausgeliefert. Die bisherigen Pfade bleiben bei
  deaktiviertem Modell unverändert. Für bestehende Kurse und Termine ist bei
  aktiviertem Modell eine Auswahl aus der Dateiverwaltung vorbereitet:
  erforderlich sind Ausbildungs-Bearbeitungsrecht sowie Dateimodul- und
  Ordnerleserecht für das bisherige und das gewählte Bild. Schreibzugriffe
  prüfen die Rechte nach Erwerb der Vereinssperre erneut. Die Übernahme des
  bisherigen Bildbestands und weitere Kursbildzugriffe fehlen weiterhin; das
  Modell bleibt deaktiviert.
- Die Migration `Version20261115130000` überträgt bisherige Hauptordnerrechte
  auf vorhandene Ordner der ersten Ebene und erhält dortige explizite
  Entscheidungen. Eigene Regeln tieferer Ordner blockieren die Migration:
  Sie müssen vorab fachlich geklärt und ausdrücklich auf die erste Ebene
  übertragen werden. Die Migration wurde nach Sicherung und Restore-Test auf
  der lokalen Anwendungsdatenbank ausgeführt. Für weitere Umgebungen sind
  Backup, Restore-Test und eine Wartung ohne parallele Schreibzugriffe nötig.
- `app:functions:migrate-roles --confirm` übernimmt direkte Funktionsrollen
  und ihre individuellen Zeiträume transaktional in Funktionszuordnungen.
  Bei aktiviertem Modell wirken direkte Funktionsrollen nicht mehr; die
  direkte Superadministrator-Notfallrolle bleibt bestehen. Das Kommando
  setzt das aktivierte Funktionsmodell voraus, prüft den Bestand erneut und
  bricht bei unklaren oder widersprüchlichen Daten ab. Es wurde in isolierten
  SQLite- und MariaDB-Tests sowie auf der wiederhergestellten lokalen
  Sicherung ausgeführt, nicht auf der Anwendungsdatenbank. Auf der
  Sicherung wurden alle 23 direkten Funktionsrollen einschließlich ihrer
  gespeicherten Zeiträume sowie die beiden Superadmin-Notfallrollen verglichen.
  Die Zuordnung der bisherigen Presse- und Administratorrechte zu allen
  vorhandenen Ordnern der ersten Ebene ist vor der Übernahme fachlich zu prüfen;
  Dateien im Hauptordner bleiben nach Aktivierung nur Administratoren zugänglich. Bereits
  vorbereitete Funktionen mit widersprüchlichen Namen oder Rechten können
  die Übernahme blockieren und müssen vorher geprüft werden.
  Für die ausdrücklich bestätigte Zusammenführung der bestehenden Funktion
  „Ausbildungsleiter“ gibt es `--merge-training-manager`: Ihre vier
  Ausbildungs-Modulrechte bleiben erhalten, die bisherige Rolle wird
  ergänzt und die Nachfolgepflicht eingeschaltet. Das Kommando bricht ab,
  wenn die geprüfte Funktion zwischenzeitlich geändert oder bereits jemand
  zugeordnet wurde. Auf einer am 15. November 2026 wiederhergestellten
  Datenbankkopie wurden mit dieser Option alle 23 Rollenzuordnungen und
  Zeiträume sowie beide Superadminrollen verglichen. Die Anwendungsdatenbank
  wurde dabei nicht geändert. Diese Option ersetzt nicht die übrigen Freigaben.
- `app:functions:migrate-roles --dry-run` führt denselben Übernahmeablauf
  auch bei deaktiviertem Modell aus und rollt Datenänderungen zurück.
  Der Probelauf ersetzt weder die Übernahme noch den Vergleich nach der
  Umstellung. Da Datenbanksequenzen und Sperren betroffen sein können, nur
  auf einer isolierten, wiederhergestellten Sicherung ausführen. Übernahme
  und Probelauf benötigen eine eigene Transaktion; unter MySQL/MariaDB
  müssen die beteiligten Tabellen InnoDB verwenden.
- Nach gesicherter und geprüfter Übertragung des einzigen fremd zugeordneten
  Turniers wurde der zentrale Vereinskontext auf einen Verein umgestellt. Die
  vollständige Prüfung und Anpassung der früheren Mehrvereinstests und aller
  betroffenen Oberflächen steht aus. Die Umstellung des Funktionsmodells ist
  noch offen; die bisherigen Nachfolgeregeln gelten bis dahin unverändert.
- `app:functions:check-migration` prüft gespeicherte Rollenlisten und
  Gültigkeitszeiträume schreibgeschützt auf bekannte Rollen, doppelte
  Listeneinträge, ungültige Zuordnungen und Zeiträume. Die lokale
  DDEV-Prüfung vom 15. November 2026 ergab 17 Konten, davon eines ohne
  Mitgliedschaft und zwei mit Superadmin-Notfallrolle, 23
  Funktionsrollenzuordnungen und einen gespeicherten Gültigkeitszeitraum;
  sie meldete keine der geprüften Fehler.
  Sie ist keine Migration oder Freigabe: doppelte JSON-Objektschlüssel und
  parallele Datenänderungen werden nicht geprüft; unbekannte Bestandsszenarien,
  Migrationssperre, Backup/Restore und Einzelvergleich bleiben nachzuweisen.
  Das nicht zugeordnete Konto trägt weder eine Funktionsrolle noch die
  Superadministrator-Notfallrolle.
- Die Mediathek-HTTP-Suite wurde an den Einzelvereinsbetrieb angepasst und
  bestand isoliert in DDEV mit 11 Tests und 371 Assertions. Sie prüft die
  bestehende Mediathek, nicht den noch fehlenden gemeinsamen Speicher oder
  Ordnerrechte.
- Die Veranstaltungs-HTTP-Suite prüft im Einzelvereinsbetrieb auch die
  Vereinsgrenze für Superadministratoren und bestand in DDEV mit 16 Tests
  und 162 Assertions. Bestehende Gastanmeldungen bleiben nach Deaktivierung
  des Website-Vereins einsehbar und stornierbar. Der Lauf ist kein Nachweis
  für die übrigen Module oder die Aktivierung des Funktionsmodells.
- Die bestehende Dateiverwaltung und der schreibgeschützte Kursbild-Explorer
  sind über eine gemeinsame Seite erreichbar. Beide Bestände werden dort
  hierarchisch mit ihren Dateien dargestellt; Kursbilder sind dort weiterhin
  nur lesbar und nur für Administratoren sichtbar.
  Medienordner können umbenannt, verschoben und leer gelöscht werden.
  Die Bestände liegen jetzt unter `files/kurse` und `files/website_media`.
  Ihre Ordner- und Metadatenverwaltung ist noch getrennt; die neuen
  Ordnerrechte gelten bislang nur für die Mediathek.
- `app:files:check-migration` inventarisiert Kursbilder, Mediathek und
  separat geschützte Veranstaltungsdokumente schreibgeschützt. Nach der
  jeweiligen Speicherumstellung prüft es den aktiven Bestand unter `files/`
  statt der historischen Quelldateien. Es meldet
  fehlende oder ungültige Referenzen, abweichende Dateigrößen, gemeinsam
  referenzierte Mediendateien und symbolische Links. Nicht referenzierte
  Dateien werden mitgezählt und dürfen bei der Übernahme nicht verloren
  gehen. Im aktuellen lokalen DDEV-Bestand wurden 19 Kursbilder mit drei
  Referenzen und 25 Mediendateien mit 25 Referenzen gezählt; 16 Kursbilder
  waren nicht referenziert. Die Prüfung ersetzt weder die Übernahme noch
  Inhaltsvergleich, Sicherung, Wiederherstellung und Schreibsperre.
- `app:files:stage-migration` führt die Vorprüfung aus und kopiert anschließend
  Kursbilder einschließlich nicht referenzierter Dateien nach `files/kurse`
  und Mediathek einschließlich Veranstaltungsdokumenten nach
  `files/website_media`. Es vergleicht SHA-256-Inhalte und überschreibt keine
  abweichenden Dateien. Vor der Aktivierung kann es bei unveränderten Quellen
  wiederholt werden; danach verweigert es eine erneute Übernahme.
  Das private `files/` ist gegen versehentliches Einchecken ignoriert.
  Lokal wurden so 19 Kursbilder und 25 Mediendateien kopiert. Die Mediathek
  und separat geschützte Veranstaltungsdokumente verwenden nun
  `files/website_media`. Beim ersten Zugriff wird der gesamte alte Bestand
  mit dem neuen Inhalt verglichen; fehlende oder abweichende Dateien blockieren
  die Umstellung. Die Aktivierungsmarkierung verhindert, dass eine erneute
  Übernahme später gelöschte Dateien wiederherstellt. Der alte Medienbestand
  bleibt als unveränderter Altbestand erhalten und ist nach der Aktivierung
  nicht mehr maßgeblich. Kursbilder werden aus `files/kurse` geladen; die
  Kursanzeige liefert sie über einen mitglieds- und kursgebundenen Endpunkt aus.
  EasyAdmin ersetzt oder entfernt Bildzuordnungen, ohne gemeinsam verwendete
  Bilddateien zu löschen. Die vollständige Verwaltung im Explorer fehlt.
  Der lokale öffentliche Kursbildordner wurde nach erfolgreichem
  Inhaltsvergleich und einem Restore-Test in einen privaten Altbestand
  verschoben; der frühere öffentliche Bildpfad liefert 404.
  Die Kopie ist keine Sicherung und keine Schreibsperre. Für weitere
  Installationen sind vor dem Deployment Backup und Restore zu prüfen,
  alle Schreibzugriffe und alten Anwendungsinstanzen anzuhalten, die Übernahme
  auszuführen und der Inhaltsvergleich vor Freigabe abzuwarten. Die Markierung
  ist keine verteilte Sperre. Ein Rollback erfordert einen gesonderten Abgleich
  der seit der Aktivierung geänderten Dateien und darf nicht einfach auf den
  alten Ordner zeigen.
- Für die Kursbildumstellung auf einer anderen Installation muss der alte
  öffentliche Ordner während der Wartung verfügbar bleiben: Datenbank und
  Dateien sichern und die Wiederherstellung testen, sämtliche Anwendungs-
  instanzen und Schreibzugriffe anhalten, die neue Version bereitstellen und
  gegebenenfalls den durch das Deployment entfernten alten Ordner aus der
  Sicherung für die Übernahme wiederherstellen. Anschließend
  `app:files:stage-migration --courses-only` ausführen und Inhalt und Referenzen
  prüfen. Erst danach Cache und Worker aktualisieren, den alten Ordner aus dem
  öffentlichen Webverzeichnis entfernen und den Zugriff wieder freigeben.
  Die im Repository entfernten alten Bilddateien werden nicht automatisch nach
  `files/` ausgeliefert; `files/` und seine Sicherung müssen beim Deployment
  persistent bleiben. Die Markierung ersetzt keine Schreibsperre.
- Eine erneute schreibgeschützte Doctrine-Schemaprüfung meldet eine Abweichung
  bei `tank`; die Ursache und eine geeignete Migration müssen gesondert
  untersucht werden. Das ausgegebene Schema-Update darf nicht ungeprüft
  angewendet werden.
- Eine inventarisierende Routenprüfung erfasste 665 Routen und 132 direkt
  angebundene Anwendungskontroller; 143 Controllerdateien existieren insgesamt.
  Dieses Inventar ist keine vollständige Einzelprüfung oder Freigabe.
- Die Migration für Rollengültigkeiten wurde in einer privaten MariaDB und
  anschließend in der lokalen DDEV-Datenbank ausgeführt. Vorher wurde ein
  Backup erstellt und in einer privaten MariaDB restauriert; die Prüfsummen
  aller 108 Tabellen stimmten überein.
- Am 17. November 2026 wurden während der bestätigten Schreibpause die
  Anwendungsdatenbank sowie `files/` und `var/private/` erneut geschützt
  gesichert. Eine netzwerkisolierte MariaDB stellte 111 Tabellen wieder her;
  die Datenbank-Dumps stimmen nach Entfernung von CR-Zeichen überein. Das
  Dateiarchiv wurde separat wiederhergestellt und verglichen. Die Sicherung
  ist eine Momentaufnahme, keine Abnahme der weiterhin offenen Umstellung und
  enthält vertrauliche Daten; bei späteren Änderungen ist sie zu erneuern.
- Nach der Sparten-Schemaänderung wurde am 17. November 2026 unter bestätigter
  Schreibpause erneut gesichert. Die Wiederherstellung in einer netzwerkisolierten
  MariaDB enthielt 111 Tabellen und die Spartenmigration; der normalisierte
  Dump stimmt mit der Sicherung überein. Das Dateiarchiv wurde getrennt
  einschließlich Dateimodi wiederhergestellt und verglichen. Datenbank und
  Dateispeicher stimmten unmittelbar nach der Sicherung mit dem Livebestand
  überein. Die vertrauliche Sicherung liegt geschützt im Sitzungsverzeichnis;
  unmittelbar vor einem Cutover sind Aktualität und Schreibpause erneut zu prüfen.
- Nach der Kursbild-Schemaänderung wurde am 17. November 2026 eine weitere
  Datenbank- und Dateisicherung erstellt. Die isolierte Wiederherstellung
  umfasst 111 Tabellen und beide neuen Kursbildreferenzen; der Datenbankinhalt
  stimmt nach Normalisierung von Dump-Metadaten und CR-Zeichen überein.
  Dateiinhalt, Inventar und Modi wurden verglichen; unmittelbar nach der
  Sicherung stimmten Datenbank und Dateibestand mit dem Livebestand überein.
  Ein isolierter Rollen-Probelauf mit `--merge-training-manager` prüfte 23
  Zuordnungen und rollte sie zurück. Vor einer tatsächlichen Rollenübernahme
  müssen Aktualität und Schreibpause erneut sichergestellt werden.
- Der alte TÜV-Buchungsweg und globale Altansichten wurden auf die
  vereinsbezogenen Arbeitsabläufe zurückgeführt. Zusätzliche Tests und ein
  vollständiges Regressionsergebnis sind vor der Abnahme auszuwerten.
- Ein Test mit zwei privaten MariaDB-Verbindungen prüfte die Vereinssperre bei
  konkurrierenden Statuswechseln; weitere parallele Rollen- und
  Zuordnungsänderungen sind gesondert nachzuweisen.
- Das finale Basis-Gate bestand mit 486 Tests und 11.044 Assertions in
  isoliertem SQLite; acht JavaScript-Tests bestanden. Der lokale
  DDEV-Migrationsstand und das Doctrine-Schema waren aktuell. Die vollständige
  Migrationssuite bestand in privater MariaDB mit 39 Tests und 327 Assertions.
  Eine erneute Staging-HTTP-Abnahme auf dem aktuellen Stand steht noch aus.
- Vorstands-E-Mails werden nur vorbereitet. Täglicher Job mit `--send`,
  gültiger Absender, Versandüberwachung, Wiederholung fehlgeschlagener Mails
  und tatsächlich rechtzeitige Zustellung sind vor der Freigabe nachzuweisen.
  Die Ereignisliste zeigt den historischen Stand, nicht automatisch den
  aktuellen Nachbesetzungsstatus.
- Ein vollständiger Nachweis für alle Rollen, Controller und vererbten
  Berechtigungen sowie für konkurrierende Rollen- und Mitgliedschaftsänderungen
  steht noch aus. Ein automatisches Test-Gate ersetzt die fachliche
  Entscheidung nicht.
