# DICOMA Phase 1 – Strukturprüfung bislang wenig untersuchter Module

Prüfart: statische Sichtung von Originalarchiv `dicoma2110.zip` und Soll-Anforderungen. Keine Runtime- oder End-to-End-Tests.

## Abdeckungsstand

| Modul | Anforderungen offen | Kandidaten nach Dateinamen |
|---|---:|---:|
| Mannschaften und Turniere | 23 | 12 |
| Thekendienste | 13 | 0 |
| Veranstaltungen | 21 | 25 |
| Clubreisen | 30 | 0 |

**Wichtig:** Null Treffer bedeutet nur, dass keine einschlägig benannten Dateien gefunden wurden. Funktionen können anders benannt, in generischen Controllern implementiert oder noch nicht implementiert sein. Umgekehrt belegt ein Dateiname keine funktionierende Fachfunktion.

## Positiv belegte Code-Strukturen: Mannschaften

### `src/Entity/Team.php`
- L31: `private ?Sport $sport = null;`
- L34: `private ?string $name = null;`
- L42: `private TeamType $type = TeamType::TRAINING_GROUP;`
- L62: `private Collection $members;`

### `src/Entity/TeamMember.php`
- L32: `private ?Team $team = null;`
- L36: `private ?Member $member = null;`
- L44: `private TeamMemberRole $role = TeamMemberRole::MEMBER;`
- L47: `private ?\DateTimeInterface $joinedAt = null;`
- L50: `private ?\DateTimeInterface $leftAt = null;`

### `src/Controller/Admin/TeamCrudController.php`
- L27: `#[IsGranted('ROLE_ADMIN')]`

### `src/Controller/Admin/TeamMemberCrudController.php`
- L29: `#[IsGranted('ROLE_ADMIN')]`
- L33: `private readonly EntityManagerInterface $memberEntityManager,`

## Fachliche Abgrenzung und nächste Prüfschritte

1. Mannschaften: Vorhandene Stammdaten und Mitgliedszuordnungen getrennt von Turnierorganisation, Meldungen, Ergebnissen und Terminplanung prüfen.
2. Thekendienste: Dienstplanung, Schichttausch, Erinnerungen und Abrechnung anhand tatsächlicher Routen/Entities und Formulare nachweisen.
3. Veranstaltungen: Veranstaltungsanlage, Teilnehmerregistrierung, Warteliste, Gebühren und Stornierung einzeln prüfen; Kurskalender ist nicht automatisch allgemeine Veranstaltungsverwaltung.
4. Clubreisen: Reiseplanung, Anmeldung, Zahlungen, Teilnehmerlisten und Stornierung unabhängig von Kursen nachweisen.
5. Für jede Anforderung Route → Controller → Service/Repository → Entity → Template → Test nachvollziehen; fehlende Funktionen erst nach fachlicher Freigabe implementieren.

## Einschränkungen

Die Zuordnung ist bewusst konservativ: `nicht verifiziert` bleibt der Status aller hier ausgewählten Anforderungen. Die vorliegenden Zahlen sind Strukturhinweise und keine Funktionsabdeckung. Keine produktiven Dateien verändert.
