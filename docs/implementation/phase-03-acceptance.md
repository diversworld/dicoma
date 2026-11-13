# Vorbereitung der fachlichen Gesamtfreigabe für Phase 3

## Entscheidungsstand

Eine Gesamt- oder Produktivfreigabe ist noch nicht erteilt. Die fachliche
Freigabe muss durch die zuständige Person erfolgen. Die folgenden Punkte
unterscheiden vorbereitete Nachweise von noch offenen Entscheidungen und
Prüfungen.

## Vereinbarte Rollenregeln

- Neu beschlossen, aber noch nicht umgesetzt: Es wird nur ein Verein
  verwaltet. Funktionen sollen frei angelegt und mit vorhandenen Berechtigungen
  verknüpft werden können. Administratoren dürfen nur delegierbare Rechte
  zuordnen, privilegierte Rechte bleiben Superadministratoren vorbehalten.
  Die Nachfolgepflicht soll je Funktion einstellbar sein.
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
