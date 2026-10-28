# Rollen und Berechtigungen – bestätigter Phase-2-Vertrag

Die vereinsgebundenen Adminrechte und die hier beschriebenen Superadmin-Beschränkungen wurden am 24.10.2026 im laufenden Auftrag ausdrücklich fachlich freigegeben. Die Prüfung betroffener Konten vor einem Produktivwechsel bleibt erforderlich.

| Rolle | Bedeutung |
|---|---|
| `ROLE_USER` | Basis eines Benutzerkontos; begründet keine Vereinsmitgliedschaft. |
| `ROLE_PRESS_OFFICER` | Pressewart: Artikel und öffentliche Veranstaltungsankündigungen im konfigurierten Website-Verein pflegen und freigeben; vom Vorstand geerbt. Keine sonstigen Adminrechte. |
| `ROLE_MEMBER` | Abgeleitet aus vorhandenem Member-Profil, nicht leerem Verein, Status `ACTIVE` und fehlendem Austrittsdatum. |
| Funktionsrollen | Ausbilder, Gerätewart, Kompressorbediener, Kassenwart und Vorstand werden ausdrücklich vergeben. Sie machen ein inaktives Mitglied nicht automatisch aktiv. |
| `ROLE_ADMIN` | Verwaltung im Verein von `User.member.club`; ohne gültigen Kontext keine vereinsbezogenen Verwaltungsdaten. |
| `ROLE_SUPER_ADMIN` | Bewusst vereinsübergreifende Verwaltung einschließlich zentraler Personen und Benutzer-/Rollenverwaltung. |

Ein in der Datenbank gespeichertes `ROLE_MEMBER` allein gewährt keine Mitgliedsrechte. `getStoredRoles()` erhält die ursprünglichen Rollendaten; `getRoles()` berechnet die effektive direkte Rollenliste. `ROLE_MEMBER` wird nicht über die Funktionsrollen-Hierarchie vererbt. Die bestehenden fachlichen Hierarchien Vorstand -> Funktionsrollen, Admin -> Vorstand/Kassenwart und Superadmin -> Admin bleiben erhalten.

Separat geschützte Ausbilder- und Gerätewartportalrouten erhalten die passende Funktionsrolle als Zugangsvoraussetzung, auch wenn keine aktive Mitgliedschaft mehr besteht. Die jeweiligen Controller müssen zusätzlich den benötigten Personen-/Vereinsbezug prüfen. Mitgliederspezifische Routen wie `/my` bleiben solchen Konten ohne aktive Mitgliedschaft verwehrt.

## Verbindliche Objektprüfung

Die Backend-Redaktion `/admin/{_locale}/editorial` verlangt `ROLE_PRESS_OFFICER` und denselben
Verein wie `PUBLIC_SITE_CLUB_ID`. Vorstand, Admin und Superadmin erben
die Rolle, aber auch Superadmins umgehen in diesem Bereich nicht die
Website-Vereinsgrenze. Die Rolle kann separat an einen Pressewart vergeben
werden; aktive Mitgliedsrechte oder EasyAdmin-Zugang werden dadurch nicht
zusätzlich verliehen. Nur eigene freigegebene Kurse/Turniere sind als
öffentliche Veranstaltung verknüpfbar. Gewöhnliche Mitglieder,
Thekenleitung und Ausbilder ohne Redaktionsrolle können keine Artikel
oder Veranstaltungsankündigungen erstellen oder freigeben.

Die EasyAdmin-Navigation bietet berechtigten Nutzern einen Einstieg unter
„Website & Redaktion“. Auch ein Pressewart ohne `ROLE_ADMIN` kann ausschließlich
die Redaktionsrouten innerhalb des EasyAdmin-Backends öffnen; die Redaktionsrolle
verleiht keinen Zugriff auf andere Backend-Routen. Die öffentliche Navigation
enthält keinen Redaktionslink.

- Routenrollen ersetzen keine Objektberechtigung.
- Member-/Kurs-Voter vergleichen persistierte Vereins-IDs.
- Reguläre Admins können keine zentrale Personen-ID zur Umgehung von Vereinsgrenzen übergeben.
- Die zentrale Personenverwaltung und die Aufnahme aus einer existierenden Person sind auf Superadmins begrenzt. Dies vermeidet eine globale Personensuche durch Vereinsadmins.
- Dashboard-Qualifikationslisten und Zähler erhalten denselben Vereinsfilter.
- Noch globale Geräte-/Kassen-/Konfigurationsverwaltungen bleiben hinter ihren bestehenden Superadmin-Sperren; die gleichartigen Dashboard-Geräteaktionen werden ebenso gesperrt.
- Die zusätzlichen Legacy-Personenverwaltungen unter `/member`, `/student` und `/instructor` sind wegen ihrer ungefilterten Listen und Objektzugriffe ausschließlich Superadmins zugänglich. Reguläre Vereinsadmins verwenden die vorhandene, vereinsbezogen abgesicherte EasyAdmin-Mitgliederverwaltung; das Ausbilderportal bleibt davon getrennt.
- Eigene Mitglieder-Dokumentdownloads prüfen weiterhin die zugehörige Mitglieds-ID, nicht allein die Rolle.

## Ergänzung Phase 3

Öffentliche Mitgliedsanträge sind ausschließlich unter `/membership/apply` zugelassen und begründen weder Konto noch Mitgliedsrechte. Vereinsadmins bearbeiten nur Anträge, Beitragsklassen, Forderungen und Mandate ihres Vereins. Globale Personenauswahl und die ausdrückliche Neuanlage einer anderen Person mit gemeinsamer Kontaktadresse bleiben Superadmins vorbehalten.

