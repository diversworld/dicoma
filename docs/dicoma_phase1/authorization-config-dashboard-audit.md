# DICOMA – Phase 1: Rollen, Doctrine-Filter und Dashboard-Zugriffe

**Basis:** unverändertes Originalarchiv `dicoma2110.zip`; statische Quellcodeprüfung am 21.10.2026. Keine Laufzeit- oder Angriffstests.

## Zusammenfassung

Die Sicherheitskonfiguration sieht `ROLE_ADMIN` als übergeordnete Rolle vor. In `access_control` wird `/admin` anhand dieser Rolle freigegeben. Eine Zuordnung des Zugriffs zu einem bestimmten Verein ist in dieser Konfiguration nicht erkennbar. In `config/packages/doctrine.yaml` ist kein globaler Doctrine-ORM-Filter deklariert. Die zwei Klassen unter `src/Filter/` sind **EasyAdmin-Oberflächenfilter**, keine globalen Doctrine-Tenant-Filter. Damit fehlt in den untersuchten Konfigurationen ein Nachweis einer automatischen Vereinsbegrenzung. Das schließt andere Einschränkungen in Repositories, Controllern oder Event-Listenern nicht aus.

## Nachgewiesene Befunde

| ID | Befund | Einschätzung | Quelle |
|---|---|---|---|
| AUTH-01 | `ROLE_ADMIN` erbt Vorstand und Kassenwart; keine Vereinsdimension in der Rollen-Hierarchie. | Rollenmodell zu klären | `security.yaml:5–22` |
| AUTH-02 | `/admin` ist für `ROLE_ADMIN` freigegeben; keine Club-Einschränkung in `access_control`. | Bedingtes Mandantenrisiko | `security.yaml:88–92` |
| AUTH-03 | `ROLE_MEMBER` als Fallback ist kein echter Mitgliedschaftsnachweis, da `User::getRoles()` diese Rolle ergänzt. | Bestätigter Designfehler | `security.yaml:142–146`, `User.php:108–114` |
| AUTH-04 | Keine globalen ORM-Filter in der untersuchten Doctrine-Konfiguration deklariert. | Fehlende zusätzliche Schutzschicht, nicht allein Schwachstelle | `doctrine.yaml:10–26` |
| AUTH-05 | Rechnungsübersicht fragt alle Clubs per `findBy([])` ab. | Vereinsübergreifende Datenliste bei `ROLE_ADMIN` nachgewiesen | `DashboardController.php:195–203` |
| AUTH-06 | Rechnungseinstellungen werden für eine frei gewählte Club-ID geladen und bei gültigem CSRF gespeichert; keine explizite Club-Autorisierung in dieser Methode. | Hoch, **falls** Admin-Rechte vereinsgebunden sein sollen | `DashboardController.php:205–235` |
| AUTH-07 | Bestätigungsliste fragt Datensätze ohne Vereinsfilter ab. | Bedingtes Offenlegungsrisiko | `DashboardController.php:238–246` |
| AUTH-08 | `TrainingAssessmentVoter` erlaubt Admins Beurteilung aller nicht abgeschlossenen Kurse, Ausbildern nur eigene Termine. | Beabsichtigte globale Admin-Regel oder Mandantenrisiko | `TrainingAssessmentVoter.php:30–48` |

## Differenzierung

**Bestätigt:** Die genannten Methoden enthalten die beschriebenen Abfragen und Prüfungen. **Nicht bestätigt:** Ob `ROLE_ADMIN` fachlich global sein soll, ob weitere Security-Listener die Requests einschränken und ob tatsächlich unerlaubte Zugriffe zwischen Vereinen möglich sind. Deshalb keine Aussage „ausnutzbare Sicherheitslücke“ ohne Rollenvertrag und Integrationstest.

## Entscheidung für die Fachabnahme

1. Festlegen, ob `ROLE_ADMIN` **global** oder **vereinsgebunden** ist und welche Aufgaben `ROLE_SUPER_ADMIN` exklusiv übernimmt.
2. Falls vereinsgebunden: `invoiceSettingsOverview`, `invoiceSettingsEdit`, `invoiceAgencyConfirmations` und den Training-Voter mit Objekt-/Club-Prüfungen absichern; auch EasyAdmin-CRUD-Lesen, Editieren und Löschen abdecken.
3. Symfony-typisch objektbezogene Regeln über **Security Voters** und `denyAccessUnlessGranted()` implementieren; Abfragen zusätzlich auf zulässige Vereine einschränken. Ein Voter ersetzt keinen Query-Filter für Listen.
4. Integrationstests mit zwei Vereinen, zwei Admin-Konten und einem globalen Super-Admin definieren; erlaubte und abgelehnte Lese- und Schreibzugriffe testen.
5. Erst nach Freigabe in Phase 2 produktive Änderungen vornehmen.

## Teststatus

Nur statische Quellcodeanalyse. Symfony-Kernel, Doctrine-Schema und HTTP-Integrationstests wurden hier nicht ausgeführt.
