# DICOMA Phase 1 – Mitglieder-, Benutzer- und Vereinsmodell

## Grundlage
Statische Prüfung des Originalarchivs `dicoma2110.zip`, insbesondere `src/Entity/Member.php`, `User.php`, `Club.php`, `src/Controller/Admin/MemberCrudController.php` und `src/Controller/MemberPortalController.php`. Keine produktiven Änderungen.

## Bestätigte Befunde

1. `Member.php` Zeilen 32–37: optionale ManyToOne-Beziehung zu `Club`; ein Member kann ohne Verein gespeichert werden.
2. `Member.php` Zeilen 107 ff. und `User.php` Zeile 49: OneToOne-Beziehung zwischen Mitgliedsprofil und Benutzerkonto.
3. `User.php` Zeilen 105–116: `getRoles()` ergänzt `ROLE_MEMBER` bedingungslos, auch ohne zugeordnetes Mitgliedsprofil. Ein Rollentest allein beweist daher keine Mitgliedschaft.
4. `MemberPortalController.php` Zeilen 17–25: zusätzlich zur Klassenberechtigung `ROLE_MEMBER` wird ein tatsächliches `User`-Objekt mit zugeordnetem `Member` verlangt; andernfalls AccessDenied.
5. `MemberCrudController.php` Zeilen 34–35: EasyAdmin-CRUD ist auf `ROLE_ADMIN` eingeschränkt. Daraus folgt keine bestätigte objektbezogene Vereinsprüfung.
6. `MemberCrudController.php` Zeilen 175 ff.: das Feld `club` ist im Formular verpflichtend, während die ORM-Relation nullable ist. Andere Schreibwege könnten deshalb einen leeren Club zulassen.
7. `Club.php` Zeilen 78 ff. und 309 ff.: inverse Sammlung von Mitgliedern; Club-Zuordnung ist fachlich im Datenmodell angelegt.

## Bewertung und Grenzen

Diese Befunde beruhen auf den untersuchten Dateien. Eine umfassende Aussage zur Mehrvereinsfähigkeit, zu Registrierungsprozessen, anderen Votern oder zur tatsächlichen Ausnutzbarkeit ist ohne Prüfung weiterer Komponenten und Laufzeittests nicht möglich.

## Vorgeschlagene Arbeitspakete (noch keine Implementierung)

- M-01: Aufrufstellen von `getRoles()`, `ROLE_MEMBER`, `getMember()` und Mitgliedschaftsstatus vollständig inventarisieren; Rollenvertrag definieren.
- M-02: Vereinsbezogene Objektzugriffe aller Member-CRUD-Aktionen inklusive Query-Filter, Detailansicht, Bearbeitung und Löschung prüfen.
- M-03: Bedeutung von `Member` für Gäste und spätere Mitgliedschaft klären; keine neue `Person`-Entity ohne Migrations- und Abhängigkeitsanalyse einführen.
- M-04: Datenbank-Constraints, Migrationshistorie und alternative Schreibpfade der Club-Beziehung prüfen.
- M-05: Tests für Benutzer ohne Mitgliedsprofil, inaktive Mitgliedschaft, mehrere Rollen und vereinsübergreifenden Zugriff spezifizieren.

## Abnahmestatus

Teilprüfung abgeschlossen. Phase 1 bleibt offen, bis alle Anforderungen verifiziert, Risiken priorisiert und Laufzeitprüfungen entweder ausgeführt oder nachvollziehbar als blockiert dokumentiert sind.