Mitglieder bearbeiten nur eigene Kontakt-/Notfalldaten; die Konto-E-Mail bleibt unverändert. Sie sehen nur eigene Forderungen und maskierte Mandate, nicht die Bank-/Forderungsdaten eines Familienzahlers. Verwaltungsdaten, Ermäßigungen und Sicherheitsrollen bleiben administrativ. Status-/Vereinswechsel und direkte Löschung werden nicht über editierbare Legacy-Mitgliederfelder an den dokumentierten Abläufen vorbeigeführt.

Eine geplante Kündigung setzt `leftAt` nicht vorzeitig. Erst die tägliche Verarbeitung nach dem wirksamen Austrittsdatum setzt Status `FORMER` und das Austrittsdatum; anschließend entfallen die bestehenden Mitgliedsrechte. Archivierung ist erst nach Austritt möglich. Siehe [Phase-3-Vertrag](../phase3-membership.md).

## Ergänzung Phase 5

Die neuen Backendrouten `/admin/teams`, `/admin/tournaments` und
`/admin/calendar` verlangen ein angemeldetes Konto und zusätzliche
Objektprüfungen durch `CompetitionAccess`. Die Basisrolle allein gewährt
keinen Zugriff.

| Aufgabe | Berechtigung |
|---|---|
| Mannschaft anlegen | Vereinsadmin im eigenen Verein; Superadmin global |
| Mannschaft, Kader und Training pflegen | Vereinsadmin oder aktuell zugeordneter Trainer, Co-Trainer bzw. Mannschaftsführung der konkreten aktiven Mannschaft |
| Turnier erstellen, planen, starten, werten und abschließen | Admin des veranstaltenden Vereins; Superadmin global |
| Offene Ausschreibungen ansehen | Berechtigte Mannschaftsbetreuung und Vereinsadmins |
| Eigene Turniermeldung und Kader bearbeiten | Betreuung der gemeldeten Mannschaft oder Veranstalter, vor Meldeschluss/Start |
| Externe Mannschaften und Spieler erfassen | Veranstalter |
| Gebühren verbuchen | Veranstalter; keine Veränderung alter Zahlungsbelege |
| Private Turnierdokumente herunterladen | Für das zugehörige Turnier berechtigte Verwaltung/Betreuung |

Abgelaufene Mannschaftszuordnungen, gewöhnliche Spieler, Betreuer ohne
Leitungsfunktion und nicht mehr aktive Mitglieder erhalten dadurch keine
Verwaltungsrechte. Eine Zuordnung zu einem fremden Verein berechtigt nicht.
Die alten EasyAdmin-Mannschafts-CRUDs behalten ihre Superadmin-Sperre.
Teilnehmer- und Berechtigungsnachweise anderer Mannschaften bleiben
deren Betreuung und dem Veranstalter vorbehalten; offene Ausschreibungen
zeigen keine Kontaktdaten oder Spielerlisten.

## Ergänzung Phase 8

`ROLE_BAR_MANAGER` (Thekenleitung) ist eine gesondert vergebbare Funktionsrolle.
Vorstand, Admin und Superadmin erben sie. Alle Thekendienstoperationen bleiben
auf `User.member.club` begrenzt; auch Superadmin erhält hier keinen
vereinsübergreifenden Datenzugriff oder eine globale Mitgliedersuche.
Ein Profil und Verein sind zwingend. Die Funktionsrolle erlaubt die
Verwaltung auch ohne aktive Mitgliedschaft, nicht jedoch die eigene
Diensteinteilung ohne aktive Mitgliedschaft.

| Aufgabe | Berechtigung |
|---|---|
| Dienstkalender, vereinsinterne Einteilung und gemeinsame Kalenderansicht | Aktive Vereinsmitglieder und Thekenleitung |
| Für einen Dienst anmelden, eigenen Dienst zurückziehen | Aktives Mitglied; freie Kapazität und keine Terminüberschneidung |
| Tausch / Vertretung anbieten | Ausschließlich die eingeteilte Person |
| Anfrage annehmen / ablehnen | Ausschließlich die angefragte Person; keine Leitungsfreigabe |
| Anfrage zurückziehen | Ausschließlich die anfragende Person |
| Veranstaltungen direkt im Kalender und Wochen-/Zweiwochenserien anlegen | Thekenleitung im eigenen Verein |
| Schichtdefinition, Zuweisung, Absage, Anwesenheit und Abschluss | Thekenleitung im eigenen Verein |
| Jahrespflicht, individuelle Ausnahmen, Gesamtauswertung und CSV | Thekenleitung im eigenen Verein |
| Separate Thekenkasse einschließlich Gegenbuchung und Abschluss | Thekenleitung im eigenen Verein |
| Eigenes Stundenkonto und Nachrichten | Ausschließlich die betreffende Person |

Alle Mutationen verlangen POST, operations-/objektgebundenes CSRF und
erneute Objektprüfung. Andere Mitglieder sehen keine fremden Stundenkonten,
Nachrichten, Begründungshistorien oder Kassenbelege. Die Kalenderfreigabe für
aktive Mitglieder erweitert **keine** Mannschafts-, Turnier- oder
Ausbildungsberechtigungen; deren bisherige Projektionsfilter bleiben bestehen.

## Betriebsfolge

Vor Freigabe müssen die Vereine betroffene Konten mit fehlendem Profil, fehlendem Verein, inaktivem Status oder Austrittsdatum prüfen. Diese Konten verlieren bewusst Mitgliedsrechte. Ein Funktionskonto benötigt für echte Mitgliedsrechte zusätzlich eine aktive Mitgliedschaft.

Die neue öffentliche Registrierung erzeugt keine automatisch aktive Vereinsmitgliedschaft. Ein Konto wird nach gültiger signierter Bestätigung nutzbar; Aufnahme und Rollenvergabe sind getrennte Vorgänge.
