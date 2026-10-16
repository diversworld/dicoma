# Schritt 50 – Integrations- und Berechtigungsabnahme

Diese Tests sind **manuell auf einer Staging-Instanz** mit anonymisiertem Testkurs und Testmitgliedern auszuführen. Die PHPUnit-Unit-Tests ersetzen keine Symfony-/Datenbank-Integrationstests.

| Test | Erwartung |
| --- | --- |
| Anonym: GET `/admin/course-participant/1/pass` | Login-Weiterleitung oder 403, keine Änderung |
| ROLE_MEMBER: GET und POST zur Pass-Route | 403, keine Änderung |
| ROLE_INSTRUCTOR ohne Admin: GET und POST zur Pass-Route | 403, keine Änderung |
| ROLE_ADMIN: GET Pass-Route | Bestätigung, kein Statuswechsel und keine Qualifikation |
| ROLE_ADMIN: POST ohne/mit ungültigem `_token` | 403, keine Änderung |
| ROLE_ADMIN: POST mit gültigem Token, Pflichtnachweise fehlen | Fehlermeldung, keine Qualifikation |
| ROLE_ADMIN: POST mit gültigem Token, vollständig | Status PASSED, Qualifikation, Audit-Ereignis in einer Transaktion |
| Zweiter POST auf bestandenen Teilnehmer | 403, keine weitere Qualifikation |
| POST auf Teilnehmer eines abgeschlossenen Kurses | 403 |
| Kursabschluss per GET | Keine Statusänderung |
| Kursabschluss per POST ohne Token | 403 |
| Kursabschluss per gültigem POST | Status COMPLETED, Audit-Ereignis |
| Wiederöffnung ohne Begründung | Keine Änderung |
| Wiederöffnung mit Begründung und Token | Status RUNNING, Begründung in Audit-Ereignis |
| Wizard: PASSED | Qualifikation und Audit-Ereignis atomar |
| Wizard: FAILED/CANCELLED | Status und Audit-Ereignis atomar |
| Anonym/Member: `/admin/course/1/history` | Login/403 |
| Admin: `/admin/course/1/history` | Nur Ereignisse des angefragten Kurses |
| Altaktion `passCourse` über GET | Nur Weiterleitung auf Bestätigung, kein Abschluss |
| Mitglieder-Frontend, Instruktor-Frontend, EasyAdmin | Logout und Zugriffsgrenzen unverändert |

Weitere Regressionen: Kursanmeldung, Terminbuchung, Anwesenheit, Sammelbewertung, Ausbildungsmatrix, Kurstermine erzeugen, Ausbildungsplan importieren, Kurs wieder öffnen. Vor der Freigabe `doctrine:schema:validate` und `debug:router` ausführen.

Hinweis: Vorhandene direkte `CourseParticipant`-CRUD-Statusänderungen und andere ältere Schreibpfade sind nicht automatisch Teil des neuen Audits. Für eine **lückenlose** Historie ist ein zentrales Domain-Event/Doctrine-Listener-Konzept mit klaren Berechtigungsregeln erforderlich.
