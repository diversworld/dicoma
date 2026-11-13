# Vorbereitung der fachlichen Gesamtfreigabe für Phase 3

## Entscheidungsstand

Eine Gesamt- oder Produktivfreigabe ist noch nicht erteilt. Die fachliche
Freigabe muss durch die zuständige Person erfolgen. Die folgenden Punkte
unterscheiden vorbereitete Nachweise von noch offenen Entscheidungen und
Prüfungen.

## Vereinbarte Rollenregeln

- Der zentrale Kontext ist auf einen Verein umgestellt. Noch nicht umgesetzt
  sind frei anlegbare Funktionen mit ausdrücklicher Berechtigungszuordnung
  und einstellbarer Nachfolgepflicht. Administratoren dürfen nur delegierbare
  Rechte zuordnen, privilegierte Rechte bleiben Superadministratoren
  vorbehalten.
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
- [ ] Die gewünschte Zusammenführung von Kursbildern und Mediathek in einen
      gemeinsamen Speicher ist noch umzusetzen. Vor der Übernahme sind
      Dateibestand, Dateitypen, fehlende Referenzen, Mehrfachverwendungen und
      nicht referenzierte Dateien zu erfassen; anschließend sind Inhalte,
      Referenzen und Wiederherstellung zu vergleichen.
- [ ] Nach der Zusammenführung sind Kurse und Termine samt Bildanzeige,
      Upload, Ordnern, Umbenennen, Verschieben, Löschen und Verwendungsschutz
      über den Datei-Explorer zu prüfen. Öffentliche Bildauslieferung darf
      geschützte Dateien nicht allgemein verfügbar machen; bestehende Rechte
      der Redaktion dürfen nicht unbeabsichtigt entfallen.
- [ ] Der gemeinsame private Speicher soll unter `files/` außerhalb des
      Webverzeichnisses liegen. Lesen, Hochladen, Bearbeiten und Löschen
      werden getrennt ausschließlich Vereinsfunktionen zugewiesen; auch
      Administratoren dürfen diese vier Rechte zuordnen. Unterordner erben
      die Regeln, können sie aber je Funktion überschreiben. Bei mehreren
      Funktionen genügt eine erlaubende Funktion; eine Sperre einer Funktion
      hebt die Erlaubnis einer anderen nicht auf. Direkte HTTP- und
      Backend-Zugriffe müssen dieselben Regeln durchsetzen.
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

- Nach gesicherter und geprüfter Übertragung des einzigen fremd zugeordneten
  Turniers wurde der zentrale Vereinskontext auf einen Verein umgestellt. Die
  vollständige Prüfung und Anpassung der früheren Mehrvereinstests und aller
  betroffenen Oberflächen steht aus. Die Umstellung des Funktionsmodells ist
  noch offen; die bisherigen Nachfolgeregeln gelten bis dahin unverändert.
- `app:functions:check-migration` prüft gespeicherte Rollenlisten und
  Gültigkeitszeiträume schreibgeschützt auf bekannte Rollen, doppelte
  Listeneinträge, ungültige Zuordnungen und Zeiträume. Die lokale
  DDEV-Prüfung ergab 17 Konten, davon eines ohne Mitgliedschaft und zwei mit
  Superadmin-Notfallrolle, 23 Funktionsrollenzuordnungen und keine
  gespeicherten Gültigkeitszeiträume; sie meldete keine der geprüften Fehler.
  Sie ist keine Migration oder Freigabe: doppelte JSON-Objektschlüssel und
  parallele Datenänderungen werden nicht geprüft; unbekannte Bestandsszenarien,
  Migrationssperre, Backup/Restore und Einzelvergleich bleiben nachzuweisen.
- Die Mediathek-HTTP-Suite wurde an den Einzelvereinsbetrieb angepasst und
  bestand isoliert in DDEV mit 11 Tests und 371 Assertions. Sie prüft die
  bestehende Mediathek, nicht den noch fehlenden gemeinsamen Speicher oder
  Ordnerrechte.
- Die bestehende Dateiverwaltung und der schreibgeschützte Kursbild-Explorer
  sind über eine gemeinsame Seite erreichbar. Beide Bestände werden dort
  hierarchisch mit ihren Dateien dargestellt; Kursbilder sind weiterhin als
  getrennter Altbestand gekennzeichnet und nur für Administratoren sichtbar.
  Medienordner können umbenannt, verschoben und leer gelöscht werden.
  Die beiden physischen Speicher und die bisherige rollenbasierte
  Zugriffskontrolle sind dadurch **nicht** zusammengeführt oder durch die
  vereinbarten Ordnerrechte ersetzt.
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
