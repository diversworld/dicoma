# Phase 1 – EasyAdmin-Controller: Vereinsbezogene Datenzugriffe

Stand: 21.10.2026. Statische Prüfung von `dicoma2110.zip`; keine Ausführung der Anwendung.

## Methode

Alle **36** Dateien `src/Controller/Admin/*CrudController.php` wurden auf Klassenattribute `IsGranted`, `createIndexQueryBuilder`, CRUD-Lebenszyklusmethoden, Vereinsreferenzen und Abfrageaufrufe untersucht. Die vollständige Inventur steht in `easyadmin-controller-inventory.csv`. Textbasierte Suchtreffer sind **kein** Nachweis vollständiger Berechtigungsprüfung oder deren Fehlen.

## Belegte Ergebnisse

- **36/36** untersuchte CRUD-Controller tragen `#[IsGranted('ROLE_ADMIN')]` (Klassenebene).
- **35/36** überschreiben `createIndexQueryBuilder()` nicht. Der einzige Treffer ist `src/Controller/Admin/MemberQualificationCrudController.php` ab Zeile **251**. Dort wird ein `validity`-Filter mit Ablaufdatum verwendet; eine Vereinsfilterung ist in dieser Methode nicht ersichtlich.
- **19/36** Dateien enthalten nach einer breiten Textsuche Referenzen auf `club`, `getClub()` oder `Club::class`; das ist kein Beleg für eine konsistente Vereinsprüfung.
- Beispiel: `src/Controller/Admin/MemberCrudController.php`: Klassenrolle `ROLE_ADMIN`; Detailansicht lädt Qualifikationen, Kursteilnahmen, Teams und Dokumente mit `findBy(['member' => $member])`. Das beschränkt die zugehörigen Daten auf das aktuell angezeigte Mitglied, beweist aber nicht, dass das Mitglied selbst dem Verein des eingeloggten Administrators angehört.
- Beispiel: `src/Controller/Admin/EquipmentBookingCrudController.php`: Klassenrolle `ROLE_ADMIN`; `configureActions()` deaktiviert NEW, EDIT, DELETE und BATCH_DELETE im Standard-CRUD. Spezielle Admin-Aktionen benötigen weiterhin eine gesonderte Prüfung.

## Sicherheitsbewertung

**Bedingtes Risiko – Priorität hoch, falls ROLE_ADMIN vereinsgebunden sein soll.** Ohne sichtbare vereinsbezogene Einschränkung in den CRUD-Controllern könnten Index-/Detail-/Bearbeitungszugriffe übergreifend sein. Ob dies tatsächlich möglich ist, hängt insbesondere von der beabsichtigten Reichweite von `ROLE_ADMIN`, globalen Doctrine-Filtern, EasyAdmin-Konfiguration und weiteren Security-Schichten ab. Die Analyse belegt **keine ausnutzbare Schwachstelle**.

## Nächste Prüfungen

1. `security.yaml`, Dashboard-Routen, Rollenvergabe und das Berechtigungskonzept auf globale bzw. vereinsbezogene Administratoren abgleichen.
2. Doctrine SQL-Filter, Event-Listener und Repository-Filter prüfen, die CRUD-Abfragen zentral einschränken könnten.
3. Für Member, User, Courses, EquipmentBooking und Kassenbelege direkte Index-, Detail-, Edit- und Delete-Aufrufe mit Administratoren aus zwei Vereinen testen.
4. Für tatsächlich vereinsgebundene Administratoren zentrale objektbezogene Voter (`isGranted()`/`denyAccessUnlessGranted()`) und passende Query-Scoping-Regeln spezifizieren; Symfony Security Voters und EasyAdmin `createIndexQueryBuilder()` als technische Referenz nutzen.

## Grenzen

Keine Symfony-Kernel-Ausführung, keine Datenbank, keine authentifizierten HTTP-Tests. Eine fehlende Override-Methode bedeutet nicht automatisch, dass keine anderweitige Zugriffsbeschränkung existiert.
