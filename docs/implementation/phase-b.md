# Phase B – Beitragsperioden, Kommunikation, Mitgliederprofil und Qualität

## Beitragsperioden

Beitragsklassen unterstützen zusätzlich:

- jährliche oder monatliche Abrechnung,
- Monatsbetrag,
- Kategorie `standard`, `youth`, `honorary`, `supporter` oder `family`.

Die bestehende Jahresabrechnung bleibt unverändert und verarbeitet nur Jahresklassen. Monatsklassen werden idempotent erzeugt:

```bash
php bin/console app:membership:monthly-billing \
  --period=2026-11 \
  --due=2026-11-30 \
  --confirm
```

Schlüssel `monthly:<member-id>:YYYY-MM` verhindert doppelte Forderungen. Jahresermäßigungen werden bei Monatsklassen anteilig auf zwölf Monate verteilt.

## Gruppenkommunikation

`/admin/communication` bietet:

- Vorlagenverwaltung,
- Vorschau der Empfängerzahl,
- Testversand an die eigene E-Mail-Adresse,
- Versand an alle aktiven Mitglieder, Vorstand, Jugend oder Ehrenmitglieder,
- personalisierte E-Mail-Platzhalter,
- Audit-Ereignis mit Empfängeranzahl.

Platzhalter: `{{ firstname }}`, `{{ lastname }}`, `{{ memberNumber }}`, `{{ club }}`.

Jede Kampagne erzeugt zusätzlich interne Nachrichten und nutzt den vorhandenen E-Mail-Outbox-Pfad. Division-/Rollenkommunikation bleibt im Mitglieder-Posteingang verfügbar.

## Mitgliederprofil und Historie

`Person` enthält optionales Geschlecht und einen privaten Profilbild-Speicherverweis. Bilder werden ausschließlich über eine authentifizierte Route geliefert.

`MembershipHistory` erfasst strukturierte Änderungen mit Akteur, Aktion, altem und neuem Wert. Verwaltungsänderungen und Selbstservice-Kontaktänderungen werden dort abgelegt. Unveränderte Felder werden nicht protokolliert.

Der Mitgliederdatenexport enthält die neuen Profilfelder. Der Datenschutzbereinigungslauf entfernt Profilbild und Geschlecht zusammen mit anderen personenbezogenen Daten.

## WCAG und Usability

Neu abgesichert:

- Skip-Link und fokussierbarer Hauptinhalt in öffentlich und Admin,
- sichtbarer Fokus mit erhöhtem Kontrast,
- Labelprüfung für Phase-B-Formulare,
- Landmark-/Titel-/Alt-Text-Prüfungen für öffentliche, Mitglieder- und Admin-Seiten,
- semantisch beschriftete Statusmeldungen.

Die automatisierten Checks sind eine Regression-Baseline und ersetzen keine vollständige manuelle WCAG-2.2-AA-Prüfung.

## Performance und Cache

Öffentliche Menüdefinitionen werden 60 Sekunden im Symfony-App-Cache gehalten. Personen-, Finance- und Kommunikations-Listen wurden mit gezielten Datenbankindizes ergänzt:

- Beitragsklasse nach Verein/Aktiv/Rhythmus,
- Forderung nach Mitglied/Fälligkeit,
- interne Nachricht nach Verein/Empfängergruppe/Zeit.

Lasttests unterstützen jetzt auch authentifizierte Pfade:

```bash
DICOMA_LOAD_COOKIE='PHPSESSID=...' \
DICOMA_LOAD_PATHS='/admin /admin/membership-finance /my' \
REQUESTS=100 CONCURRENCY=10 \
MAX_P95_MS=3000 \
scripts/phase-a-load-test.sh https://staging.example
```

Cookies dürfen nur in der lokalen Testumgebung eingesetzt werden und nicht in Logs oder Versionsverwaltung gelangen.
