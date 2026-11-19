# DICOMA – Phase 10 – Abschlussbericht

## 1. Status

**Technisch abgeschlossen; Produktiv- und SEPA-Betriebsabnahme erforderlich.**

Die vorhandenen Beitragsklassen, Zahlungen, Familienzahler, Ermäßigungen,
verschlüsselten SEPA-Mandate und privaten Mitgliedsdokumente wurden erhalten.
Spartenzuschläge, kontrollierte Jahresläufe, Rechnungsstand, Mahnungen und
Finanzexporte wurden ergänzt.

## 2. Implementierte Änderungen

- `DivisionContribution`: aktiver Spartenzuschlag je Sparte und Jahr.
- Idempotenter Jahresbeitragslauf mit Vorschau und expliziter `--confirm`-
  Bestätigung.
- Familienzahler werden nicht doppelt belastet; bestehende Beitragsregeln
  bleiben maßgeblich.
- Jede erzeugte Forderung erhält eine unveränderliche interne Rechnungsnummer.
- Lauf speichert Anzahl und Gesamtsumme; ein Jahr pro Verein wird nur einmal
  verarbeitet.
- Überfällige Forderungen können zeitabstandsbegrenzt in der sicheren
  Mitglieds-Outbox gemahnt werden.
- Finanzexport als CSV verlangt neue Zieldatei, `--confirm` und Modus `0600`.
- Separate Finanzansicht für Kassenwart-/Vorstands-Modulrecht enthält nur
  Rechnung, Mitglied, Forderung, Zahlungen und Status; keine IBAN,
  Mandatsnachweise oder allgemeinen Mitgliedsstammdaten.

## 3. Geänderte Dateien

- `src/Entity/DivisionContribution.php`
- `src/Entity/MembershipBillingRun.php`
- `src/Entity/MembershipCharge.php`
- `src/Service/MembershipBillingService.php`
- `src/Command/MembershipBillingCommand.php`
- `src/Command/MembershipDunningCommand.php`
- `src/Command/ExportMembershipFinanceCommand.php`
- `src/Controller/Admin/MembershipFinanceController.php`
- `src/Controller/Admin/DivisionContributionCrudController.php`
- `templates/admin/membership/finance.html.twig`
- `migrations/Version20261119160000.php`

## 4. Datenbank

Neu:

- `division_contribution`
- `membership_billing_run`

Erweitert:

- `membership_charge` um Rechnungsnummer, Rechnungszeitpunkt,
  Mahnzeitpunkt und Mahnanzahl.

Die Migration wurde in isolierter MariaDB vorwärts, rückwärts und erneut
vorwärts geprüft.

## 5. Sicherheit

- Beitragslauf und Mahnung verändern Daten nur mit expliziter Bestätigung;
  Vorschau ist schreibgeschützt.
- Wiederholter Jahreslauf wird abgewiesen.
- Exportdatei wird exklusiv neu angelegt und auf `0600` gesetzt.
- Finanzansicht verlangt Finance-Modul-Leserecht und gleiche Vereinszuordnung.
- Normale Mitglieder erhalten 403.
- IBAN bleibt verschlüsselt und wird in der Finanzansicht nicht offengelegt.
- SEPA-Mandat bleibt Verwaltungsvorbereitung. Es wird kein Bankeinzug,
  Sepa-XML oder Lastschriftauftrag ausgelöst.
- Bestehende Mandatsbeweise und Mitgliedsdokumente bleiben in den bestehenden
  privaten, personenbezogenen Zugriffswegen.

## 6. Befehle und Inbetriebnahme

```bash
php bin/console app:membership:billing-run --year 2026 --due 2026-01-31 --dry-run
php bin/console app:membership:billing-run --year 2026 --due 2026-01-31 --confirm
php bin/console app:membership:queue-dunning --minimum-days 14 --repeat-days 30
php bin/console app:membership:export-finance --year 2026 --output var/private/membership-finance.csv --confirm
```

Mahnungen werden ausschließlich vorbereitet. Der bestehende Mailversand
benötigt weiterhin gültigen Transport, Absender, Monitoring und explizites
`app:membership:daily --send`.

## 7. Tests

- Laufvorschau, Spartenzuschlag, Rechnungsnummern und Doppel-Lauf: bestanden.
- Mahnungs-Deduplizierung und frische Forderungen: bestanden.
- Vertraulicher Export, Bestätigungspflicht und Dateimodus: bestanden.
- Finanzzugriff Kassenwart erlaubt, normales Mitglied 403: bestanden.
- Phase-10-Neutests: 4 Tests, 24 Assertions.
- Symfony-Container und Twig-Lint bestanden.

Bestehende Mitgliedschaftsfinanztests bleiben für manuelle Zahlungen, Mandate
und Familienbeiträge die Regressionsebene.

## 8. Bekannte Einschränkungen

- Rechnungsnummern und Status sind implementiert; PDF-/Mailrechnung ist noch
  kein Bestandteil der Phase.
- Die Mahnung ist textbasiert und nicht als juristisch gestaltetes
  Mahnschreiben modelliert.
- Bankeinzug, SEPA-XML, Bankabgleich, Rücklastschriften und Erstattungen sind
  bewusst gesperrt, bis Gläubiger-ID, Bankprozess, Vorabinformation,
  Ausführungsgenehmigung und Fachabnahme vorhanden sind.
- Ein Kontenimport oder Zahlungsabgleich mit Banken ist nicht implementiert.
- Spartenzuschläge werden bei aktiven `MemberSport`-Zuordnungen berechnet.

## 9. Abnahmekriterien

- Beitragsklassen: bestehend, regressionsgeprüft.
- Spartenzuschläge: erfüllt.
- Wiederkehrende Beitragsläufe: erfüllt mit Vorschau/Bestätigung/Idempotenz.
- Rechnungen und Zahlungseingänge: Rechnungsreferenz/Status ergänzt;
  Zahlungseingang bleibt bestehend, PDF-Rechnung nicht enthalten.
- SEPA: sichere Mandatsverwaltung erfüllt, operativer Einzug bewusst nicht
  freigegeben.
- Offene Forderungen und Mahnungen: erfüllt.
- Dokumentenverwaltung: bestehend und unverändert geschützt.
- Export/Auswertung: Finance-CSV und gesicherte Finanzansicht ergänzt.

## 10. Nächster Schritt

Fachliche, rechtliche und bankseitige Abnahme des Beitrags-/SEPA-Prozesses
durchführen. Phase 11 wird nicht automatisch begonnen.
