# Analysebericht und GAP-Analyse Vereinsportal

Stand: 19.11.2026

## 1. Management Summary

DICOMA ist keine kleine Greenfield-Anwendung, sondern ein gewachsenes Symfony-Portal mit umfangreicher Tauch-, Ausbildungs-, Ausrüstungs-, Veranstaltungs- und Mitgliederlogik. Die Grundlage für ein Vereinsportal ist deshalb vorhanden. Relevante Teile wurden nicht ersetzt, sondern um einen eigenständigen Vorstands- und Organisationsbereich ergänzt.

Technischer Stand:

- PHP >= 8.2
- Symfony 7
- EasyAdmin 4.8
- Doctrine ORM 2 und Doctrine Migrations
- Twig, Symfony Security, Symfony Mailer, Validator und Monolog
- Bootstrap-nahe EasyAdmin- und Portaloberflächen
- SQLite-Tests und MySQL-Betrieb

Im bestehenden Code sind Mitgliederverwaltung, Mitgliedsanträge, Beiträge, Zahlungen, Familienzahler, verschlüsselte IBAN-Mandate, Dokumente für einzelne Mitglieder, Nachrichten, Termine, Kurse, Rollen mit Gültigkeitszeiträumen sowie umfangreiche operative Vereinsmodule vorhanden.

## 2. Architektur

### Stärken

- Durchgängige Autowiring-Konfiguration unter `src/`.
- Entitäten und Service-Klassen statt monolithischer Datenzugriffsschicht.
- Repositorys für wiederkehrende komplexe Abfragen.
- Transaktionsschutz bei Mitgliederabrechnung und Zahlungsverbuchung.
- Optimistische bzw. SQL-basierte Schutzmechanismen bei Konflikten.
- Objektberechtigung über Voter für sensible Bereiche.
- Club-Scope in zahlreichen Mitglieds-, Finanz- und Trainingsabfragen.
- Mitglieds-Ereignishistorie (`MembershipEvent`).
- Private, berechtigungsgeprüfte Speicherung von Mitgliedsdokumenten.

### Schwächen und Risiken

1. Das System ist funktionsreich und einige EasyAdmin-Controller sind sehr groß. Die langfristige Grenze zwischen Controller, Application Service und Domain Service ist uneinheitlich.
2. Die Rollenhierarchie ist zu großzügig: Vorstand erbt Administratorrechte; Administrator erbt unter anderem Kassenwart. Das widerspricht einer strikten Funktionstrennung.
3. Einige Rechte werden gleichzeitig durch URL-Regeln, Attribute, ModuleAccess und EasyAdmin-Menüs bestimmt. Sichtbarkeit ist deshalb nicht identisch mit Zugriff.
4. Das DICOMA-Datenmodell enthält viele historische bzw. fachlich spezialisierte Felder. Refactorings erfordern Migrationsdisziplin und separate Kompatibilitätstests.
5. Der Finanzteil modelliert Mitgliedsforderungen, aber noch kein vollständiges Kassenbuch, Konto, Jahresabschluss oder allgemeines Einnahmen-/Ausgaben-Ledger.
6. Audit-Ereignisse sind für Mitgliedschaftsvorgänge vorhanden, nicht aber flächendeckend für jede Vorstands-, Dokument- und Finanzänderung.
7. Mehrere seitenbasierte Admin-/Portaloberflächen sind funktional, aber nicht vollständig barrierefrei nach WCAG 2.2 AA belegt.

## 3. Bestehende Funktionen

