# Phase 4: Tauchausbildung

## Einstieg und Rollen

- Das öffentliche Kursangebot ist unter `/my-training/courses` erreichbar,
  auch ohne Anmeldung. Es zeigt Kursart, Beschreibung, Voraussetzungen,
  Gebühren, Kapazität, Termine, Orte, Ausbilder und benötigte Ausrüstung.
- Gäste verwenden die bestehende Registrierung unter `/register` und
  bestätigen ihre E-Mail. Dabei entstehen ausschließlich `User` und `Person`,
  **kein künstliches Vereinsmitglied**.
- Das Ausbildungsportal unter `/training` zeigt Tauchschülern ihre eigenen
  Teilnahmen, Ausbildungsfortschritt, Anwesenheiten und Bescheinigungen.
- `ROLE_INSTRUCTOR`: eigene Kurse und zugeordnete Termine, Anwesenheiten und
  Bewertungen; ein verantwortlicher Kursausbilder kann Kurstermine bewerten.
- `ROLE_TRAINING_MANAGER`: Ausbildungsleitung des eigenen Vereins,
  einschließlich Bewertung und Abschluss. Die Rolle erbt `ROLE_INSTRUCTOR`,
  aber keine allgemeine Administration.
- `ROLE_COURSE_ADMIN`: Kursverwaltung des eigenen Vereins, Ausbildungsplan,
  Termine, Teilnehmer, Warteliste und Zahlungsbuchungen. Die Rolle allein
  berechtigt nicht zu Prüfungsbewertungen oder individuellen Bestehensentscheidungen.
- Bestehende Vereinsadministratoren behalten ihre Verwaltungsrechte im eigenen
  Verein; nur Superadministratoren erhalten vereinsübergreifenden Zugriff.
  Neue Rollen werden in der bestehenden Benutzerverwaltung vergeben.

## Kursverwaltung und Durchführung

Kurse können im Portal angelegt und bearbeitet werden. Ausbildungsorte und
Ausbilder werden pro Termin geführt. Theorie-, Praxis- und Prüfungseinheiten
werden als vereinseigene Einheitenarten definiert und mit Lernzielen,
Pflichtkennzeichnung und benötigter Terminanzahl in den Ausbildungsplan
aufgenommen. Vorhandene globale Einheitenarten bleiben nutzbar.

Termine und Nachholtermine werden für dieselben Ausbildungsanforderungen
angelegt. Nachholtermine referenzieren die ursprünglichen Terminbuchungen;
vorhandene Nachweise und Anwesenheiten bleiben erhalten. Zusätzliche
Terminbuchungen können über die bestehende Sammelbewertung synchronisiert
werden, auch für Gäste. Wartende und stornierte Teilnehmer werden dabei
nicht übernommen.

Bewertungen verwenden die vorhandenen Zustände offen, bestanden, nicht
bestanden und Wiederholung erforderlich. Bestanden setzt bestätigte
Anwesenheit voraus. Prüfer und Bewertungsdatum werden gespeichert.
Pflichtanforderungen bestimmen den individuellen Fortschritt; ohne erfüllte
Pflichtanforderungen ist kein Bestehen möglich.

## Anmeldung, Warteliste und Stornierung

Die Teilnahme ist dauerhaft an `Person` gebunden. Ein optionaler Bezug auf
`Member` bleibt für bestehende Mitgliederabläufe bestehen. Anmeldung und
Platzvergabe werden durch eine Datenbanktransaktion mit Kurssperre serialisiert.
Eine Person kann sich nicht doppelt für denselben Kurs anmelden.

Bei ausgeschöpfter Kapazität wird ein Wartelisteneintrag angelegt. Wartende
und stornierte Anmeldungen belegen keinen regulären Platz. Wird ein belegter
Platz in einem noch offenen Kurs storniert, rückt der älteste Wartende nach
Anmeldezeitpunkt und ID nach, sofern Kapazität frei ist. Die ursprüngliche
Gebührenvereinbarung bleibt erhalten. Bereits endgültig entschiedene oder
archivierte Teilnahmen können nicht selbst storniert werden.

Die Teilnahmebestätigung und der aktuelle Wartelistenstatus sind im eigenen
Portal abrufbar. Die ursprüngliche, stornierte Anmeldung bleibt in der
Historie; erneute öffentliche Doppelanmeldungen werden abgewiesen.

## Zahlungsabwicklung

Der bei Anmeldung gültige Preis wird centgenau in der Teilnahme gespeichert.
Spätere Kurspreisänderungen verändern keine bestehenden Forderungen.

Die Kursverwaltung erfasst **tatsächlich erfolgte Überweisungen und
Barzahlungen** mit Betrag, eindeutiger Belegreferenz, Bearbeiter und Zeit.
Teilzahlungen sind möglich. Eine doppelte Belegreferenz, Überzahlungen,
Zahlungen für Wartende sowie unzulässige Erstattungen werden abgewiesen.
Erstattungen sind negative Buchungen und löschen keine Originalzahlung.
Nach einer Stornierung zeigt das Portal den noch zu erstattenden Betrag.

Dies ist kein Kreditkarten-, PayPal- oder Bankeinzugsadapter. Es werden keine
externen Zahlungen ausgelöst. Kontoverbindung und praktische Zahlungsannahme
werden mit dem jeweiligen Kursverein abgestimmt. Der Zahlungseingang ist
bewusst von der fachlichen Ausbildungsentscheidung getrennt.

## Abschluss, Brevetierung und Archiv

Die Ausbildungsleitung oder ein entsprechend berechtigter Administrator
entscheidet über das Bestehen. Dabei werden erfüllte Pflichtanforderungen
zentral erneut geprüft. Eine optionale offizielle Brevetnummer kann
eingetragen werden. Für Mitglieder bleibt die bestehende Erstellung einer
`MemberQualification` erhalten. Für Gäste wird stattdessen die
personenbezogene Teilnahmebescheinigung ausgestellt.

