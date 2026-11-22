# Technisches Lastenheft – Vorstands- und Vereinsportal

## 1. Ziel

Die Lösung soll die tägliche Arbeit von Vorstand, Stellvertretung, Schriftführung und Kassenwart in EasyAdmin bündeln. Mitglieder erhalten einen eingeschränkten Self-Service. Die bestehende Tauch-, Ausbildungs- und Ausrüstungslogik bleibt erhalten.

## 2. Rollenmodell und Berechtigungen

### 2.1 Rollen

| Rolle | Verantwortungsbereich |
|---|---|
| `ROLE_MEMBER` | Eigene Mitgliedschaft, eigene Kontakt-/Profildaten, eigene Dateien, freigegebene Vereinsdokumente, eigener Beitragsstatus |
| `ROLE_BOARD` | Mitglieder, Termine, Aufgaben, Beschlüsse, Dokumentfreigaben, Vereinsstatistiken; Finanzdaten lesend entsprechend Berichtszweck |
| `ROLE_SECRETARY` | Sitzungen, Agenda, Teilnehmer, Protokolle, Beschlüsse, Aufgaben aus Beschlüssen, Einladungs-/Mitgliederanschreiben |
| `ROLE_TREASURER` | Beitragsklassen, Forderungen, Zahlungseingänge, Mahnungen, Lastschriftvorbereitung, Einnahmen, Ausgaben, Abschluss |
| `ROLE_ADMIN` | Vereinsbezogene Administration aller freigegebenen Fachmodule |
| `ROLE_SUPER_ADMIN` | Globale technische Verwaltung |

### 2.2 Berechtigungsregeln

- Jede adminis­trative Abfrage wird auf den ausgewählten Verein begrenzt.
- Ein Mitglied darf nie allgemeine Vereinsdokumente bearbeiten oder nicht freigegebene Versionen lesen.
- Schriftführung darf Sitzungsdaten bearbeiten, aber keine Zahlung verbuchen.
- Kassenwart darf Finanzdaten bearbeiten, aber keine Protokolle verändern.
- Vorstand darf Dokumente freigeben, aber die technische Benutzer- und Systemkonfiguration bleibt begrenzt.
- Destruktive Schritte benötigen CSRF, konkrete Objektprüfung und Audit-Eintrag.
- Rollenänderungen sind zeitlich befristbar und prüfen Mitgliedschaftsstatus sowie Vereinszuordnung.

## 3. Fachliche Anforderungen

### 3.1 Mitglieder

Pflichtfelder:

- Mitgliedsnummer
- Vor-/Nachname
- Geburtstag
- Kontakt, Mobil, Telefon
- Straße, PLZ, Ort
- Eintritt, Austritt, Status, Mitgliedsart
- Aktive/passive Mitgliedschaft, Familienzuordnung
- Vereinsrollen, Sport-/Abteilungszuordnung
- Ereignis- und Verwaltungsänderungshistorie

Noch fachlich auszubauen:

- optionales Geschlecht
- Profilbild
- strukturierte Änderungsübersicht für alle personenbezogenen Felder

### 3.2 Beitragsverwaltung

- Beitragsklassen pro Verein
- Jahresbetrag, Familienzahler, Rabatt mit Begründung
- Abteilungszuschläge
- Sonderforderung
- Fälligkeitsdatum, Teilzahlung, Belegreferenz und Rechnung
- Status offen, bezahlt, überfällig, gemahnt
- SEPA-Mandat mit verschlüsselter IBAN und privatem Nachweis

Zielerweiterung:

- periodischer Monats-/Jahresrhythmus
- BIC und pain.008-Export
- kontrollierte Lastschriftsammeldatei mit Hash und Freigabestatus

### 3.3 Mahnwesen

Stufen:

1. Zahlungserinnerung
2. Erste Mahnung
3. Zweite Mahnung
4. Letzte Mahnung

Jede Stufe besitzt:

- eindeutigen Schlüssel pro Forderung
- Betreff und Textvorlage
- Erstellungszeit
- nächste Aktion
- E-Mail-Outbox-Eintrag
- PDF-Ausgabe

Ein CLI-Lauf darf dieselbe Stufe nicht duplizieren. Die nächste Stufe entsteht erst nach dem konfigurierten Wiederholabstand.

### 3.4 Aufgaben

Felder:

- Titel und Beschreibung
- Priorität: niedrig, normal, hoch, dringend
- Status: offen, in Bearbeitung, erledigt, abgebrochen
- Verantwortliche Person
- Ersteller
- Frist
- Erinnerung
- optionale Beschlussverknüpfung

Überfällige Aufgaben werden im Dashboard hervorgehoben. Erinnerungen laufen über die vorhandene Outbox und sind idempotent.

### 3.5 Sitzungen und Beschlüsse

Sitzung:

- Titel, Beginn, Ende, Ort
- Agenda
- Teilnehmende
- Status
- Protokoll

Beschluss:

- Titel und Entscheidung
- Status
- Verantwortlicher
- Frist
- Verknüpfte Aufgabe

Eine Aufgabe kann pro Beschluss nur einmal angelegt werden. Verworfen oder erledigt verhindert die nachträgliche Automatik.

### 3.6 Dokumentenmanagement

Dokument:

- Titel, Kategorie und Mitgliedersichtbarkeit
- Status Entwurf, zur Freigabe, freigegeben, archiviert
- Vereinsscope
- Ersteller

