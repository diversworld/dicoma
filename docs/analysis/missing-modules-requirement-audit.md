# DICOMA – Phase 1: Soll-Ist-Abgleich Thekendienste, Veranstaltungen, Clubreisen

Prüfgrundlage: unverändertes Originalarchiv `dicoma2110.zip`, Anforderungen aus `underreview-module-requirements.csv` und Master-Spezifikation (Abschnitte 11–13). Statische Prüfung von 524 PHP-, Twig- und Konfigurationsdateien in `src/`, `templates/` und `config/`.

## Umfang

| Fachmodul | Anforderungen | Status |
|---|---:|---|
| Thekendienste | 13 | Nicht funktional nachgewiesen |
| Veranstaltungen | 21 | Nicht funktional nachgewiesen |
| Clubreisen | 30 | Nicht funktional nachgewiesen |
| **Gesamt** | **64** | **Keine End-to-End-Freigabe** |

## Thekendienste

Gezielte Suche nach eindeutigen Domänenbegriffen: **0 dokumentierte Treffer** (maximal 100 je Modul); diese belegen keine vollständigen Geschäftsprozesse.

Keine eindeutigen Treffer in den untersuchten PHP-/Twig-/Konfigurationsdateien. Das ist ein **negativer Suchbefund**, kein Beweis, dass die Funktion an anderer Stelle unmöglich vorhanden ist.

## Veranstaltungen

Gezielte Suche nach eindeutigen Domänenbegriffen: **4 dokumentierte Treffer** (maximal 100 je Modul); diese belegen keine vollständigen Geschäftsprozesse.

- `src/Controller/Admin/ScheduleCrudController.php:184` – `yield TextField::new('location','Veranstaltungsort')`
- `templates/admin/course/history.html.twig:29` – `{% if event.participantId is not null %}`
- `templates/admin/course/history.html.twig:30` – `{{ participantNames[event.participantId]|default('Nicht mehr vorhanden') }}`
- `templates/admin/course/history.html.twig:31` – `<span class="muted small">(#{{ event.participantId }})</span>`

## Clubreisen

Gezielte Suche nach eindeutigen Domänenbegriffen: **0 dokumentierte Treffer** (maximal 100 je Modul); diese belegen keine vollständigen Geschäftsprozesse.

Keine eindeutigen Treffer in den untersuchten PHP-/Twig-/Konfigurationsdateien. Das ist ein **negativer Suchbefund**, kein Beweis, dass die Funktion an anderer Stelle unmöglich vorhanden ist.

## Abgrenzung und Freigabekriterien

- Kurs- und Terminbuchungen werden nicht automatisch als Veranstaltungsmanagement oder Clubreisebuchung gewertet.
- Symfony `EventListener` und `EventSubscriber` sind technische Ereignisse, keine Vereinsveranstaltungen.
- Für Thekendienste sind Dienstplan, Zuordnung, Tausch, Erinnerungen und ggf. Kasse separat nachzuweisen.
- Für Veranstaltungen sind Erstellung, Anmeldung, Kapazität, Warteliste, Stornierung, Rechte und Kalenderintegration zu prüfen.
- Für Clubreisen sind Reiseplanung, Teilnehmer, Plätze, Kosten, Zahlungsstatus und Stornierung zu prüfen.
- Für alle drei Module sind anschließend HTTP- und Datenbanktests erforderlich.

**Fazit:** Die 64 Anforderungen dieser drei Module bleiben `nicht verifiziert`. Fehlende Treffer erhöhen die Wahrscheinlichkeit einer Implementierungslücke, erlauben aber ohne vollständige dynamische Prüfung keine pauschale Aussage „nicht implementiert“. Keine produktiven Codeänderungen.
