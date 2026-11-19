# DICOMA – Phase 5 – Abschlussbericht

## 1. Status

**Technisch abgeschlossen; fachliche Abnahme noch erforderlich.**

Die Mitgliederportal-, Kalender- und Kommunikationsanforderungen sind
implementiert und durch isolierte Integrationstests abgesichert. Andere
Installationen benötigen vor Produktivsetzung ihre übliche fachliche und
betriebliche Abnahme.

## 2. Implementierte Änderungen

- Der Mitgliederbereich zeigt aktive, passive und pausierte
  Spartenzugehörigkeiten aus `MemberSport`; beendete oder inaktive Sparten
  werden nicht dargestellt.
- Die persönliche Startseite zeigt ungelesene interne Nachrichten und verlinkt
  Posteingang, neuen Versand und persönlichen Kalender.
- Der persönliche Kalender enthält nur eigene Buchungen, eigene
  Veranstaltungsanmeldungen und persönlich zugewiesene Ausbildungstermine der
  kommenden zwölf Monate.
- Ein interner Posteingang speichert Nachrichten, explizite Empfänger und
  Lesestatus. Fremde Mitglieder erhalten keinen direkten oder gelisteten
  Zugriff.
- Aktive Mitglieder können Direktnachrichten an aktive Mitglieder des eigenen
  Vereins senden.
- Spartenleiter können Nachrichten nur an aktive Mitglieder ihrer aktuell
  geführten Sparte senden. Vereinsadministration und Vorstand können
  vereinsweite Spartenversände ausführen.
- Administratoren und Superadministratoren können Mitglieder anhand einer
  wirksamen Rollenfunktion adressieren.
- Für jeden Empfänger mit gültiger E-Mail-Adresse wird eine Benachrichtigung
  im vorhandenen ausgehenden Mitgliedschafts-Mailsystem vorbereitet. Die
  interne Nachricht selbst ist unabhängig vom E-Mail-Versand sichtbar.
- Portalnavigation und Dashboard wurden um Kalender und Nachrichten ergänzt.

## 3. Geänderte Dateien

- `src/Entity/InternalMessage.php`: Nachricht, Sender, Verein, Empfängerart und
  optionales Sparte-/Rollenziel.
- `src/Entity/InternalMessageRecipient.php`: explizite Zustellung und Lesestatus.
- `src/Service/InternalMessaging.php`: Empfängerauflösung, Sendegrenzen,
  Sparten-/Rollenberechtigung und E-Mail-Outbox.
- `src/Controller/InternalMessageController.php`: Posteingang, Detailansicht
  und geschütztes Versandformular.
- `src/Controller/MemberPortalController.php`: Sparten, ungelesene Nachrichten
  und persönlicher Kalender.
- `src/Service/PortalNavigation.php`: neue persönliche Navigation.
- `templates/member_portal/dashboard.html.twig`: Portal-Karten.
- `templates/member_portal/calendar.html.twig`: persönlicher Kalender.
- `templates/member_portal/messages/`: Posteingang, Versand und Detail.
- `migrations/Version20261119090000.php`: Tabellen und Fremdschlüssel.
- `tests/Integration/Phase5CommunicationHttpTest.php`: Portal-, Versand- und
  Zugriffstests.

## 4. Datenbank

Die Migration erstellt:

- `internal_message`
- `internal_message_recipient`

Empfänger sind pro Nachricht eindeutig. Nachrichten löschen ihre Empfänger
per `CASCADE`; Verein, Absender und Mitglieder bleiben durch `RESTRICT`
geschützt. Die Migration wurde in isolierter MariaDB vorwärts, rückwärts und
erneut vorwärts ausgeführt und ist auch auf der lokalen DDEV-Datenbank
eingespielt.

## 5. Sicherheit

- Alle Nachrichtenrouten verlangen `ROLE_MEMBER`.
- Detailzugriff erfolgt ausschließlich über eine `InternalMessageRecipient`-
  Zuordnung zum angemeldeten Mitglied; nicht zugeordnete Nachrichten liefern
  404.
- Direkt-, Sparten- und Rollenempfänger werden unabhängig vom Formular
  serverseitig auf aktiven Status und Vereinszugehörigkeit geprüft.
- Spartenversand prüft eine aktuelle `DivisionRoleAssignment` mit
  `DivisionRole::LEADER`.