Version:

- Versionsnummer
- Original- und Speichername
- MIME-Typ und Größe
- Uploader
- Freigebende Person und Zeitpunkt

Uploads werden außerhalb `public/` und mit zufälligem Namen gespeichert. Download erfolgt über Controllerprüfung. Für Mitglieder werden nur freigegebene Versionen und als mitgliedersichtbar markierte Dokumente ausgeliefert.

### 3.7 Kommunikation

Zielgruppen:

- alle Mitglieder
- Vorstand
- Jugendabteilung
- Ehrenmitglieder
- frei wählbare Gruppen

Vorlagen:

- Aufnahmebestätigung
- Beitragsrechnung
- Mahnung
- Einladung
- Newsletter

Für jeden Versand müssen Empfängerliste, Anzahl, Vorlagentext, Ausführende Person und Ergebnis protokolliert werden. Testversand und Vorschau sind verpflichtend.

## 4. Dashboards

### Vorstand

Pflicht-KPIs:

- Mitglieder gesamt, aktiv, passiv
- neue Mitglieder und Austritte
- offene Aufgaben
- kommende Termine
- offene Beschlüsse
- offene Mahnungen
- aktuelle Einnahmen
- Geburtstage

### Schriftführung

- kommende Vorstandssitzungen
- fehlende Protokolle
- offene Beschlüsse
- offene Aufgaben
- Dokumentfreigaben

### Kassenwart

- fällige/überfällige Beiträge
- offene Mahnungen
- Zahlungseingänge
- Lastschriftsätze
- Einnahmen
- Ausgaben
- Kontostände

## 5. Nicht-funktionale Anforderungen

### Sicherheit

- `auto` Password Hashing
- CSRF für alle Mutationen
- Role Validity und Club Scope
- Security Voter bzw. eindeutiger Controller-Gate pro Objekt
- kein Klartext für IBAN im Browser/Log/Export ohne Freigabe
- private Header bei Download und `no-store`
- Audit-Trail mit Akteur, Zeitpunkt, Objekt und Aktion
- Rate Limiting am Login und Versand

### Datenschutz

- Datenminimierung
- definierte Aufbewahrungsfrist
- Lösch-/Anonymisierungsprozess
- personenbezogener Datenexport
- private Backups
- AVV-/TOM-Nachweis außerhalb des Anwendungscodes

### Performance

- Listen paginiert
- indizierte Club-/Status-/Frist-Suchfelder
- keine N+1-Abfragen in Dashboardlisten
- Aggregationen in SQL
- messbare Zeitgrenzen für 10.000 Mitglieder

### UX und Barrierefreiheit

- Mobile First und Bootstrap-5-Layout
- Tastaturbedienung
- sichtbarer Fokus
- eindeutige Labels
- Status nicht nur über Farbe
- wenig Klicks von Dashboard zu Aufgabe, Beschluss oder Forderung
- deutsche Fachsprache; technische Felder ausblenden

### Betrieb

- Migrationen vor Code-Umschaltung
- tägliche Mahn-, Aufgaben- und Mitgliedschaftsautomationen
- Fehleralarmierung bei Versand und Export
- Datei-Backup mit Datenbankkonsistenz
- Restore-Test

## 6. Datenmodell

Neue Aggregate:

- `BoardTask`: `club`, `title`, `description`, `priority`, `status`, `assignee`, `createdBy`, `dueAt`, `remindAt`, `completedAt`, `resolution`
- `BoardMeeting`: `club`, `title`, Zeitraum, Ort, Agenda, Minuten/Protokoll, Status, Teilnehmende
- `BoardResolution`: `meeting`, Entscheidung, Status, Verantwortlicher, Frist
- `ClubDocument`: `club`, Titel, Kategorie, Status, Mitgliedersichtbarkeit
- `ClubDocumentVersion`: Version, Datei-Metadaten, Uploader, Freigabe
- `MembershipDunningNotice`: Forderung, Mahnstufe, Text, Erstellung, nächste Aktion

## 7. Abhängigkeiten und Bereitstellung

Bereitstellung:

1. Backup von Datenbank und `var/club-documents`.
2. `php bin/console doctrine:migrations:migrate --no-interaction`
3. Cache für prod leeren und vorwärmen.
4. Uploadverzeichnis durch Webprozess beschreibbar; nicht öffentlich bereitstellen.
5. Tägliche Cron-Jobs für Mitgliedschaft, Mahnung und Aufgaben.
6. Smoke-Test für Login, Board-Dashboard, Dokument-Download und Mahn-PDF.

## 8. Offene produktive Abnahme

Die Phase-A-Module für Buchhaltung, SEPA, Rollen, Mahnung, Audit, Datenschutz und Testgates sind implementiert. Für die formale Produktivabnahme verbleiben:

- Bankseitiger Abgleich des konkreten pain.008-Profils
- historischer Abgleich/Backfill des neuen Journals gegen Bestandssysteme
- Gruppen-E-Mail mit Vorlageneditor
- steuerrechtliche Bestätigung der Aufbewahrungsfristen und Jahresabschlussrichtlinie
- AV-Malware-Prüfung und Dokumentaufbewahrung
- rechtliche Gestaltung der PDF-Mahntexte
- vertiefte manuelle WCAG-2.2-AA- und Langzeitlastabnahme
