Lastenheft / Fachkonzept für eine umfassende Vereinsverwaltungssoftware
---

# 1. Einleitung
## 1.1 Zielsetzung

Ziel des Projekts ist die Entwicklung einer webbasierten, modernen und DSGVO-konformen Vereinsverwaltungssoftware für Tauchvereine mit mehreren Sparten und Ausbildungsbereichen.

Die Anwendung soll sämtliche Prozesse eines Vereins digital abbilden, darunter:
- Mitgliederverwaltung
- Ausbildungsverwaltung
- Veranstaltungsmanagement
- Mannschafts- und Turnierverwaltung
- Geräte- und Wartungsmanagement
- Clubheim- und Thekendienstverwaltung
- Content-Management-System (CMS)
- Finanzverwaltung einzelner Bereiche
- Rechte- und Rollenverwaltung
- Gäste- und Kursverwaltung

Die Software soll modular aufgebaut werden, damit sie auch von anderen Tauchsportvereinen oder Mehrspartenvereinen eingesetzt werden kann.

# 2. Rahmenbedingungen
## 2.1 Zielgruppe

- Vereinsvorstand
- Geschäftsführung
- Mitgliederverwaltung
- Kassenwart
- Ausbildungsleiter
- Tauchausbilder
- Gerätewart
- Kompressorbetreiber
- Spartenleiter
- Pressewart
- Clubheimverwalter
- Thekendienst
- Vereinsmitglieder
- Gastteilnehmer
- Interessenten

## 2.2 Plattform

Webanwendung (Responsive Design)
Desktop
Tablet
Smartphone

## 2.3 Technologien
- Moderne Webanwendung
- REST-API oder GraphQL
- Responsive Frontend
- Mehrsprachigkeit vorbereitet
- Cloud- oder On-Premise-Betrieb

# 3. Systemverwaltung
## 3.1 Vereinskonfiguration

Der Verein soll über ein eigenes Administrationsmenü konfigurierbar sein.

Konfigurierbar sind:
- Vereinsname
- Anschrift
- Kontaktdaten
- Logo
- Verbandszugehörigkeit

Beispiele:
- VDST
- CMAS
- PADI Dive Center
- SSI Dive Center
- NAUI
- DIWA
- frei definierbar

Weitere Einstellungen:
- Beitragsarten
- Mitgliedsarten
- Sparten
- Rollen
- Berechtigungen
- E-Mail Vorlagen
- Dokumentenvorlagen
- DSGVO-Einstellungen

# 4. Rollen- und Rechtesystem
## 4.1 Rollen

Das System muss beliebig viele Rollen unterstützen.

Standardrollen:
- Vorstand
- Geschäftsführer
- Kassenwart
- Pressewart
- Ausbildungsleiter
- Tauchausbilder
- Gerätewart
- Kompressorbetreiber
- Spartenleiter
- Clubheimverwalter
- Thekendienst
- Mitglied
- Gast
- Administrator

## 4.2 Berechtigungen

Rollenbasierte Zugriffskontrolle (RBAC).

Beispiele:

| Funktion | Vorstand | Ausbildungsleiter | Gerätewart |
|-------|-------|-------|-------|
| Mitglieder bearbeiten | Ja | Nein | Nein |
| Kurse verwalten | Ja | Ja | Nein |
| Ausrüstung verwalten | Ja | Nein | Ja |
| CMS bearbeiten | Ja | Nein | Nein |

# 5. Mitgliederverwaltung
## 5.1 Mitgliederstammdaten

Erfassung:
- Mitgliedsnummer
- Vorname
- Nachname
- Geburtsdatum
- Geschlecht
- Anschrift
- Telefon
- Mobil
- E-Mail
- Eintrittsdatum
- Austrittsdatum
- Bankverbindung
- Notfallkontakte

## 5.2 Mitgliedsarten
- Aktives Mitglied
- Passives Mitglied
- Jugendmitglied
- Ehrenmitglied
- Familienmitglied

## 5.3 Verbandsdaten
- Tauchschein
- Brevetierungsstufe
- Verband
- Taucherpassnummer
- Lizenznummer

## 5.4 Dokumentenverwaltung

Speicherung von:
- Aufnahmeantrag
- SEPA-Mandat
- Tauchtauglichkeit
- Datenschutzerklärung
- Ausbildungsnachweise

# 6. Spartenverwaltung
## 6.1 Sparten**

Beliebig viele Sparten:
- Gerätetauchen
- Ausbildung
- Unterwasser-Rugby
- Flossenschwimmen
- Apnoe
- Triathlon
- Jugendgruppe

Jede Sparte besitzt:
- Name
- Beschreibung
- Logo
- Spartenleiter
- Stellvertreter

