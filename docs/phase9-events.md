# Veranstaltungsbuchungen

## Einrichten und veröffentlichen

Vereinsadmins und Vorstand öffnen in EasyAdmin **Veranstaltungsbuchungen**.
Sie erstellen eigenständige Veranstaltungen mit Beschreibung, Ort, Beginn,
Ende, optionaler Anmeldefrist und optionaler Platzanzahl. Entwürfe können bis
zur ersten Anmeldung bearbeitet und freigegeben werden. Nach einer Anmeldung
werden Preis, Termin und weitere Angaben nicht mehr verändert; die Veranstaltung
kann stattdessen abgesagt und neu angelegt werden. Eine Absage informiert in der
Anmeldungsansicht; bereits erfasste Zahlungen bleiben zur manuellen Erstattung
erhalten.

Zwei Arten sind zulässig:

- **Kostenfrei für Mitglieder:** beide Preise null; nur aktive Mitglieder des
  veranstaltenden Vereins sehen und buchen die Veranstaltung.
- **Kostenpflichtig für Mitglieder und Gäste:** beide Preise positiv, mit
  unabhängigem Mitglieder- und Gästepreis; die Veranstaltung ist öffentlich.

Das öffentliche Angebot verwendet wie die Website den durch
`PUBLIC_SITE_CLUB_ID` konfigurierten Verein. Die Anmeldung setzt ein bestätigtes
Personenkonto voraus; Gäste können das vorhandene Gastkonto verwenden. Ein
Mitglied eines anderen Vereins erhält den Gästetarif. Der Preis und Tarif werden
bei der Anmeldung festgehalten und nicht rückwirkend verändert.

## Anmeldung, Warteliste und Zahlung

Die Anmeldung erscheint unter **Meine Veranstaltungsanmeldungen**, bei Mitgliedern
auch über **Meine Buchungen**. Kapazität und Doppelanmeldungen werden unter einer
Datenbanksperre geprüft. Ist die Veranstaltung voll, erfolgt die Anmeldung auf
die Warteliste. Storniert eine angemeldete Person, rückt die älteste berechtigte
Wartelistenanmeldung nach; bei kostenfreien Mitgliedsveranstaltungen wird der
Mitgliedsstatus dabei erneut geprüft. Die persönliche Ansicht zeigt den aktuellen
Status. Eine einmal stornierte Anmeldung kann derzeit nicht erneut angemeldet
werden; dafür muss die Vereinsverwaltung eine neue Veranstaltung anlegen.

Bezahlt wird per Überweisung oder bar nach Absprache. Die Vereinsverwaltung
erfasst Zahlung oder Erstattung mit Betrag, Zahlungsart und eindeutiger
Belegreferenz im Backend. Zahlungen sind append-only, centgenau und auf den
vereinbarten Preis begrenzt; auf der Warteliste ist keine Zahlung fällig.
Stornierungen und Veranstaltungsabsagen erstatten nicht automatisch: Ein bereits
bezahlter Betrag wird sichtbar und muss tatsächlich erstattet und als negativer
Betrag mit neuer Referenz gebucht werden. Es gibt keine Online-Zahlung.

Die Teilnehmerliste und Zahlungsbelege sind ausschließlich für Vereinsadmins
des veranstaltenden Vereins sichtbar. Eigene Anmeldungen sind an die Person
gebunden. Frontend und Backend behalten ihre getrennte Navigation; der
Vereinskalender zeigt zulässige Veranstaltungen im jeweiligen Bereich.
Redaktionelle Ankündigungen sowie Kurs-/Turnieranmeldungen bleiben unabhängige
Datensätze; sie werden nicht automatisch in Veranstaltungsbuchungen umgewandelt.

## Betrieb und Grenzen

Vor Nutzung müssen die irreversiblen Doctrine-Migrationen
`Version20261028180000` und `Version20261028181000` nach Backup und
gesonderter Freigabe angewendet werden; ohne die erste fehlen die Tabellen.
Die zweite korrigiert gegebenenfalls Indexnamen einer bereits ausgeführten
ersten Fassung und verändert keine Geschäftsdaten. Tests der Migrationskette
verwenden eine eigens angelegte und wieder entfernte MariaDB-Testdatenbank.
Im hier geprüften lokalen Bestand war die erste Migration bereits anderweitig
ausgeführt; nach ausdrücklicher Freigabe wurde nur die zweite ausgeführt.
Schema und Mapping stimmen dort überein, es sind keine Migrationen offen.

Automatische Erinnerungen, E-Mail-Nachrichten beim Nachrücken oder Absagen,
Veranstaltungsdokumente und erneute Anmeldung nach Storno sind noch nicht
implementiert. Der Verein muss Teilnehmer über Änderungen außerhalb der
Anwendung informieren. Diese Einschränkungen sind keine vollständige Abnahme
der Veranstaltungsphase der Master Specification.
