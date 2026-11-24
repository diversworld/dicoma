# Phase A – Sicherheit, Finanzen und Compliance

## Rollen und Berechtigungen

Die transitive Vererbung wurde entfernt. Vorstand, Kassenwart, Schriftführung und Administrator erhalten keine impliziten operativen Rollen mehr voneinander. Die fachlichen Fähigkeiten werden über `ModuleAccess` und den gewählten Verein bewertet.

Kernregeln:

- Vorstand: Mitglieder/Governance schreiben, Finanzen nur lesen.
- Kassenwart: Finanzen/Buchhaltung, keine Mitgliederadministration.
- Schriftführung: Governance, aber keine Finanztransaktionen.
- Administrator: alle vereinsbezogenen Module über Modulberechtigung, ohne dadurch als Kassenwart eingeloggt zu erscheinen.
- Superadministrator: technische Gesamtadministration.

Die HTTP-Regressionsprüfung befindet sich in `tests/Integration/PhaseARoleBoundaryHttpTest.php`.

## SEPA und pain.008

Ein Lastschriftlauf wird in drei Zuständen verarbeitet:

1. Entwurf mit unveränderbaren Forderungs-/Mandats-Snapshots
2. Freigabe durch eine zweite Finanzperson
3. idempotenter `pain.008.001.02`-Export

Der Export:

- erfordert vollständig konfigurierte Gläubigerdaten,
- entschlüsselt Bankdaten nur während der Erzeugung,
- speichert keinen Klartext im Audit-Kontext,
- ändert Forderungs- oder Zahlungsstatus nicht,
- liefert einen SHA-256-Prüfwert im Header und Datenmodell.

BIC wird wie IBAN verschlüsselt gespeichert. Das gemeinsame Bankmodell besitzt weiterhin keinen automatischen Zahlungsabgleich; bestätigte Rückgaben werden über den vorhandenen Zahlungsworkflow separat gebucht.

Die Oberfläche verarbeitet fachliche SEPA-Fehler als Flash-Meldung und verhindert,
dass dieselbe Forderung gleichzeitig in mehreren Entwurfs-, Freigabe- oder
Exportläufen reserviert wird. Das früheste Einzugsdatum wird anhand von zwei
Werktagen berechnet.

## Buchhaltung

`FinanceAccount` und `FinanceTransaction` bilden ein vereinsbezogenes, ausgeglichenes Journal. Jede Buchung benötigt Soll- und Habenkonto, positiven Cent-Betrag, Datum, Referenz und Akteur. Abgeschlossene Jahre lehnen nachträgliche Buchungen ab.

Korrekturen erfolgen durch Gegenbuchung und nicht durch Bearbeitung bestehender Datensätze. `FinanceAnnualClose` speichert Einnahmen, Ausgaben, Kontensalden, SHA-256 und abschließende Person.

## Mahnwesen

Mahnintervalle und die vier Vorlagen werden in den Mitgliedschaftsregeln gepflegt. Unterstützte Platzhalter:

- `{{ description }}`
- `{{ amount }}`
- `{{ dueDate }}`
- `{{ invoice }}`
- `{{ stage }}`

Die CLI verwendet ohne Optionen die Vereinsrichtlinie:

```bash
php bin/console app:membership:queue-dunning
```

Legacy-Optionen `--minimum-days` und `--repeat-days` bleiben für kontrollierte Migrationen gültig.

## Audit, Auskunft und Aufbewahrung

`AuditEvent` protokolliert sensible Fachvorgänge mit Akteur, Aktion, Objekttyp/-ID, minimiertem Kontext, Zeitpunkt und Ablaufdatum. Bekannte Geheimnisfelder wie IBAN, BIC, Token, Passwort und Schlüssel werden im Kontext ersetzt.

Mitglieder können unter `/my/data-export` ihre Daten als privaten JSON-Download abrufen. Die Datenschutzseite unter `/admin/privacy` verwaltet:

- Mitgliedsaufbewahrung
- Nachrichtenaufbewahrung
- Finanzaufbewahrung
- Auditaufbewahrung
- Freigabe der automatischen Bereinigung

Dry-Run:

```bash
php bin/console app:privacy:retention
```

Ausführung nach Freigabe:

```bash
php bin/console app:privacy:retention --apply --limit 250
```

Vor erster produktiver Ausführung sind die gesetzlich/fachlich festgelegten Fristen zu bestätigen. Ohne gespeicherte Richtlinie oder ohne explizite Freigabe wird nichts bereinigt. Der Befehl begrenzt jede Kategorie pro Lauf, filtert berechtigte Datensätze bereits in der Abfrage und schützt Mitgliederdaten, solange jüngere Finanzvorgänge oder eine zweite Vereinsmitgliedschaft bestehen.

## WCAG-Gate

Die automatisierte Basisprüfung deckt:

- Sprachangabe und Seitentitel
- Skip-Link und Hauptinhaltsziel
- Alt-Attribute
- Labels für Formulareingaben

Die Prüfung ersetzt keine vollständige WCAG-2.2-AA-Expertise, sondern verhindert Regressionen in zentralen Strukturen. Tastatur-Fokus wurde im öffentlichen und administrativen Design sichtbar vereinheitlicht.

Ausführung:

```bash
php bin/phpunit tests/Integration/AccessibilitySmokeTest.php
```

## Lasttest

Der reproduzierbare DDEV-/Staging-Test misst Statusfehler und p95-Latenz:

```bash
REQUESTS=100 CONCURRENCY=10 MAX_P95_MS=3000 scripts/phase-a-load-test.sh https://dicoma.ddev.site
```

Der Test erzeugt ausschließlich GET-Anfragen auf Start- und Login-Seite, verändert keine Daten und bricht bei HTTP-Fehlern oder Schwellenüberschreitung ab.