Der Abschluss erzeugt einen unveränderlichen JSON-Snapshot mit
Teilnehmeridentität, Kurs, Verein, Zielqualifikation, Herausgeber,
Abschlussdatum, Lernzielen, Anforderungen, Bewertungen, Prüfern und
Anwesenheiten einschließlich Nachholbezügen. Die Bescheinigung besitzt eine
eindeutige interne Nummer und zeigt gegebenenfalls die offizielle
Brevetnummer. Sie ist nur für den Teilnehmer oder berechtigte
Ausbildungsmitarbeiter abrufbar und kann über die Druckfunktion als PDF
gespeichert werden. Sie ersetzt keine externe Verbandsregistrierung.

Ein Kurs kann erst abgeschlossen werden, wenn sämtliche Teilnahmen
einschließlich Warteliste abschließend entschieden sind. Dann werden auch
die übrigen Ausbildungsnachweise archiviert. Sowohl der neue Portalabschluss
als auch der vorhandene administrative Kursabschluss verwenden denselben
Archivierungsservice. Archivierte Teilnahmen und zugehörige Kurse sind
gegen ORM-Löschung geschützt; die Nachweisbearbeitung ist gesperrt.

## Späterer Vereinsbeitritt

Die vorhandene Mitgliederaufnahme muss die bereits geprüfte Gastperson und
deren Konto wiederverwenden, wie in Phase 3 vorgesehen. Da Anmeldungen,
Zahlungen und Ausbildungsnachweise an derselben `Person` hängen, bleiben sie
im Schülerportal nach einem Beitritt unverändert sichtbar. Ein Beitritt vor
dem Ausbildungsabschluss wird bei der Qualifikationsvergabe berücksichtigt.
Bereits ausgestellte Gastbescheinigungen bleiben als Originalnachweise
erhalten; es erfolgt keine automatische externe Brevetumschreibung.

## Migration und Inbetriebnahme

`Version20261024130000` ist additiv und absichtlich nicht rückwärts
datenlöschend. Sie:

1. prüft vollständige Personenzuordnung sowie doppelte Kurs-/Personpaare vor DDL;
2. ergänzt und befüllt Personenbezüge und Gebühren bestehender Teilnahmen;
3. macht den bisherigen Mitgliedsbezug optional und erhält alle Teilnehmer-IDs;
4. ergänzt Ausbildungsarchive und die Zahlungsbuchungstabelle;
5. verknüpft bestehende Terminbuchungen mit ihren Kursteilnahmen.

Vor Anwendung auf einem bestehenden Zielsystem sind Backup und eine
kontrollierte Migration notwendig. In dieser Umsetzung wurden ausschließlich
SQLite-In-Memory- und zufällig benannte Wegwerf-Datenbanken auf dem separaten
MariaDB-Testdienst verwendet. **Die vorhandenen Anwendungsdatenbanken und
Produktiv-Caches wurden nicht migriert oder aktiviert.**

## Prüfung

Die dedizierten HTTP-/Integrationstests stehen in
[`Phase4TrainingHttpTest.php`](../tests/Integration/Phase4TrainingHttpTest.php)
und sind Teil der Testsuite `isolated`. Sie prüfen öffentliche
Gastanmeldung ohne Mitgliedsanlage, Rollen und Vereinsgrenzen, CSRF,
Wartelistenreihenfolge, Zahlungen und Erstattungen, Anwesenheiten,
Prüfungsergebnisse, Nachholbezüge, Kursverwaltung, Mitgliederqualifikationen,
Gastbescheinigungen und Identitätserhalt beim späteren Vereinsbeitritt.

Die MariaDB-Migrationssuite prüft zusätzlich die Übernahme bestehender
Teilnahmen, Gebühren und Terminbuchungen sowie Mapping-/Schemagleichheit.
Die Tests verwenden keine externen Mails oder Zahlungsdienste.

Prüfstand vom 24.10.2026 (bestehender DDEV-Webcontainer mit PHP 8.5):

| Prüfung | Ergebnis |
|---|---|
| Kombinierte Unit-/isolierte Suite | 100 Tests, 531 Assertions, erfolgreich ohne übersprungene Tests |
| Abschließender gezielter Phase-4-Lauf | 10 Tests, 127 Assertions, erfolgreich nach den letzten Transaktionsänderungen |
| Vollständige MariaDB-Migrationskette | 17 Tests, 136 Assertions, erfolgreich einschließlich Upgrade, frischer Installation und historischer Rollbacks |
| Container, Sicherheits-YAML, 13 betroffene Twig-Templates, Patchformat | Erfolgreich |
| Globaler Übersetzungscheck | Nicht vollständig grün: vorhandene Kataloglücken unter anderem bei `Menge`, Mitgliedskontaktfeldern und älteren administrativen Kurstexten; keine gemeldete Kataloglücke der neuen Kursform |

Die neuen Formularbezeichnungen sind in Deutsch, Englisch, Französisch und
Spanisch ergänzt. Die bestehenden, nicht zu Phase 4 gehörenden
Übersetzungslücken wurden nicht nebenbei bereinigt.

```sh
php vendor/bin/phpunit --testsuite unit,isolated
php vendor/bin/phpunit tests/Integration/MigrationChainTest.php
```

Die Migrationssuite benötigt die in der README beschriebene separate
`MIGRATION_TEST_DATABASE_URL`. Für die isolierten HTTP-Tests sind
`pdo_sqlite` und eine zur installierten PHPUnit-Version passende
PHP-Laufzeit erforderlich.