## 6.2 Mannschaftsverwaltung

Je Sparte konfigurierbar:
- Mannschaften aktiv/inaktiv
- mehrere Mannschaften pro Sparte

Beispiel:

Unterwasser-Rugby:
- Mannschaft
- Jugendmannschaft

# 7. Turniermanagement
## 7.1 Turniere**

Anlage von:
- Vereinseigenen Turnieren
- Externen Turnieren

Erfassung:
- Name
- Austragungsort
- Zeitraum
- Ausrichter
- Regeln
- Teilnehmer

## 7.2 Mannschaftsteilnahmen

Dokumentation:
- Anmeldung
- Kader
- Ergebnisse
- Platzierungen
- Statistiken

# 8. CMS-System
## 8.1 Öffentliche Inhalte**

Startseite
Sparten
News
Kurse
Veranstaltungen
Vereinsvorstellung

## 8.2 Interne Inhalte

Nur für Mitglieder:
- Protokolle
- Vereinsinformationen
- Dokumente
- Rundschreiben

## 8.3 Kategorien

Inhalte müssen kategorisiert werden können.

Zuordnung zu:
- Sparte
- Verein
- Ausbildung
- Veranstaltung

## 8.4 Artikelverwaltung

Funktionen:
- Entwurf
- Veröffentlichung
- Archivierung
- Versionierung

## 8.5 Bildergalerien
Mehrere Bilder pro Galerie
Tags
Kategorien
Sparte

# 9. Veranstaltungsmanagement
## 9.1 Veranstaltungen

Beispiele:
- Clubabend
- Vereinsfest
- Mitgliederversammlung
- Vereinsfahrt
- Grillfest

## 9.2 Anmeldungen

Konfigurierbar:
- Keine Anmeldung erforderlich
- Nur Mitglieder
- Mitglieder und Gäste

## 9.3 Teilnehmermanagement
- Teilnehmerliste
- Warteliste
- Check-In
- Auswertung

# 10. Clubheimverwaltung
## 10.1 Clubabende

Planung von:
- regelmäßigen Clubabenden
- Sonderveranstaltungen

Anzeige:
- geplant
- offen
- besetzt
- abgesagt

## 10.2 Clubheim-Dashboard

Der Clubheimverwalter sieht:
- kommende Clubabende
- offene Dienste
- Auslastung
- Belegung

# 11. Thekendienstverwaltung
## 11.1 Dienstplanung

Mitglieder können:
- freie Dienste übernehmen
- Dienste tauschen
- Tauschgesuche einstellen

## 11.2 Thekenkasse

Führung einer separaten Kasse.

Erfassung:
- Einnahmen
- Ausgaben
- Tagesabrechnungen

Auswertungen:
- Monatsabschluss
- Jahresabschluss

# 12. Ausbildungsverwaltung
## 12.1 Kurse

Beispiele:
- Schnuppertauchen
- Grundtauchschein
- CMAS *
- CMAS **
- Spezialkurse
- 12.2 Kursstruktur
- Kursleiter
- Ausbilder
- Schüler
- Termine
- Inhalte

## 12.3 Gastanmeldungen

Nicht-Mitglieder können:
- Kurse online buchen
- Profil anlegen
- Kursstatus einsehen
- Dokumente hochladen

# 13. Gastverwaltung

Separater Bereich für Gäste.

Erfassung:
- Stammdaten
- gebuchte Kurse
- Zahlungen
- Ausbildungsstatus

Selbstserviceportal:
- Login
- Kursfortschritt
- Dokumentenupload

# 14. Ausbildungs-Dashboard

Der Ausbildungsleiter erhält ein Dashboard mit:
- laufenden Kursen
- freien Kursplätzen
- Anzahl Teilnehmer
- Erfolgsquoten
- Auslastung der Ausbilder
- Ausbildungsstatistiken

KPIs:
- aktive Kurse
- Schüler je Ausbilder
- Ausbildungsstunden
- Wartelisten

# 15. Geräteverwaltung
Führung einer separaten Kasse.

Erfassung:
- Einnahmen
- Ausgaben
- Tagesabrechnungen

Auswertungen:
- Monatsabschluss
- Jahresabschluss

## 15.1 Vereinsausrüstung

Verwaltung von:
- Flaschen
- Atemreglern
- Jackets
- Anzüge
- Kopfhauben
- Handschuhe
- Kompressoren
- O2-Koffern
- ABC-Equipment
- Leihequipment

## 15.2 Private Ausrüstung

Mitglieder dürfen eigene Ausrüstung erfassen:
- Flaschen
- Atemregler
- Jackets
- Anzüge
- Flossen
- Computer
- Lampen

