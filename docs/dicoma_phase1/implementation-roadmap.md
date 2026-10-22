# DICOMA – Priorisierte Roadmap (Entwurf, keine Implementierungsfreigabe)

## Gate 0: Analyse abschließen
1. Abhängigkeiten und Laufzeitumgebung reproduzieren.
2. `debug:router`, Doctrine-Mapping und Migration-Status prüfen.
3. Authentifizierung, Voter und Zugriff auf Dateien und Installationsrouten manuell sowie automatisiert prüfen.
4. Kritische Geschäftsprozesse Ende-zu-Ende nachverfolgen und die Matrix auf vollständig/teilweise/fehlend/defekt umstellen.
5. Mit Testdatenbestand Tests und Migrationsprobe ausführen.

## Paket A – Sicherheit und Datenintegrität (kritisch)
Installationsschutz, Zugriffskontrolle, Club-Isolation, sensible Dokumente, Zahlungs-/Kassenintegrität, Konkurrenz bei Reservierungen.

## Paket B – Zentrales Domänenmodell (hoch)
Person/Benutzer/Mitgliedschaft/Gast-Beziehungen, gemeinsame Berechtigungs- und Kalenderkonventionen; Bestand vor Änderung prüfen.

## Paket C – Bestehende Kernmodule stabilisieren (hoch)
Mitglieder, Ausbildung, Ausrüstung, Flaschen, Gerätewartkasse; vollständige Prozessketten und Tests.

## Paket D – Weitere Fachmodule (mittel)
Mannschaften/Turniere, Thekendienste, Veranstaltungen, Clubreisen.

## Paket E – Portal-/Querschnittsfunktionen
Portale, Benachrichtigungen, Dokumente, globale Suche, Auswertungen, Barrierearmut.

## Freigabekriterien
Vor Implementierung: dokumentierte Architekturentscheidung, konkrete Dateiliste, Migrationsplan, Sicherheitsmodell, Tests, Rückfallstrategie und ausdrückliche Freigabe.
