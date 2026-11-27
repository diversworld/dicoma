# Rechte- und UX-Prüfung Verwaltung

Stand: 26.11.2026

## Festgestellter Rechtefehler

Die direkte `ROLE_TREASURER` erhielt über `ModuleAccess` Lesen und Schreiben für
`finance` sowie `accounting`. Der EasyAdmin-Menüpunkt „Mitgliedsfinanzen“ wurde
jedoch nur bei aktiviertem Funktionsmodell berechnet. Dadurch konnten berechtigte
Kassenwarte die Route aufrufen, sahen sie aber nicht in der Navigation.

## Korrektur

Die Menüanzeige und Controllerberechtigung verwenden jetzt dieselben Modulprüfungen:

- `finance:read`: Mitgliedsfinanzen
- `accounting:read`: Buchhaltung & Jahresabschluss
- `finance:edit`: Beitragsarten & Regeln

Die Kassenwart-, Vorstand-, Administrator- und Funktionsrollenpfade bleiben
getrennt. Zusätzliche Berechtigungen werden nicht durch die Menüänderung
gewährt. Kassenwart, Schriftführung und Vorstand werden nach direkter
Authentifizierung ins administrative Dashboard geleitet.

Regressionstest:
`Phase10FinanceAccessHttpTest::testDirectTreasurerRoleShowsAllEntitledMenuEntriesWithoutFunctionModel`

## UX-Maßnahmen

Gemeinsame Admin-UX-Stile liegen in `public/css/admin-ux.css`:

- größere und konsistent ausgerichtete Eingabefelder
- sichtbare Fokusrahmen
- Karten mit klarer Gruppierung
- Aktionsbereiche unter Formularen
- responsive Tabellen
- mobile Seitenaktionen in voller Breite
- kompakte Details nur für selten verwendete Vorgänge

Angepasste Nicht-EasyAdmin-Formulare:

- Mitgliedsfinanzen und SEPA
- Buchhaltung und Jahresabschluss
- Mitglieder- und SEPA-Aktionen
- Beitragsarten und Vereinsregeln
- Kommunikation
- Audit und Datenschutz
- Sitzungsbeschlüsse und Dokumentupload

## UX-Prinzipien

- Pflichtangaben und primäre Aktion bleiben im sichtbaren Bereich.
- Seltene oder irreversible Vorgänge stehen in beschrifteten Details bzw. eigenen Karten.
- Finanzbeträge, Status und Warnungen sind nicht ausschließlich farbcodiert.
- Formulardaten bleiben nach Validierungsfehlern erhalten.
- Dateien, Freigaben und irreversible Finanzaktionen besitzen erläuternden Hilfetext.
- Mobile Tabellen bleiben horizontal scrollbar statt Inhalte zu beschneiden.

## Verifikation

Automatisierte Prüfung umfasst Rollen-/Routenzugriff, Kassenwart-Menü,
Loginziel, Formularsyntax, Container und den Accessibility-Smoke-Test.
Manuelle WCAG-Expertenfreigabe sowie reale Vorstands-/Kassenwart-Abnahme bleiben
separate produktive Nachweise.
