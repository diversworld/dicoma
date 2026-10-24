# Rollen und Berechtigungen – bestätigter Phase-2-Vertrag

| Rolle | Bedeutung |
|---|---|
| `ROLE_USER` | Basis eines Benutzerkontos; begründet keine Vereinsmitgliedschaft. |
| `ROLE_MEMBER` | Abgeleitet aus vorhandenem Member-Profil, nicht leerem Verein, Status `ACTIVE` und fehlendem Austrittsdatum. |
| Funktionsrollen | Ausbilder, Gerätewart, Kompressorbediener, Kassenwart und Vorstand werden ausdrücklich vergeben. Sie machen ein inaktives Mitglied nicht automatisch aktiv. |
| `ROLE_ADMIN` | Verwaltung im Verein von `User.member.club`; ohne gültigen Kontext keine vereinsbezogenen Verwaltungsdaten. |
| `ROLE_SUPER_ADMIN` | Bewusst vereinsübergreifende Verwaltung einschließlich zentraler Personen und Benutzer-/Rollenverwaltung. |

Ein in der Datenbank gespeichertes `ROLE_MEMBER` allein gewährt keine Mitgliedsrechte. `getStoredRoles()` erhält die ursprünglichen Rollendaten; `getRoles()` berechnet die effektive direkte Rollenliste. `ROLE_MEMBER` wird nicht über die Funktionsrollen-Hierarchie vererbt. Die bestehenden fachlichen Hierarchien Vorstand -> Funktionsrollen, Admin -> Vorstand/Kassenwart und Superadmin -> Admin bleiben erhalten.

Separat geschützte Ausbilder- und Gerätewartportalrouten erhalten die passende Funktionsrolle als Zugangsvoraussetzung, auch wenn keine aktive Mitgliedschaft mehr besteht. Die jeweiligen Controller müssen zusätzlich den benötigten Personen-/Vereinsbezug prüfen. Mitgliederspezifische Routen wie `/my` bleiben solchen Konten ohne aktive Mitgliedschaft verwehrt.

## Verbindliche Objektprüfung

- Routenrollen ersetzen keine Objektberechtigung.
- Member-/Kurs-Voter vergleichen persistierte Vereins-IDs.
- Reguläre Admins können keine zentrale Personen-ID zur Umgehung von Vereinsgrenzen übergeben.
- Die zentrale Personenverwaltung und die Aufnahme aus einer existierenden Person sind auf Superadmins begrenzt. Dies vermeidet eine globale Personensuche durch Vereinsadmins.
- Dashboard-Qualifikationslisten und Zähler erhalten denselben Vereinsfilter.
- Noch globale Geräte-/Kassen-/Konfigurationsverwaltungen bleiben hinter ihren bestehenden Superadmin-Sperren; die gleichartigen Dashboard-Geräteaktionen werden ebenso gesperrt.
- Die zusätzlichen Legacy-Personenverwaltungen unter `/member`, `/student` und `/instructor` sind wegen ihrer ungefilterten Listen und Objektzugriffe ausschließlich Superadmins zugänglich. Reguläre Vereinsadmins verwenden die vorhandene, vereinsbezogen abgesicherte EasyAdmin-Mitgliederverwaltung; das Ausbilderportal bleibt davon getrennt.
- Eigene Mitglieder-Dokumentdownloads prüfen weiterhin die zugehörige Mitglieds-ID, nicht allein die Rolle.

## Betriebsfolge

Vor Freigabe müssen die Vereine betroffene Konten mit fehlendem Profil, fehlendem Verein, inaktivem Status oder Austrittsdatum prüfen. Diese Konten verlieren bewusst Mitgliedsrechte. Ein Funktionskonto benötigt für echte Mitgliedsrechte zusätzlich eine aktive Mitgliedschaft.

Die neue öffentliche Registrierung erzeugt keine automatisch aktive Vereinsmitgliedschaft. Ein Konto wird nach gültiger signierter Bestätigung nutzbar; Aufnahme und Rollenvergabe sind getrennte Vorgänge.
