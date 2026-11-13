# Phase 3: Rollen, Berechtigungen und Sicherheit

## 1. Status

Begonnen, aber nicht vollständig umgesetzt oder fachlich abgenommen. Die
nachfolgenden Nachweise betreffen einen abgegrenzten Teilumfang; sie sind keine
Gesamt- oder Produktivfreigabe.

## 2. Umsetzung

Die bestehende Mehrfachrollenverwaltung bleibt erhalten. Vereinsadministratoren
können ausgewählte Funktionen aktiven, nicht privilegierten Mitgliedskonten
ihres eigenen Vereins zuweisen und entziehen. Eigene Konten, fremde oder
inaktive Mitglieder sowie Konten mit geschützten oder unbekannten gespeicherten
Rollen sind ausgeschlossen. Superadministratoren verwenden den ausgewählten
Vereinskontext. Die allgemeine Benutzerverwaltung bleibt Superadministratoren
vorbehalten.

Delegierbar sind Ausbilder, Ausbildungsleiter, Kursadministration, Gerätewart,
Gerätekasse, Kompressorbedienung, Thekenverwaltung, Presse und Kassenwart.
Die Auswahl ist serverseitig begrenzt; Basisrollen bleiben erhalten.
Formulare verwenden CSRF-Schutz, erneute Berechtigungsprüfung und einen
Rollen-Fingerprint zur Erkennung veralteter Formulare. Rollenänderungen werden
nach erfolgreichem Commit protokolliert.

Bei einem manuellen Wechsel von einer aktiven zu einer nicht aktiven
Mitgliedschaft muss für jede gespeicherte Funktionsrolle ein anderes aktives
Mitglied desselben Vereins dieselbe Rolle ausdrücklich und aktuell gültig
besitzen. Vererbte, abgelaufene oder erst künftig gültige Rollen zählen nicht.
Nach erfolgreicher Prüfung werden die Funktionsrollen und ihre Zeiträume
dauerhaft entfernt; eine spätere Reaktivierung stellt sie nicht wieder her.
`ROLE_SUPER_ADMIN` ist als Notfallrolle ausgenommen. Kontozuordnungen mit
Funktionsrollen können nicht über die Mitglieder- oder Benutzerverwaltung
verschoben werden; sie müssen zuvor gesondert geklärt werden.

Bei einer erfassten Kündigung wird eine Meldung an aktive Vorstandsmitglieder
des Vereins vorbereitet. Der tägliche Lauf bereitet bei offener Nachfolge
erneute Meldungen vor. Der Austritt erfolgt nach Ablauf des letzten
Mitgliedschaftstages auch ohne Nachfolge; Funktionsrollen werden entfernt und
eine etwaige Lücke einschließlich fehlender Vorstandsadresse in der
Mitgliederverwaltung als historischer Vorgang angezeigt. Diese Anzeige ersetzt
keine laufende Prüfung, ob eine Nachfolge inzwischen zugewiesen wurde.

Die Vorstandsmeldungen liegen zunächst in der bestehenden Mailwarteschlange.
Für tatsächlichen Versand ist ein verlässlich geplanter täglicher Aufruf von
`app:membership:daily --send`, eine gültige `MEMBERSHIP_MAIL_FROM` und eine
funktionierende Mailzustellung erforderlich. Fehlgeschlagene Nachrichten müssen
überwacht und gegebenenfalls mit `--retry-failed --send` erneut verarbeitet
werden. Ohne zustellbaren aktiven Vorstand wird ein Fehler protokolliert und
die Verwaltung muss den Vorstand anderweitig informieren. Eine rechtzeitige
Zustellung ist ohne diese Betriebsnachweise nicht gewährleistet.

