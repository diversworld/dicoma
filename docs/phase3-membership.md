# Phase 3 – Vereinsmitgliederverwaltung

## Fachlicher Umfang

Die lokale technische Phase-2-Abnahme und die Weiterentwicklung wurden ausdrücklich freigegeben. Phase 3 verwendet das bestehende Personen-, Konto-, Mitglieder-, Dokument- und Qualifikationsmodell; es entsteht keine zweite Personenverwaltung.

| Bereich | Umsetzung |
|---|---|
| Stammdaten | Bestehende persönliche Angaben, führende PLZ-Nullen und Mitgliedsnummern bleiben erhalten. Hinzu kommen Mitgliedschaftsart, Vereinsfunktionen und Notfallkontakt. Vereinsfunktionen vergeben keine Sicherheitsrollen. |
| Antrag | Öffentlicher Antrag ohne Konto; eigener unverifizierter Datenschnappschuss, Einwilligungsbestätigung und CSRF. Keine automatische Mitgliedschaft, Person oder Kontoanlage. |
| Aufnahme | Vereinsverwaltung entscheidet nach dokumentierter manueller Identitätsprüfung. Globale Personenauswahl nur für Superadmins. Keine automatische Zusammenführung anhand Name/E-Mail. Gemeinsame Kontaktadressen erfordern bei Neuanlage eine ausdrückliche Entscheidung des Superadmins. Vorhandene Stammdaten werden nicht durch Antragseingaben überschrieben. |
| Mitgliedschaft | Statuswechsel über die Mitgliederverwaltung; satzungsgerechte Kündigung, wirksamer Austritt und anschließende Archivierung. Personen, Konten, Dokumente, Forderungen und Historie werden nicht gelöscht. |
| Beiträge | Vereinsbezogene EUR-Beitragsklassen anlegen, bearbeiten und deaktivieren; Jahres-/Familien-/Sonderforderungen, begründete Verwaltungsermäßigungen, offene Beträge und Teilzahlungen mit Zahlungsprotokoll. |
| Familie | Expliziter eigenständiger Familienzahler im selben Verein mit Familienklasse. Keine Zyklen oder zusätzlichen Jahresforderungen beim abhängigen Mitglied. Bei ausgeschiedenem Zahler: Verwaltungswarnung statt erfundener Einzelbeiträge. |
| SEPA | Gläubiger-ID, eindeutige Referenz je Verein, Kontoinhaber, gültige IBAN, Unterschriftsdatum und eigener gespeicherter Nachweis. Verschlüsselte IBAN, maskierte Anzeige, dokumentierter Widerruf. Kein Bankeinzug und kein Zahlungsdateiexport. |
| Self-Service | Nur eigene Kontakt-, Adress- und Notfalldaten. Keine Änderung von Namen, Geburtstag, Konto-/Reset-E-Mail, Status, Sicherheitsrollen, Vereinsfunktionen, Beiträgen oder Ermäßigungen. |
| Automatisierung | Ablaufwarnung für verifizierte Tauchtauglichkeit innerhalb von 30 Tagen bzw. nach Ablauf; monatliche Hinweise auf konfigurierte fehlende Dokumentarten; Benachrichtigungen zu Mitgliedschaftsvorgängen. |

## Einstieg und Rechte

