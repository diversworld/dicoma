# Patch-Inhalt und Anwendung

Dieses Overlay ergänzt Phase 1 und ausgewählte, überprüfbare Grundlagen aus Phase 2. Es ist **kein** behaupteter Komplettausbau aller geforderten Fachmodule.

Enthalten:
- `docs/repository-analysis.md`: Analyse des gelieferten Referenzarchivs mit Grenzen der Nachweisbarkeit.
- `docs/migration-mapping.md`: fachliches Mapping zu den Ziel-Entities und Migrationsregeln.
- `docs/architecture.md`: Schichten, Statusdimensionen und Mermaid-ERD.
- `docs/implementation-plan.md`: Arbeitspakete und Abnahmekriterien.
- `src/DiveClub/Shared/Domain/TimeRange.php`: getestetes Value Object für halb offene Zeitintervalle und Puffer.
- `tests/Unit/DiveClub/Shared/Domain/TimeRangeTest.php`: Grenzfalltests.
- `docker-compose.diveclub.yml`: isolierte PostgreSQL-16- und optionale Mailpit-Entwicklungsdienste.

Anwendung aus dem entpackten Patchverzeichnis im Projektstamm von `dicoma1510`:

```bash
cp -R docs/. /pfad/zu/dicoma1510/docs/
mkdir -p /pfad/zu/dicoma1510/src/DiveClub/Shared/Domain
cp src/DiveClub/Shared/Domain/TimeRange.php /pfad/zu/dicoma1510/src/DiveClub/Shared/Domain/
mkdir -p /pfad/zu/dicoma1510/tests/Unit/DiveClub/Shared/Domain
cp tests/Unit/DiveClub/Shared/Domain/TimeRangeTest.php /pfad/zu/dicoma1510/tests/Unit/DiveClub/Shared/Domain/
cp docker-compose.diveclub.yml /pfad/zu/dicoma1510/
```

Die Compose-Datei ist bewusst separat, damit bestehende lokale DDEV-/Docker-Workflows nicht unaufgefordert überschrieben werden. Vor dem Start `POSTGRES_PASSWORD` als Umgebungsvariable oder in einer nicht versionierten `.env.local` setzen. Für Symfony anschließend `DATABASE_URL` auf PostgreSQL umstellen, sobald die Zielumgebung dafür bereit ist.

Tests aus dem Projektstamm ausführen:

```bash
php bin/phpunit tests/Unit/DiveClub/Shared/Domain/TimeRangeTest.php
```

Noch nicht enthalten sind Doctrine-Entities/Migrationen für alle Fachbereiche, Workflow-/Messenger-Bundle-Konfiguration, Bootstrap/Stimulus-Paketinstallation, PDF-/XLSX-Generatoren oder die vollständigen CRUD-Prozesse. Diese benötigen weitere kleine, getestete Arbeitspakete und fachliche Entscheidungen.


## Ergänzung nach fachlicher Rückmeldung

Bestätigt am 15.10.2026: Rechnungen ohne Umsatzsteuerausweis gemäß Vereinsvorgabe; Kapazität wird je Prüfstelle und Prüftermin individuell gepflegt; erfolgreiche Flaschenprüfungen erhalten ein Intervall von 2,5 Jahren (= 30 Kalendermonate). Ergänzt wurden `InspectionIntervalPolicy`, `ClubPricingPolicy` sowie Unit-Tests. Die steuerrechtliche Zulässigkeit des fehlenden Umsatzsteuerausweises wird durch Software nicht beurteilt und muss vor Produktivbetrieb bestätigt werden.