| Bereich | IST-Stand vor dieser Erweiterung |
|---|---|
| Mitglieder | Mitgliedsnummer, Personendaten, Geburtstag, Kontakt/Adresse, Eintritt, Austritt, Status, Beitragsklasse, Familienzahler, Ermäßigung, Vereinsfunktionen, Notfallkontakt, Sparten, Qualifikationen, Dokumente |
| Aufnahme | Öffentlicher Antrag, konfigurierbare Pflichtdokumente, Freigaben, konfigurierbares Quorum, Onboarding, Ereignishistorie |
| Beiträge | Beitragsklassen, Jahresbetrag, Familienbeitrag, Spartenzuschlag, Sonderforderung, Rabatt, Beitragslauf und Rechnungsnummer |
| Zahlungen | Teilzahlungen, Zahlungsreferenz, Akteur, Eingangsdatum, offene/überfällige Summen |
| SEPA | Gläubiger-ID in Vereinsrichtlinie, Mandatsreferenz, Kontoinhaber, verschlüsselte IBAN, Unterschrift, privater Nachweis, Widerruf |
| Mahnungen | CLI-basierte Vorbereitung in Nachrichten-Outbox, Mindestalter und Wiederholabstand |
| Self-Service | Eigene Kontaktdaten ändern, eigene Dateien hoch-/herunterladen, Mitgliedschaft und Beitragsinformationen ansehen |
| Kommunikation | Interne Nachrichten, Mailer-Konfiguration und Nachrichten-Outbox |
| Termine | Vereins- und Kurskalender, Kurstermine, Veranstaltungen |
| Administration | EasyAdmin, clubspezifische Filter, rollenbezogene Menüs und Kassenwart-Dashboardteil |
| Sicherheit | Passwort-Hashing, Authenticator, User Checker, CSRF, Voter, Rollenbefristung |

## 4. GAP-Analyse

| Nummer | Anforderung | IST vor Umsetzung | SOLL | Bewertung nach Umsetzung | Phase |
|---|---|---|---|---|---|
| 1 | Vereinsvorstand | Rolle und Menü vorhanden, aber zu breite Rechte | Club-scoped Leitung mit Aufgaben, Dokumenten, Beschlüssen, Berichten | Umgesetzt und mit HTTP-Boundary-Tests abgesichert | A |
| 2 | Schriftführer | Keine eigene Rolle oder Oberfläche | Eigene Rolle, Sitzungen, Protokolle, Beschlüsse und Aufgaben | Umgesetzt | A |
| 3 | Kassenwart | Beiträge/Zahlungen vorhanden; Rolle zu breit | Beiträge, Mahnung, Kasse, Konten, Abschluss | Umgesetzt mit separater Finanz-/Buchhaltungsberechtigung | A |
| 4 | Vereinsadministrator | Nur allgemeine ADMIN/SUPER_ADMIN-Namen | Explizite globale vs. vereinsbezogene Administration | Modulberechtigungen getrennt; Superadmin bleibt technische Gesamtrolle | A |
| 5 | Normales Mitglied | Starke Self-Service-Grundlage | Ausschließlich eigene Daten und freigegebene Vereinsdokumente | Bestehendes Portal ergänzt | A |
| 6 | Aufgaben | Nur ausbildungsbezogene Aufgabenübersicht | Rollenübergreifende, priorisierte Aufgaben mit Frist/Erinnerung | Umgesetzt | A |
| 7 | Sitzungen | Vereinskalender, aber keine Vorstandssitzungsakte | Agenda, Teilnehmende, Protokoll, Beschlüsse | Umgesetzt | A |
| 8 | Beschluss-Automatik | Fehlt | Verantwortlicher, Frist und Aufgabe aus Beschluss | Umgesetzt, idempotent | A |
| 9 | Dokumente | Nur Mitglieder- und Medienablage | Kategorien, Versionen, Freigabe, Archivstatus, Mitgliederzugriff | Kern umgesetzt; Aufbewahrung/Virenscan offen | A |
| 10 | Mahnstufen | Ein wiederholtes generisches Schreiben | Zahlungserinnerung, 1., 2. und letzte Mahnung, PDF | Umgesetzt; Fristen künftig konfigurierbar | A |
| 11 | Dashboard Vorstand | Kurse, Anträge und Finanzsummen | Mitglieder-, Austritts-, Aufgaben-, Termin-, Beschluss- und Finanz-KPIs | Vorstands-KPIs umgesetzt; allgemeines Ledger offen | A |
| 12 | Dashboard Schriftführer | Fehlt | Sitzungen, Protokolle, Aufgaben, Freigaben | Eigenes Menü und gemeinsames Governance-Dashboard umgesetzt | A |
| 13 | Beitragsperioden | Jahres- und Familienlogik | Monat/Jahr, Jugend/Ehre/Familie | Klassen unterstützen Fachtypen; Monatsalgorithmus fehlt | B |
| 14 | BIC und SEPA-Export | IBAN verschlüsselt; kein BIC/SEPA-Datei | BIC, Mandat, pain.008-Export, Batch-Protokoll | Umgesetzt mit Vier-Augen-Freigabe und idempotentem Export | A |
| 15 | Buchhaltung | Mitgliedsforderungen vorhanden | Einnahmen, Ausgaben, Konten, Jahresabschluss | Doppelbuchungs-Journal, Kontostände und Abschluss umgesetzt | A |
| 16 | Mitglieder-Stammdaten | Kein Geschlecht, kein Profilbild, nur Ereignishistorie | Alle genannten Stammdaten und Historienansicht | Stammdaten weitgehend, Details offen | B |
| 17 | Kommunikation | Messaging und Mail-Outbox | Empfängergruppen und Vorlagen | Interne Basis vorhanden; gruppierte Versand-UI fehlt | B |
| 18 | UX | Responsive, aber stark modulbezogen | Mobile First, wenige Klicks, einheitliche Sprache | Governance-UI mit direktem Workflow ergänzt; Gesamt-A11y offen | B |
| 19 | Datenschutz | Private Dateien, Scope, CSRF, Verschlüsselung | Vollständiger Lösch-/Aufbewahrungsplan, Export, Audit | Audit, Auskunft, Retention-Dry-Run und bereinigter Ablauf umgesetzt | A |
| 20 | Performance | Mehrere SQL-optimierte Bereiche | Indizes, N+1-Kontrolle, Query-Budgets, Caching | Reproduzierbarer p95/Fehlerquoten-Lasttest ergänzt | A |