Für jede zugewiesene Funktionsrolle kann optional ein Beginn und/oder Ende
festgelegt werden. Der Endtag zählt noch zur Gültigkeit; maßgeblich ist der
Kalendertag in der PHP-Zeitzone der Anwendung. Vereinsadministratoren können
die Gültigkeit der delegierbaren Rollen bearbeiten; für privilegierte Rollen
steht Superadministratoren ein eigener Weg in der Benutzerverwaltung zur
Verfügung. Bei einem Wechsel der effektiven Rollen wird eine vorhandene Sitzung
beim nächsten Request ungültig. Andere gültige Rollen und vererbte Rechte
bleiben bestehen.

## 3. Zuständigkeiten und Rollen

Die Spartenfunktion Trainer allein gibt keine Mannschaft frei. Bestehende
Trainer-, Co-Trainer- oder Mannschaftsleitungszuordnungen gewähren Zugriff auf
die jeweilige Mannschaft; Spartenleitungen sind auf ihre Sparte und den
Gültigkeitszeitraum beschränkt.

Die Gerätekasse erfordert **Gerätewart und Gerätekasse**. Ein Gerätewart allein
erbt weder die Gerätekassenrolle noch die Kassenwartrolle. Vereinsadministratoren
und Superadministratoren erhalten beide Gerätekassenrechte über die vorhandene
Administrationshierarchie. Operative Ausleihe und Kompressorbedienung bleiben
getrennt; der Zahlungsstatus einer Ausleihe bleibt für deren Abwicklung sichtbar.

## 4. Datenbank und Migration

Die Migration `Version20261109130000` ergänzt ein nullable JSON-Feld für die
optionalen Zeiträume; vorhandene Rollen bleiben unbefristet. Vor der Migration
wurde die lokale DDEV-Datenbank gesichert und in einer privaten MariaDB
restauriert. Die Prüfsummen aller 108 Tabellen stimmten überein. Die Migration
wurde für frische und bestehende synthetische Daten in privater MariaDB geprüft
und anschließend in DDEV ausgeführt. Sie ist bewusst irreversibel, damit
zeitliche Einschränkungen nicht versehentlich verloren gehen.

Bestehende Gerätewarte erhalten die neue Kassenrolle **nicht automatisch**;
falls sie weiterhin kassieren sollen, muss sie ihnen ausdrücklich zugewiesen
werden. Bestehende Daten und Belege werden nicht verändert.

## 5. Sicherheit

Die Gerätekassen-Controller prüfen beide Rollen serverseitig, einschließlich
Zahlung, Auszahlung, Korrektur, Tagesauswertung und Abschluss. Navigation und
Kassierformular sind entsprechend eingeschränkt. Die delegierte Rollenvergabe
verwendet einen objektbezogenen Voter; auf MySQL/MariaDB wird das bearbeitete
Benutzerkonto für die Änderung gesperrt. Der frühere TÜV-Buchungsweg leitet auf
den mitgliedsbezogenen Ablauf um und nimmt keine Schreibanfragen mehr an.
Alte Flaschenansichten sind auf den eigenen Verein begrenzt; eine alte
GET-Buchung leitet auf das geschützte Anmeldeformular um. Globale alte
TÜV-Ansichten leiten für Gerätewarte auf deren Vereinsübersicht um.
Die historischen POST-Wege der administrativen Altverwaltung sind weiterhin
stillgelegt. Ein zusätzlicher früherer TÜV-GET-Weg prüft die Vereinszuordnung
und leitet ohne Buchung weiter. Weitere Controller und Nebenläufigkeitsfälle
sind noch nicht vollständig geprüft.

## 6. Tests