- Öffentlich: `/membership/apply`; einzelne Vereine erscheinen erst nach ausdrücklicher Aktivierung ihrer Antragsannahme.
- Verwaltung: Dashboard → **Mitgliedschaften und Beiträge**, `/admin/membership`; Vereinsadmins sehen ausschließlich ihren Verein, Superadmins dürfen vereinsübergreifend arbeiten.
- Mitglied: Profil → **Mitgliedschaft**, `/profile/membership`, eigene Kontaktbearbeitung unter `/profile/contact`.
- Bestehende EasyAdmin-Stammdaten bleiben erhalten. Bei vorhandenen Mitgliedschaften sind Verein und Status dort nicht mehr direkt editierbar; Statuswechsel/Kündigung/Archivierung erfolgen über die neuen dokumentierten Abläufe. Eine weitere Vereinsmitgliedschaft wird separat angelegt, nicht durch Verschieben einer historischen Mitgliedschaft.
- Die direkte EasyAdmin-Mitgliederlöschung ist zugunsten der Archivierung deaktiviert. Auch der alte `/member/{id}`-Lösch-POST löscht nicht mehr, sondern führt nach CSRF-Prüfung zur Archivierungsverwaltung. Fremdschlüssel schützen Finanz- und Vorgangshistorie zusätzlich. Ein separater DSGVO-Aufbewahrungs-/Löschprozess ist damit nicht ersetzt.
- Konto-E-Mail und zentrale Kontakt-E-Mail bleiben getrennt. Eine Kontaktänderung betrifft dieselbe zentrale Person auch in anderen Vereinen; die Oberfläche weist darauf hin.
- Private Finanz-/Mitgliederantworten sind nicht cachebar. CSRF und Objekt-/Vereinsprüfung gelten auch für Zahlungs- und Mandatsaktionen. Mandatsnachweise dürfen über den Profilbereich nicht entfernt werden.

## Einmalige Einrichtung je Verein

1. Verwaltungs-Kontaktadresse und bei Bedarf öffentliche Anträge aktivieren.
2. Satzungsregeln ausdrücklich eintragen: für den bestätigten Jahresfall `09-30` als Kündigungsstichtag und `12-31` als Jahresende. Das sind Beispiele, keine automatisch gesetzten Defaults. Ohne beide Werte gibt es keine automatische Kündigungsberechnung.
3. Erforderliche Dokumentarten auswählen; bestehende Dokumente erhalten zunächst Kategorie `other` und müssen bei Bedarf fachlich zugeordnet werden.
4. Tatsächlich beschlossene Beitragsklassen und EUR-Beträge eintragen; es werden keine Tarife erfunden und keine Bestandsforderungen automatisch erstellt.
5. Familienzahler, Klassen und begründete Ermäßigungen durch die Verwaltung zuordnen.
6. Für SEPA die tatsächliche Gläubiger-ID und den geheimen Bankschlüssel konfigurieren; unterschriebene Nachweise privat hochladen.
7. Für Erinnerungen die vorhandene Qualifikationsverwaltung mit dem Typ **Tauchtauglichkeit**, verifiziertem Nachweis und Ablaufdatum verwenden.

## Fachliche Grenzen

- Eingang bis einschließlich 30. September ergibt bei der Beispielkonfiguration den 31. Dezember desselben Jahres; späterer Eingang das folgende Jahresende. Das Austrittsdatum wird erst am Folgetag wirksam verarbeitet, damit `ROLE_MEMBER` nicht vorzeitig entfällt.
- Aktiv/passiv und weitere bestehende Statuswerte folgen weiterhin dem Phase-2-Rollenvertrag. Ein Funktionskonto ersetzt keine aktive Mitgliedschaft.
- Jahresforderungen sind eingefrorene Betrags-/Text-Schnappschüsse, eindeutig je Mitglied/Jahr. Spätere Klassenänderungen schreiben alte Forderungen nicht um. Keine stillschweigende zeitanteilige Beitragsberechnung.
- Zahlungseingänge sind positive Cent-Beträge mit Referenz; eine atomare Datenbankbedingung verhindert Überzahlung. Die vorhandene Geräte-/Kassenbuchhaltung wird dadurch nicht ersetzt.
- Deaktivierte Klassen bleiben als Historie sichtbar, können aber keine neuen Jahresforderungen begründen.
- Ein Familienmitglied kann bei ausgeschiedenem Zahler erst nach Verwaltungsentscheidung neu abgerechnet werden. Keine automatische Neuzuordnung oder Belastung.
- Mehrere Vereinsmitgliedschaften derselben Person sind möglich. Der bestehende aktive Konto-Mitgliedschaftskontext bleibt unverändert; ein neuer Vereinswechseldialog ist nicht Teil dieser Phase.

## Betrieb: Schlüssel und Nachrichten

