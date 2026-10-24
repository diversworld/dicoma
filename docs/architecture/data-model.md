# Personen-, Konto- und Mitgliedschaftsmodell

## Verantwortlichkeiten

| Objekt | Verantwortung |
|---|---|
| `Person` | Vorname, Nachname, Geburtstag, Kontakt und Anschrift. Kann ohne Konto und ohne Mitgliedschaft existieren. |
| `User` | Anmeldekennung, Passwort-Hash, Verifizierung, explizite Funktionsrollen und Konto-E-Mail. Kann ohne Mitgliedschaft existieren. |
| `Member` | Verein, Mitgliedsnummer, Mitgliedschaftsstatus, Eintritt/Austritt, vereinsbezogene Notizen und bestehende Fachrelationen. Verweist zwingend auf eine Person. |
| `Club` | Vereinsstammdaten und Vereinsbeziehungen. Keine neue Mandantenplattform. |

Eine Person kann mehrere Vereinsmitgliedschaften haben, aber pro nicht leerem Verein nur eine. Höchstens ein Konto verweist auf dieselbe Person. Der bestehende `User.member`-Verweis bezeichnet weiterhin die Mitgliedschaft des aktiven Berechtigungskontexts; ein Vereinswechseldialog ist nicht neu implementiert.

Konto-E-Mail und Personen-Kontakt-E-Mail sind fachlich getrennt: Eine Änderung von Kontaktdaten darf nicht stillschweigend die verifizierte Anmelde-/Reset-Adresse verändern. Bei Neuregistrierung werden beide zunächst mit derselben Adresse initialisiert.

## Kompatibilität

Aufrufe wie `Member.getFirstname()`, `setLastname()` und `getPostalCode()` bleiben erhalten, lesen/schreiben aber ausschließlich `Member.person`. Twig-Zugriffe und bestehende Formularfeldnamen bleiben dadurch verwendbar. ORM-Abfragen müssen dagegen beispielsweise `member.person.lastname` statt des nicht mehr gemappten `member.lastname` verwenden.

Die Standardnamenssortierung in der Mitgliederliste bleibt erhalten. Die bisherigen virtuellen Namens-/Kontaktspalten sind nicht individuell anklickbar sortierbar; eine direkte Sortierung auf den entfernten Member-Spalten wäre ungültig. Die zentrale Personenverwaltung verwendet direkt gemappte und damit sortierbare Personenfelder.

Das Entfernen eines Kontos erhält Mitgliedschaft und Person; `member.user_id` wird durch `ON DELETE SET NULL` geleert. Das Entfernen einer Mitgliedschaft erhält Konto und Person. Eine Person mit referenzierenden Mitgliedschaften wird durch den Fremdschlüssel vor versehentlicher Löschung geschützt. Die neue Personenverwaltung bietet bewusst keine pauschale Löschaktion; ein DSGVO-Lösch-/Aufbewahrungsprozess ist gesondert auszuarbeiten.

## Migration

1. `Version20261023100000`: Personen mit deterministischen IDs aus den bisherigen Mitgliederdatensätzen anlegen, Member-/User-Verknüpfungen setzen, Constraints ergänzen. Gleichnamige Datensätze werden nicht automatisch verschmolzen. Alte Personenfelder bleiben zunächst vorhanden.
2. `Version20261023110000`: Jede bisherige Personenspalte nullsicher und bytegenau mit dem Ziel vergleichen, Konto-/Personenverweise prüfen und erst bei vollständiger Übereinstimmung die redundanten Member-Spalten entfernen.

IDs der vorhandenen Mitgliedschaften und damit deren Kurs-, Mannschafts-, Kassen- und Dokumentreferenzen bleiben unverändert. Bestehende Konten ohne Mitgliedschaft bleiben ohne erfundene Identitätsdaten bestehen.

Beide Migrationen sind absichtlich nicht automatisch rückwärts ausführbar: Neue Gäste oder zentral geänderte Personendaten dürfen nicht durch ein historisches `down()` gelöscht werden. MySQL-DDL ist nicht vollständig transaktional; Wartungsmodus, verifiziertes Backup und Wiederanlaufverfahren sind zwingend. Siehe [Betrieb](../deployment/operations.md).