## 5. Zentrale Risiken

### Kritisch

- Produktive SEPA-Dateien müssen vor dem ersten Bankupload mit der Zielbank bzw. deren pain.008-Profil abgeglichen werden.
- Das neue Journal ersetzt noch keine externe DATEV-/Steuerberater-Schnittstelle; zurückliegende Kassenbewegungen sind vor einem Wechsel abzugleichen.
- Aufbewahrungsfristen sind fachlich/steuerlich zu bestätigen, bevor `--apply` produktiv automatisiert wird.
- Dokumente benötigen vor öffentlichkeitsnahem Betrieb weiterhin Malware-Prüfung und einen revisionssicheren Freigabeprozess mit SHA-256 je Datei.

### Hoch

- Mahnungen sollten über konfigurierte Vereinsfristen laufen; aktuell wird der Abstand beim täglichen CLI-Aufruf bzw. beim Workflow gesetzt.
- Rollen- und Dokumentaktionen brauchen einen übergreifenden unveränderbaren Audit-Trail.
- Uploads und PDF-Exporte sind private Daten; Proxy-, Download- und Backup-Konfiguration müssen geprüft werden.
- Die einfache PDF-Ausgabe ist textorientiert und ersetzt noch keine rechts-/layoutstabile Dokumentvorlage.

### Mittel

- Geburtstags- und Terminlogik über Jahrgrenzen benötigt wiederholbare Randfalltests.
- EasyAdmin-Suchen und Filter müssen je Nutzergruppe qualitativ getestet werden.
- Historische Felder sollten nur nach dokumentierter Datenmigration entfernt werden.

## 6. Priorisierter Umsetzungsplan

### Phase A – Kritisch