- Rollenversand ist auf die Vereinsverwaltung beschränkt und basiert auf
  wirksamen Benutzerrollen beziehungsweise Funktionszuordnungen.
- Manipulierte vereinsfremde oder nicht angebotene Formularwerte werden
  abgewiesen.
- Nachrichtentext wird bei der Ausgabe escaped und nur über sichere
  Zeilenumbrüche dargestellt.
- E-Mail-Benachrichtigungen enthalten keine vertraulichen Nachrichtentexte.

## 6. Tests

Ausgeführt:

- PHP-Syntaxprüfung aller neuen PHP-Dateien: bestanden.
- `lint:container` für Test- und Produktivprofil: bestanden.
- `doctrine:schema:validate`: Phase-5-Mapping und Tabellen sind synchron; die
  bereits bekannte, vorbestehende Abweichung bei `tank` bleibt außerhalb von
  Phase 5 bestehen.
- `lint:twig templates/member_portal`: 7 Templates gültig.
- Migration auf isolierter MariaDB: Dry-run, Up, Down und erneutes Up bestanden.
- `Phase5CommunicationHttpTest`: 4 Tests mit Direct-, Division-, Role-,
  Kalender-, Dashboard-, Outbox-, Fremdvereins- und Fremdlese-Negativfällen.
- Fokussierte bestehende Portalregressionen:
  - Mitgliedsdaten-Self-Service: 1 Test, 12 Assertions.
  - Dashboardübersetzungen: 1 Test, 61 Assertions.
  - Eigene Buchungen: 1 Test, 44 Assertions.

Die alte, breitere Suite mit direkt gesetzten Legacy-Rollen ist in der
aktivierten Funktionsmodell-Konfiguration nicht vollständig grün. Die
Fehlbilder betreffen vorhandene Testfixtures, die wirksame Rollen weiterhin
nur direkt im Benutzer ablegen, und wurden nicht als Phase-5-Freigabe gewertet.

## 7. Bekannte Einschränkungen

- Interne Nachrichten unterstützen aktuell eine Textantwort über eine neue
  Direktnachricht, jedoch keinen Thread- oder Antwortbaum.
- Anhänge sind nicht Teil der Phase.
- E-Mail wird vorbereitet, nicht synchron beim Nachrichtenversand versendet.
- Der Kalender deckt Kurstermine, Buchungen, Veranstaltungsanmeldungen und
  Ausbildungszuweisungen ab; Thekendienste bleiben im separaten Dienstkalender.
- Die produktive Fachabnahme der Empfängerliste und Gruppensende-Regeln steht
  aus.

## 8. Inbetriebnahme

1. Vor Deployment Datenbank und private Dateien sichern und Restore prüfen.
2. Migration `Version20261119090000` ausführen.
3. Cache für die Zielumgebung leeren.
4. Täglichen oder regelmäßig eingeplanten Job mit bestehendem
   `app:membership:daily --send` konfigurieren, sobald ein gültiger
   `MEMBERSHIP_MAIL_FROM`, Transport, Monitoring und Retry-Freigabe vorliegen.
5. Posteingang, direkten Versand, Spartenleitung und Rollenempfänger fachlich
   mit mindestens zwei Mitgliedern und einem Fremdverein prüfen.

## 9. Abnahmekriterien

- Mitgliederportal: erfüllt, bestehende Self-Service-Funktion erhalten.
- Persönliche Spartenzugehörigkeiten: erfüllt.
- Persönlicher Kalender: erfüllt.
- Eigene Buchungen und Kurse: bestehende Seiten erhalten und im persönlichen
  Kalender um die buchungsbezogenen Termine ergänzt.
- Internes Nachrichtensystem: erfüllt.
- Spartenspezifische Mitteilungen: erfüllt.
- Rollenbezogene Kommunikation: erfüllt.
- E-Mail-Benachrichtigungen: als sichere Outbox-Vorbereitung erfüllt;
  tatsächlicher Versand bleibt betriebliche Inbetriebnahme.
- Leserechte und Empfängergruppen: erfüllt und negativ getestet.

## 10. Nächster Schritt

Fachliche Abnahme der Empfängerregeln und der Portaloberfläche durchführen.
Phase 6 darf erst nach ausdrücklicher Freigabe begonnen werden.
