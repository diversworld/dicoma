# Phase C – Komfort und Betriebswerkzeuge

## Jahresberichte und Exportpakete

Die Vorstandsseite bietet für abgeschlossene Jahre ein privates ZIP-Exportpaket mit:

- maschinenlesbarem Jahresbericht (`jahresbericht.json`)
- Mitglieder-Snapshot (`mitglieder.csv`)
- Zahlungseingängen des Jahres (`zahlungen.csv`)

Der Download wird auf den gewählten Verein begrenzt, mit `private, no-store`
ausgeliefert und auditiert. Temporäre Dateien werden nach dem Versand gelöscht.

## Vorstands-Digest

```bash
php bin/console app:board:digest --days=14 --dry-run
php bin/console app:board:digest --days=14 --confirm
```

Der Digest enthält kommende Vereinsveranstaltungen und Vorstandssitzungen,
ausstehende Protokolle, offene Aufgaben und offene Beschlüsse. Nachrichten werden
idempotent in der bestehenden E-Mail-Outbox eingereiht und anschließend mit
`app:membership:daily --send` versendet. Abgelaufene Vorstandsrollen werden nicht
angeschrieben.

## Dashboard-Reihenfolge

Jede angemeldete Verwaltungsrolle kann die sichtbaren Vorstands- und Finanz-KPIs
über Positionsnummern sortieren. Die vollständige Reihenfolge wird pro Benutzer
gespeichert und beim nächsten Dashboard-Aufruf angewendet.

## Mitglieder-Bulkimport

Pflichtspalten sind `firstname`, `lastname` und `memberNumber`. Optional sind
`email`, `birthday`, `gender`, `joinedAt`, `status`, `membershipType`, `mobile`,
`phone`, `street`, `postalCode` und `city`.

```bash
php bin/console app:membership:import-members /pfad/mitglieder.csv --dry-run
php bin/console app:membership:import-members /pfad/mitglieder.csv --confirm
```

Komma und Semikolon werden als Trenner akzeptiert. Datumswerte verwenden
`YYYY-MM-DD`. Dubletten innerhalb der Datei sowie vorhandene Mitgliedsnummern
oder E-Mail-Adressen werden gemeldet. Validierungsfehler brechen den gesamten
Import ab; der Schreiblauf ist transaktional.

## Externe Produktivfreigaben

Bankprofil, Steuerberatung, Aufbewahrungsfristen und manuelle WCAG-Abnahme werden
nicht simuliert. Unter **Audit & Datenschutz** werden prüfende Person und
Nachweisreferenz dokumentiert. Kritische Aktionen prüfen diese Freigaben in der
Produktivumgebung.

Die Malwareprüfung ist in DDEV dauerhaft über ClamAV konfiguriert. Signaturen
werden beim Containerstart aktualisiert. Der Uploadscan ist produktiv
fail-closed: Ohne ausführbaren Scanner und aktuelle Datenbank wird kein Dokument
gespeichert.