In DDEV mit PHP 8.5 und isolierter SQLite-Datenbank bestanden 34 Tests mit
305 Assertions für Rollenvergabe und Geräteabläufe. Geprüft wurden unter anderem
Rollenablehnung, CSRF, veraltete Formulare, Änderungen geschützter Rollen und
Mitgliedschaften vor dem Absenden, Rollenentzug mit Sitzungsinvalidierung sowie
Kassen-GET/POST-Sperren und bestehende Zahlungsvorgänge. Eine weitere
Rollen- und Navigationsprüfung bestand mit 69 Tests und 2.874 Assertions;
drei gezielte Sparten- und Trainertests bestanden mit 71 Assertions, darunter
die tatsächliche Anlage eines Trainings für eine zugewiesene Mannschaft.
Twig- und Container-Lint bestanden.
Nach Erneuerung des lokalen Anwendungscaches liefert die DDEV-Anmeldung HTTP
200 und die neue Rollenverwaltung für anonyme Zugriffe eine Weiterleitung zur
Anmeldung.

Für die Nachfolgeregel und die abgesicherten Altwege bestanden gezielte Tests
mit synthetischen SQLite-Daten. Ein MariaDB-Test mit zwei Verbindungen wies
nach, dass ein konkurrierender Statuswechsel auf die Vereinssperre wartet und
nach dem ersten Commit die Nachfolge erneut prüfen muss. Er ist kein
vollständiger Nachweis aller parallel möglichen Rollen- und Zuordnungsänderungen.

Das abschließende Basis-Gate bestand in DDEV mit PHP 8.5 und isoliertem SQLite:
486 Tests und 11.044 Assertions; auch Routing, Container, Mapping und
Installationssperren bestanden. Acht JavaScript-Tests bestanden in DDEV. Mit
expliziter Verbindung zur lokalen DDEV-Datenbank waren Migrationsstand und
Doctrine-Schema aktuell. Die vollständige Migrationssuite bestand in der
privaten MariaDB mit 39 Tests und 327 Assertions. Dies ist keine erneute
Staging-HTTP-Abnahme oder fachliche Freigabe.

Ein zwischenzeitlicher Wettbewerbslauf ohne die nötigen Test-Umgebungsvariablen
scheiterte an einem veralteten Symfony-Testcontainer; mit den dokumentierten
Variablen bestand er erneut. Die Nachweise einzelner Sperren und des
Basis-Gates ersetzen keine vollständige Prüfung des Phase-3-Endstands.

## 7. Offene Punkte

Die vollständige Einzelprüfung aller Controller, vertraulichen Daten und
Vereinsgrenzen steht aus. Fachliche Rechte sind noch nicht generell von globalen
Rollen getrennt; weitere geforderte Funktionen sind nicht vollständig umgesetzt.
Eine vollständige technische Abnahme und der Zustellnachweis der
Vorstandsbenachrichtigungen stehen aus.
Die Testfälle ersetzen keine fachliche Abnahme oder Prüfung realer
Arbeitsabläufe. Die vorbereitete Prüfliste steht in
[phase-03-acceptance.md](phase-03-acceptance.md).

## 8. Abnahmekriterien

Für den getesteten Teilumfang ist die Trennung von Gerätewart und Gerätekasse
nachgewiesen. Mannschafts- und Spartenrechte werden gezielt geprüft; ein
vollständiger Nachweis für sämtliche Trainingsbereiche und spartenspezifischen
Funktionen steht noch aus.

## 9. Empfehlung

Vor einem Einsatz bestehende Gerätewarte überprüfen und notwendige
Gerätekassenrechte gezielt vergeben. Bis zur umfassenden Sicherheits- und
fachlichen Prüfung keine Produktivfreigabe erteilen.

## 10. Nächster Schritt

Die übrigen Rollen und Controller inkrementell prüfen, fehlende Zuständigkeiten
ergänzen, Nebenläufigkeit auf MariaDB nachweisen und danach
Gesamt- und fachliche Abnahme durchführen.

## 11. Neue Vorgaben: ein Verein und erweiterbare Funktionen

Die Anwendung soll künftig nur einen Verein verwalten. Für die lokale
DDEV-Umgebung wurde DUC Darmstadt e.V. (ID 1) als Zielverein bestimmt. Das
zuvor dem anderen Verein zugeordnete Turnier wurde nach ausdrücklicher
Freigabe auf diesen Verein übertragen; seine beiden Einträge blieben erhalten.
Der andere Vereinsdatensatz wurde nicht gelöscht.

