# DICOMA – Phase 2: Abschluss- und Abnahme-Gate

Dieses Paket ist **kein Beleg für eine Produktivfreigabe**. Es ergänzt die Schritte 01–100 um reproduzierbare Prüfungen und einen Freigabeprozess. Es enthält keine Datenbankmigration und führt keine produktiven Änderungen aus.

## Installation

Die beiden Dateien `tests/security/phase2_route_gate.php` und `scripts/phase2_acceptance_gate.sh` in die entsprechenden Projektverzeichnisse kopieren. Die PHP-Routenprüfung kann ohne Composer ausgeführt werden:

```bash
php tests/security/phase2_route_gate.php
```

Auf einer **Staging-Installation** mit Composer-Abhängigkeiten:

```bash
bash scripts/phase2_acceptance_gate.sh
```

Optionale **lesende** Schema-/Migrationsprüfungen nur mit ausdrücklich auf eine Staging-Datenbank zeigender Testumgebung:

```bash
APP_ENV=test DICOMA_RUN_STAGING_DB_CHECKS=1 bash scripts/phase2_acceptance_gate.sh
```

**Achtung:** `APP_ENV=test` allein garantiert nicht, dass `DATABASE_URL` auf eine Testdatenbank zeigt. Vorher Verbindung und Datenbankname unabhängig kontrollieren. Die PHPUnit-Suite kann Schreiboperationen ausführen und darf niemals gegen die Produktivdatenbank laufen.

## Freigabekriterien (alle zwingend)

- [ ] Alle bisherigen Patches im korrekten Versionsstand installiert; keine Patchkonflikte.
- [ ] Composer-Abhängigkeiten und Symfony-Container auf Staging erfolgreich.
- [ ] PHPUnit-Suite auf Staging erfolgreich.
- [ ] Doctrine-Mapping und Schema auf Staging aktuell, keine offenen Migrationen.
- [ ] HTTP-Rollentests: anonym / `ROLE_ADMIN` Verein A / `ROLE_ADMIN` Verein B / `ROLE_SUPER_ADMIN`.
- [ ] Direkte CRUD-URLs, Form-Manipulationen, Downloads und schreibende Aktionen mit fremden Vereins-IDs getestet.
- [ ] Registrierung, Passwort-Reset, Verifizierung, Installation, Kurs, Mitglied, TÜV, Kasse, Ausrüstung regressionsgetestet.
- [ ] Fachliche Freigabe der `ROLE_SUPER_ADMIN`-Sperren aus 41–100 (reguläre Admins verlieren Funktionen).
- [ ] Backup und Wiederherstellung tatsächlich getestet; Rollback-/Wartungsfenster geplant.
- [ ] Freigabe dokumentiert (Verantwortliche, Datum, Version, Testergebnisse).

## Grenzen

Die neue PHP-Routenprüfung ist eine **statische Quellcodeprüfung** für die 30 Endpunkte aus 71–100. Sie beweist keine tatsächliche HTTP-Autorisierung, keinen CSRF-Schutz und keine Mandantentrennung. `debug:router` gibt Routen aus, testet sie aber nicht. Das Shell-Gate führt bei fehlenden Abhängigkeiten zu einem Blocker statt zu einem grünen Scheinresultat. Die Fachfreigabe kann nicht automatisiert werden.

Referenzen: https://symfony.com/doc/current/testing.html · https://symfony.com/doc/current/security.html · https://symfony.com/doc/current/doctrine.html