Secrets ausschließlich außerhalb Git verwalten. `DICOMA_MEMBERSHIP_BANK_KEY` muss Base64-kodierte, kryptographisch zufällige **32 Bytes** enthalten. PHP `sodium` ist für SEPA erforderlich; ohne Erweiterung oder korrekten Schlüssel wird die Speicherung ausdrücklich abgewiesen. Schlüssel und Datenbank getrennt geschützt sichern. Ein Schlüsselwechsel ohne kontrollierte Neuverschlüsselung macht vorhandene IBANs unlesbar; keine automatische Schlüsselrotation durchführen. IBANs niemals in Logs, Dokumentation oder Quellcode übernehmen.

Der tägliche Befehl verändert Mitgliedschaftsdaten und legt Nachrichten in der Outbox ab, sendet aber standardmäßig keine E-Mails:

```bash
php bin/console app:membership:daily --env=prod
```

Erst nach Prüfung von Datenbankziel, `MAILER_DSN`, `MEMBERSHIP_MAIL_FROM` und Empfängern den Versand ausdrücklich einschalten:

```bash
php bin/console app:membership:daily --env=prod --send
php bin/console app:membership:daily --env=prod --send --retry-failed
```

Der Betreiber muss den täglichen Aufruf einplanen; es wird kein Cronjob automatisch installiert. Ein gemeinsamer Dateilock verhindert parallele Läufe auf demselben Host. Höchstens 200 Nachrichten pro Aufruf, bei größerem Rückstand wiederholen. Fehlende Absenderkonfiguration und Transportfehler führen zu Fehlerstatus; Transportfehler werden protokolliert und die Nachricht als `failed` gespeichert. Fehlgeschlagene Nachrichten werden nur auf ausdrücklichen Wiederholungsaufruf erneut versucht. Bei einem Prozessabbruch nach Mailversand, aber vor Datenbankbestätigung ist Doppelzustellung möglich; dies ist keine Exactly-once-Zustellung und kein verteiltes Mehrhost-Queue-System. Fehlende Kontaktadressen werden protokolliert.

## Migration und Nachweise

`Version20261024102048` ergänzt acht Tabellen, Mitgliederfelder und Dokumentkategorien. Vor sämtlichen Schemaänderungen werden doppelte Mitgliedsnummern je Verein abgewiesen. Bestehende IDs, Stammdaten und die ältere, nicht als ORM-Entity gemappte Tabelle `equipment_cash_correction` bleiben erhalten. Kein automatisches `down()`, das Finanz- und Vorgangsdaten verwerfen würde.

Vor Aktivierung: Backup und Restore auf neuer isolierter Datenbank prüfen, Migration auf dieser Kopie ausführen, Schema sowie alte Daten vergleichen und erst nach ausdrücklicher Freigabe das lokale Ziel migrieren. Siehe [Betrieb und Wiederherstellung](deployment/operations.md).

Die automatisierte Abdeckung liegt in:

- [Satzungsregeln](../tests/Unit/MembershipPolicyTest.php): genaue Fristen, Jahreswechsel, fehlende Konfiguration und ungültige Kalendertage.
- [Mitgliederabläufe](../tests/Integration/Phase3MembershipHttpTest.php): echte Antrags-/Genehmigungs-/Kontakt-/Beitrags-/Zahlungsformulare, Vereinsgrenzen, Identitätsentscheidung, Familienabrechnung, Teilzahlung/Überzahlungsrollback, Austritt/Archiv, Erinnerung/Deduplizierung, SEPA-Verschlüsselung und fehlender Schlüssel sowie expliziter Mailversand mit Null-Mailer.
- [MariaDB-Migrationen](../tests/Integration/MigrationChainTest.php): kompletter Migrationspfad, Bestandserhalt für Mitglieder/Dokumente/Kassenkorrekturen sowie Abbruch bei doppelten Mitgliedsnummern vor neuen Tabellen.

Die genaue ausgeführte lokale Prüfung wird im [Prüfbericht](testing/test-report.md) dokumentiert. Technische Tests sind keine externe Produktivfreigabe, keine Ausführung echter Bankeinzüge und keine Bestätigung bereits eingerichteter vereinsindividueller Tarife, Satzung oder Mailzustellung.
