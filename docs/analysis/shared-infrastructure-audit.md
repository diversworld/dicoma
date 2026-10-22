# Phase 1 – Gemeinsame Infrastruktur: Kalender, Benachrichtigungen, Zahlungen

**Quelle:** unverändertes Originalarchiv `dicoma2110.zip`. **Methode:** statische Inspektion ausgewählter Entities, Controller und Services. **Keine** Symfony-Laufzeittests und keine produktiven Änderungen.

## Belastbare Ergebnisse

**Kalender:** Ein administrativer Kurskalender unterstützt Monats-, Wochen- und Tagesansichten (`CourseCalendarController.php:63–97`). Termine lassen sich per POST mit CSRF-Prüfung duplizieren, ohne Teilnehmerbuchungen zu übernehmen (`:23–54`). `ScheduleConflictService` ermittelt Überschneidungen derselben Lehrkraft (`:23–105`); seine Kandidatenabfrage besitzt keinen sichtbaren Vereinsfilter. Der öffentliche `ScheduleController` lädt Termine per `findAll()` (`:18–25`), weshalb die fachliche Sichtbarkeit separat zu prüfen ist.

**Benachrichtigungen:** Eine persistente TÜV-spezifische Benachrichtigungs-Entity besitzt Deduplizierungsschlüssel, Status und Versuchszähler (`TuvNotification.php:5–21`). `TuvNotificationSender` verwendet eine Dateisperre und speichert Versandzustände (`:23–54`). Der Admin-Sofortversand erfordert POST und CSRF (`TuvNotificationSendNowController.php:15–36`). Ein allgemeines Erinnerungs-/Benachrichtigungssystem für Thekendienste, Veranstaltungen und Clubreisen ist dadurch **nicht** belegt. Eine Dateisperre auf lokalem Dateisystem ist für verteilte Deployments gesondert zu beurteilen.

**Zahlungen:** TÜV-Rechnungen und -Zahlungsbelege (`TuvInvoice`, `TuvPaymentReceipt`) sowie vereinsbezogene Gerätewartkassen-Auszahlungen und -Abschlüsse (`EquipmentCashPayout`, `EquipmentCashClosing`) sind vorhanden. Diese Komponenten sind fachlich spezialisiert. Ihre Wiederverwendung für Clubreisen oder Veranstaltungen erfordert eine bewusste Architekturentscheidung und darf nicht als fertige allgemeine Zahlungsverwaltung gewertet werden.

## Prüffragen und Abnahmetests

1. Sichtbarkeit: Welche Termine dürfen Gäste, Mitglieder und Vereinsadministratoren sehen? HTTP-Tests mit zwei Vereinen und einem Gastkonto.
2. Terminüberschneidungen: Gleiche Lehrkraft und überlappende Zeiten müssen erkannt werden; verschiedene Lehrkräfte nicht. Mandantengrenzen ausdrücklich prüfen.
3. Benachrichtigungen: Doppelte Auslösung, SMTP-Fehler, erneuter Versuch und gleichzeitiger Versand testen; bei mehreren Servern Sperrstrategie prüfen.
4. Zahlungen: Teilzahlungen, Storno, Rückzahlung und Kassenabschluss für bestehende TÜV-/Verleihfälle prüfen; Event-/Reiseanforderungen gesondert abgleichen.
5. Architektur-Gate: Erst nach Fachentscheidung über globales/vereinsbezogenes `ROLE_ADMIN` dürfen objektbezogene Autorisierungsregeln finalisiert werden.

## Fazit

Es gibt **wiederverwendbare Teilkomponenten**, aber keinen belastbaren Nachweis einer bereits vollständigen, modulübergreifenden Infrastruktur für Veranstaltungen, Clubreisen und Thekendienste. Die 291 Anforderungen bleiben bis zur individuellen Verifikation offen. Phase 1 ist nicht abnahmefähig.

**Referenz für spätere Tests:** https://symfony.com/doc/current/testing.html ; **Autorisierung:** https://symfony.com/doc/current/security/voters.html
