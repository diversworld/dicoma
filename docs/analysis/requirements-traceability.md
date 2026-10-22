# DICOMA – Anforderungsnachverfolgung (statische Vorprüfung)

Erfasst: **291** Aufzählungsanforderungen aus den Abschnitten 6–15 und 17–19 der Master-Spezifikation.

## Methodik und Grenzen

Jeder Aufzählungspunkt wurde als eigene prüfbare Anforderung übernommen. Die zugeordneten PHP-Dateien sind **nur modulbezogene Dateinamenskandidaten**, kein Funktionsnachweis. Daher ist der Status durchgehend „nicht verifiziert“; weder „fehlend“ noch „vollständig“ wird ohne Ablaufprüfung behauptet. Fließtextanforderungen und Unterpunkte aus anderen Abschnitten sind ergänzend manuell zu erfassen.

| Modul | Anforderungen |
|---|---:|
| Mitglieder | 29 |
| Tauchausbildung | 32 |
| Mannschaften und Turniere | 23 |
| Ausrüstung und Gerätewart | 24 |
| Gerätewartkasse | 22 |
| Thekendienste | 13 |
| Veranstaltungen | 21 |
| Clubreisen | 30 |
| Portale | 54 |
| Übergreifende Funktionen | 15 |
| Datenschutz | 11 |
| Qualitätssicherung | 17 |

## Nächster Abnahmeschritt

1. Pro Anforderung Route, Controller, Service, Entity und Template konkret referenzieren.
2. Positiv-, Negativ- und Mandantengrenztests dokumentieren.
3. Status nach belegten Kriterien auf vollständig / teilweise / fehlend / defekt setzen.
4. Datenbank- und Containerprüfungen in isolierter Umgebung nachholen oder Blockade formal abnehmen.

Die CSV ist eine Traceability-Grundlage, **keine fertige Funktionsmatrix**.