# 16. Wartungsmanagement
## 16.1 Wartungszyklen

Frei konfigurierbar.

Beispiele:

Gerät	        Intervall
Tauchflasche	2,5 Jahre
Atemregler	    1 Jahr
Kompressor	    1 Jahr

## 16.2 Erinnerungen

Automatische Benachrichtigung:
- E-Mail
- Push-Mitteilung
- Dashboard-Hinweis

# 17. Flaschenprüfungsmanagement
## 17.1 Prüfaktionen

Der Gerätewart kann Prüfaktionen anlegen:
- Prüfdatum
- Dienstleister
- Annahmeschluss
 
## 17.2 Preisverwaltung
Berücksichtigt werden sollen alle gängigen Flaschengrößen.

Preislisten:
| Größe | Preis |
|------|------|
| 1 Liter | Betrag |
| 2 Liter | Betrag |
| 3 Liter | Betrag |
| 5 Liter | Betrsg |
| 4 Liter | Betrag |
| 7 Liter | Betrag |
| 8 Liter | Betrag |
| 10 Liter | Betrag |
| 12 Liter | Betrag |
| 15 Liter | Betrag |
| 40 cft  | Betrag |
| 80 cft | Betrag |

# 17.3 Anmeldung

Mitglieder melden eigene Flaschen an.

Workflow:
- Anmeldung
- Abgabe
- Bezahlung
- Prüfung
- Rückgabe

## 17.4 Kompressorbetrieb
Kompressor-Füllbuch gemäß Betriebssicherheitsverordnung

FÜhren einer separaten Kasse

Erfassung:
- Einnahmen (Verleih, Flasachenfüllungen, etc.)
- Ausgaben
- Tagesabrechnungen

Auswertungen:
- Monatsabschluss
- Jahresabschluss

# 18. Benachrichtigungssystem

Versand über:
- E-Mail
- Push-Nachrichten
- interne Mitteilungen

Automatische Erinnerungen:
- Veranstaltungen
- Kurse
- Wartungen
- Dienste
- Mitgliedschaften

# 19. Reporting und Dashboard

Zentrale Dashboards für:
- Vorstand
- Kassenwart
- Ausbildungsleiter
- Gerätewart
- Spartenleiter
- Clubheimverwalter

Auswertungen:
- Mitgliederentwicklung
- Kursstatistiken
- Vereinsaktivitäten
- Gerätebestand
- Veranstaltungen
- Finanzkennzahlen

# 20. DSGVO- und Compliance-Anforderungen
## 20.1 Datenschutz

Pflichtfunktionen:
- Einwilligungsmanagement
- Protokollierung von Einwilligungen
- Widerrufsverwaltung
- Löschkonzept
- Sperrkonzept

## 20.2 Betroffenenrechte

Unterstützung von:
- Auskunftsersuchen
- Datenexport
- Datenkorrektur
- Löschung
- Einschränkung der Verarbeitung

## 20.3 Sicherheit
- TLS-Verschlüsselung
- Passwort-Hashing
- Zwei-Faktor-Authentifizierung
- Rollenbasierte Zugriffe
- Audit-Logs

## 20.4 Revisionssicherheit

Protokollierung von:
- Stammdatenänderungen
- Rollenänderungen
- Kursänderungen
- Geräteänderungen
- Finanzbuchungen

## 21. Nichtfunktionale Anforderungen
- Performance
- Antwortzeiten < 2 Sekunden
- Verfügbarkeit
- 99,5 % Jahresverfügbarkeit
- Skalierbarkeit
- mindestens 5.000 Mitglieder
- mindestens 50 Sparten
- mindestens 100.000 Dokumente
- Benutzerfreundlichkeit
- Responsive Design
- WCAG 2.1 AA
- Dark Mode
- moderne Benutzeroberfläche
- Mehrsprachigkeit

## 22. Zukünftige Erweiterungen

- Mobile App (iOS/Android)
- digitale Mitgliedskarte
- QR-Code Check-In
- Online-Bezahlung (PayPal, Stripe, SEPA)
- Verbandsanbindung (VDST/CMAS)
- elektronische Ausbildungsnachweise
- E-Learning-Plattform
- KI-gestützte Auswertungen
- Integration von Vereinsfahrzeugen und Bootsverwaltung
- Tauchplatz- und Tauchgangsverwaltung

Dieses Lastenheft beschreibt eine vollwertige Vereinsmanagement-Plattform, welche die Anforderungen eines modernen Tauchvereins mit mehreren Sparten, Ausbildungsbetrieb, Mannschaftswesen, Geräteverwaltung und Vereinskommunikation vollständig abdeckt und gleichzeitig für zukünftige Erweiterungen skalierbar ausgelegt ist.
