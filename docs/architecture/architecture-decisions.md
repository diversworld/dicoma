# Architekturentscheidungen – Phase 2

Stand: 23.10.2026. Fachentscheidungen wurden im laufenden Auftrag ausdrücklich bestätigt.

## ADR-01 – Bestehendes System erweitern

Symfony, Doctrine, EasyAdmin, bestehende IDs und fachliche Relationen bleiben erhalten. Keine Neuentwicklung, keine PostgreSQL-Umstellung, keine neue Mandanteninfrastruktur und keine automatische Identitätszusammenführung.

## ADR-02 – Person normalisieren, Schnittstellen erhalten

Personendaten werden aus `Member` extrahiert; kompatible Zugriffsmethoden delegieren an `Person`. Eine reine zusätzliche Entity mit dauerhaft doppelten Stammdaten wäre keine Normalisierung. Die Entfernung der überprüften Altspalten wurde ausdrücklich freigegeben.

Die Migration ist zweistufig, damit die Prüfung **nach** tatsächlich ausgeführter Kopie und **vor** der Spaltenentfernung erfolgt. Abweichungen blockieren statt stillschweigend Daten zu überschreiben. Ein vollständiger Dry-Run auf einem noch nicht erweiterten Schema ersetzt diesen Ablauf nicht.

## ADR-03 – Authentifizierung ist keine Mitgliedschaft

Ein Gastkonto benötigt weder Member-Datensatz noch Mitgliedsrolle. `ROLE_MEMBER` hängt vom aktiven Vereinsprofil ab. Funktionsrechte werden getrennt vergeben und nicht über eine implizite Mitgliedschaft legitimiert.

## ADR-04 – Vereinsadmins begrenzen, globale Altbereiche sperren

Bestehende Voter und SQL-Filter bleiben maßgeblich. Nicht ausreichend vereinsbezogene Legacy-Verwaltungen werden nicht allein aufgrund einer allgemeinen Adminrolle freigegeben. Die vollständige Ersetzung aller temporären Superadmin-Sperren durch delegierte Vereinsverwaltungen ist ein eigener, objektweise zu testender Ausbau.

## ADR-05 – Keine Löschkaskade zwischen Identität, Konto und Mitgliedschaft

Das Löschen eines Kontos oder einer Mitgliedschaft darf das jeweils andere Objekt und die zentrale Identität nicht mitlöschen. Referenzielle Integrität und bewusst getrennte Löschverfahren ersetzen gegenseitige `cascade: remove`.

## ADR-06 – Prüfarten getrennt ausweisen

Statische Routenprüfung, isolierte HTTP-Tests, echte Staging-HTTP-Tests und fachlich-betriebliche Freigabe sind unterschiedliche Nachweise. Fehlende Fixtures und übersprungene Tests ergeben keine erfolgreiche Abnahme. `doctrine:migrations:up-to-date` prüft ausstehende Migrationen; ein reiner Statusausdruck nicht.

## ADR-07 – Portalnavigation ist keine neue Berechtigungsinstanz

Stand: 28.10.2026. `PortalNavigation` bündelt öffentliche und persönliche
Menüangebote sowie rollenabhängige Frontendfunktionen. Der Service verwendet die bestehenden
Rollen, Vereinszuordnungen und Fachprüfungen (`CompetitionAccess`,
`PublicWebsite`), ersetzt aber keine Controller-/Voter-Prüfung.
Eine Mitgliedschaft ist keine Voraussetzung für unabhängig vergebene
Funktionsrollen. Ohne den vom Ziel benötigten Vereinskontext wird kein
unbenutzbarer Einstieg angeboten.

Der gemeinsame Login führt Mitglieder nach `/my`, Gäste nach `/training` und
reine Verwaltungsrollen zu ihrem EasyAdmin-Einstieg. Explizite Ausbilder-/
Gerätewartfunktionen behalten bei Mehrfachrollen den Frontendeinstieg.
`/portal` bleibt eine optionale persönliche Übersicht ohne Verwaltungslinks.
Gespeicherte Zielpfade haben weiterhin Vorrang. Die gemeinsame Plattform bleibt
ein Symfony-Monolith mit getrennten Darstellungen, keine neue SPA-/API-Architektur.

## ADR-08 – Darstellungsgrenzen nach Tätigkeit, nicht nach Benutzerrolle

Persönlicher Ausbildungs-Self-Service unter `/training` und der lesende
Mitgliederkalender unter `/calendar` bleiben auch für Admins im Frontend.
Kursverwaltung liegt unter `/admin/training`, Sportverwaltung unter
`/admin/teams` und `/admin/tournaments`, auch für berechtigte Nichtadmins im
Backend. Der bestehende `/admin/calendar` samt EasyAdmin-Kurzaufruf bleibt
kompatibel und verwendet durchgehend den Backendrahmen.
Es gibt keine kopierten Kalenderdaten und keine zweite Fachimplementierung.

Alle Frontendseiten einschließlich Gerätewart, Ausbilder und Thekendienst
erben dasselbe konfigurierte Theme. Nur die Navigation erweitert sich nach Rolle.
Frontend und Backend bieten keine gegenseitigen Navigationslinks. Die
Clubheimverwaltung liegt unter `/admin/bar-duty`; Planung, Vorlagen,
Pflichtstunden und Gesamtauswertung sind im Frontend auch für Leitungsrollen
gesperrt. Aktive eingeteilte Mitglieder dürfen nur die Kasse ihrer eigenen
Schicht bedienen. Finanzregeln und unveränderliche Abschlüsse bleiben erhalten.

## ADR-09 – Öffentliche Vereinsdaten nur aus dem konfigurierten Verein

`/club` und `/contact` verwenden ausschließlich den explizit konfigurierten,
aktiven `PUBLIC_SITE_CLUB_ID`. Ohne ihn werden weder Seiten noch Menüeinträge
angeboten. Clubbeschreibung und Organisationskontakt werden HTML-escaped
dargestellt; interne Notizen, Mitglieder- und Kontodaten bleiben ausgeschlossen.
Nur HTTP-/HTTPS-Websiteadressen werden zu anklickbaren Links. Die bestehende
Vereinskonfiguration wird wiederverwendet, ohne neue Tabellen oder automatische
Veröffentlichung anderer Vereinsdaten.