Vor der Übertragung wurde ein Datenbankbackup erstellt und in einer privaten
MariaDB wiederhergestellt. Die Prüfsummen aller 108 Tabellen stimmten mit der
lokalen Datenbank überein. Nach der Übertragung meldete die vorhandene
schreibgeschützte Einzelvereinsprüfung keine abweichenden oder nicht
zugeordneten geprüften Vereinsbezüge. Die isolierten Einzelvereins- und
Übernahmetests bestanden mit 17 Tests und 70 Assertions.

Der zentrale Vereinskontext ist inzwischen auf einen Verein umgestellt; der
frühere Betriebsartschalter hat keine Wirkung mehr. Ohne konfigurierte ID wird
ein einzelner aktiver Verein verwendet; bei mehreren aktiven Vereinen ist die
Ziel-ID ausdrücklich erforderlich. Ein Superadministrator kann den ersten
Verein in EasyAdmin anlegen, aber keine weiteren Vereine. Die öffentliche
Website benötigt weiterhin `PUBLIC_SITE_CLUB_ID`.

Die Umstellung ist noch nicht vollständig abgenommen: Ein unterbrochener
isolierter Regressionstest meldete nach 427 Tests 25 Fehler und 10 Fehlschläge.
Viele betreffen Tests des entfernten Vereinswechsels; weitere Tests und die
verbleibenden Oberflächen und Datenpfade müssen angepasst oder untersucht
werden. Die Fach- und Staging-Abnahme steht aus.

Der Einstieg in die Vereinsfunktionen zeigt die vorhandenen Funktionen statt
einer Kontenliste. Nach Auswahl einer Funktion zeigt eine EasyAdmin-Standardliste
alle Konten und deren ausdrückliche Zuordnung einschließlich Zeitraum; geerbte
Berechtigungen sind keine eigene Zuordnung. Der Bearbeitungsdialog ändert nur
die ausgewählte Funktion dieses Mitglieds. Andere Rollen und deren Zeiträume
bleiben erhalten. Die Bearbeitung ist weiterhin nur bei delegierbaren Rollen
und durch den Voter freigegebenen Konten möglich. Geschützte Rollen verwalten
Superadministratoren weiterhin in der Benutzerverwaltung.

Die Zuordnung behält CSRF-Schutz, Konflikterkennung und die Sperre gegen
gleichzeitige Statusänderungen. Beim Abwählen werden die Gültigkeitsdaten
dieser Funktion gelöscht; die Datumsfelder sind ohne JavaScript bedienbar.
Die bisherige kontenbezogene Bearbeitungsroute bleibt für bestehende Links
erhalten, ist jedoch nicht mehr der Einstieg. Direkte CRUD-Schreib- und
Detailaktionen der Kontenliste sind gesperrt. Die erste Vereinsanlage wurde
zusätzlich mit zwei privaten MariaDB-Verbindungen auf konkurrierende Zugriffe
geprüft.

Vereinsfunktionen sollen frei angelegt und vorhandenen Berechtigungen
ausdrücklich zugeordnet werden können. Administratoren sollen nur bisher
delegierbare Rechte zuordnen können; privilegierte Rechte bleiben
Superadministratoren vorbehalten. Ob eine Nachfolge erforderlich ist, soll
pro Funktion konfigurierbar sein. Dieses Funktionsmodell, die Übernahme der
bestehenden Rollen und Zeiträume und die passende Nachfolgeprüfung sind noch
nicht implementiert. Geeignete Verwaltungsseiten sollen bevorzugt auf
EasyAdmin-Standardoberflächen umgestellt werden; eine vollständige Prüfung
und Umstellung steht ebenfalls aus.