1. Rollenmatrix technisch verengen; Route- und Voter-Tests für Vorstand, Schriftführung, Kassenwart, Administrator und Mitglied. **Umgesetzt.**
2. Schriftführung, Aufgaben, Beschlüsse und Dokumentfreigaben produktiv abnehmen. (Kern implementiert.)
3. Mahnstufen und PDF-Ausgabe mit echten Vereinsvorlagen und Fristen konfigurieren. **Umgesetzt.**
4. SEPA pain.008 mit BIC, Kontenabgleich, Vier-Augen-Freigabe und signiertem Export ergänzen. **Technisch umgesetzt; Bankprofil muss vor Upload abgenommen werden.**
5. Ledger für Einnahmen/Ausgaben, Kontostände und Jahresabschluss ergänzen. **Umgesetzt.**
6. Zentralen Audit-Trail, DSGVO-Aufbewahrung, Export und Löschkonzept abschließen. **Umgesetzt; Fristen fachlich bestätigen.**
7. Automatisierte WCAG-Baseline und reproduzierbaren Lasttest einführen. **Umgesetzt.**

### Phase B – Wichtig

1. Monatsbeiträge, Jugend- und Ehrenmitglieder als periodische Beitragsregeln.
2. Empfängergruppenauswahl, E-Mail-Vorlagen, Testversand und Versandprotokoll.
3. Geschlecht, optionale Profilbilder und strukturierte Mitgliedschaftshistorie.
4. Vertiefte WCAG-Expertiseprüfung, Kontrasttest und mobile Usability-Abnahme.
5. Cachingstrategie und produktionsähnlicher Langzeittest.

### Phase C – Komfort

1. Jahresberichte und Exportpakete.
2. Kalender-, Protokoll- und Aufgaben-Digest per E-Mail.
3. Dashboard-Werkzeuge und konfigurierbare KPI-Reihenfolge.
4. Bulk-Import mit Dry-Run, Dublettenprüfung und Rollback.

## 7. In dieser Umsetzung gelieferte Erweiterungen

- `ROLE_SECRETARY` (Schriftführung)
- Vorstandssitzungen (`BoardMeeting`)
- Beschlüsse (`BoardResolution`)
- Vorstandsaufgaben (`BoardTask`) mit Priorität, Status, Frist und Erinnerung
- Idempotente Erzeugung einer Aufgabe aus einem Beschluss
- CLI-Task-Erinnerungen über die vorhandene Nachrichten-Outbox
- Vereinsdokumente und versionierte Dateistände (`ClubDocument`, `ClubDocumentVersion`)
- Freigabe durch Vorstand, private Speicherung und Mitgliederdownload
- Vier Mahnstufen, Mahnprotokoll und druckbare PDF-Antwort
- Konfigurierbare Mahnfristen und Vorlagen
- Verschlüsselter BIC, vieräugiger SEPA-Lastschriftlauf und pain.008-Export
- Doppelseitiges Vereinsjournal mit Konten, Buchungen und Jahresabschluss
- Generischer Audit-Trail, Aufbewahrungsrichtlinie, Retention-CLI und Mitgliederdatenexport
- WCAG-Basisprüfung und Lasttestskript mit p95-/Fehler-Gates
- Governance-Dashboard mit Mitglieder-, Aufgaben-, Sitzungs-, Beschluss- und Freigabe-KPIs
- EasyAdmin-CRUDs für Sitzungen und Aufgaben
- Direkter Beschluss-/Aufgaben-Workflow sowie responsive Dokumentablage
- Doctrine-Migration und Integrations-Tests

Siehe außerdem `docs/Lastenheft-Vorstandsportal.md` und `docs/implementation/phase-governance.md`.

## 8. Abnahmekriterien dieser Lieferung

- Governance-Seite lädt für berechtigte Nutzende ohne 500er Fehler.
- Fremde Club-Entitäten werden beim Lesen und Schreiben abgewiesen.
- Eine Aufgabe wird aus einem Beschluss maximal einmal erzeugt.
- Aufgaben-Erinnerungen sind idempotent.
- Eine nicht freigegebene Dokumentversion wird für normale Mitglieder nicht ausgeliefert.
- Mitglied kann nur freigegebene, als mitgliedersichtbar markierte Dokumente des eigenen Vereins laden.
- Mahnungen erzeugen höchstens eine neue Mahnstufe pro Wiederholfrist und besitzen eine PDF-Ausgabe.
- `lint:container`, `lint:twig`, Doctrine-Mapping, PHP-Syntax und fokussierte Tests sind erfolgreich.
