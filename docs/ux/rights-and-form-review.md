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

Die früheren Kassenwart-, Schriftführer- und Vorstand-Sonderzweige wurden
entfernt. Dadurch werden zusätzliche Modulrechte einer Vereinsfunktion nicht mehr
nach dem ersten passenden Rollenlabel abgeschnitten.

Regressionstests:
- `Phase10FinanceAccessHttpTest::testDirectTreasurerRoleShowsAllEntitledMenuEntriesWithoutFunctionModel`
- `Phase10FinanceAccessHttpTest::testFunctionMenusFollowEveryEffectiveModulePermissionForTreasurerBoardSecretaryAndPress`

## UX-Maßnahmen

Das Standard-EasyAdmin-Design bleibt unverändert. Änderungen erfolgen nur lokal
über vorhandene EasyAdmin-/Bootstrap-Klassen in Nicht-CRUD-Seiten:

- semantische Karten und bestehende Card-Header
- responsive Zeilen/Spalten und Tabellencontainer
- bestehende `form-control`, `form-select`, `form-label` und `form-text`
- Standard-Buttonvarianten und EasyAdmin-Farben

Geprüfte Nicht-EasyAdmin-Formulare:

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
- mobile Tabellen bleiben horizontal scrollbar statt Inhalte zu beschneiden.

## Verifikation

Automatisierte Prüfung umfasst Rollen-/Routenzugriff, Kassenwart-Menü,
Loginziel, Formularsyntax, Container und den Accessibility-Smoke-Test.
Manuelle WCAG-Expertenfreigabe sowie reale Vorstands-/Kassenwart-Abnahme bleiben
separate produktive Nachweise.

## Redaktion und Veranstaltungen

Seiten, Artikel und operative Veranstaltungen besitzen getrennte Einstiege:

- „Artikel & Neuigkeiten“
- „Website-Seiten“
- „Veranstaltungen & Buchungen“

Das Seitenformular enthält Hierarchie, Menüreihenfolge und Sichtbarkeit. Das
Artikelformular enthält nur Artikelinhalt und optionale Seitenzuordnung.
Veranstaltungsdaten werden ausschließlich im Veranstaltungsformular gepflegt.

Die Übersicht „Veranstaltungen & Buchungen“ lädt sowohl operative `ClubEvent`-
Datensätze als auch bestehende redaktionelle Altveranstaltungen. Dadurch fehlt
keine Veranstaltung mehr je nach verwendeter Einstiegsseite. Altveranstaltungen
werden klar als solche gekennzeichnet und verlinken auf ihr separates
Legacy-Formular; neue buchbare Veranstaltungen entstehen nur im operativen
Veranstaltungsworkflow.
