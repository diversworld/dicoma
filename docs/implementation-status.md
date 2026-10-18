# DICOMA – Bestandsaufnahme und Startpriorität (Phase 48)

## Quellen und Aussagekraft
- Geprüft: bereitgestelltes DICOMA-Archiv `dicoma171001.zip` (älterer Entwicklungsstand), Contao-Referenzarchiv `contao-diveclub-bundle-main(2).zip` und Anforderungsliste.
- **Nicht geprüft:** aktuelle Plesk-Installation nach Phasen 36–47, echte Datenbank, Mailserver und vollständige Integration.
- Diese Analyse ist deshalb keine Aussage, dass neuere ZIP-Patches bereits produktiv funktionieren.

## Nachgewiesener Stand im DICOMA-Archiv
- Symfony 7, Doctrine, Security, EasyAdmin, Twig und Mailer sind in `composer.json` vorgesehen.
- `config/routes.yaml` aktiviert Attribut-Routen und EasyAdmin-Routen.
- Login und rollenbasierte Security-Konfiguration sind vorhanden.
- Entities: `EquipmentItem`, `EquipmentCategory`, `EquipmentManufacturer`, `EquipmentModel`, `EquipmentReservation`, `EquipmentBooking`, `EquipmentBookingItem`, `MemberTank`, `Tank`, `TankCheck`, `TankCheckArticle`, `MemberTankCheckBooking` und weitere.
- Ein `/health`-Endpunkt existiert, prüft aber nur die HTTP-Erreichbarkeit, **nicht** die Datenbank.
- PHPUnit-Tests existieren; vollständige Abdeckung der Geschäftsregeln ist nicht belegt.
- Die aktuelle Implementierung von Benachrichtigungen und Migrationen nach Phase 35 ist in diesem Archiv nicht enthalten.

## Fachlicher Umsetzungsgrad (qualitativ, keine Prozentangaben)
| Bereich | Befund | Nächster Schritt |
|---|---|---|
| Symfony-Basis / Anmeldung | im Archiv vorhanden, produktive Funktion nicht verifiziert | Bereitschaftsprüfung und Smoke-Test |
| Geräte / Flaschen | Datenmodell und Verwaltungsbausteine vorhanden | Kernabläufe testen |
| TÜV | Termin-/Buchungsbausteine vorhanden, neuere Phasen nicht im Archiv | Status und Versand end-to-end testen |
| Verleih | Reservierungs-/Buchungsmodelle vorhanden | Ausgabe/Rückgabe als ersten vollständigen Ablauf prüfen |
| Rechnungen | nicht als vollständig nachgewiesen | zunächst zurückstellen |
| Dokumente / Exporte | teils vorhanden; Vollständigkeit offen | später prüfen |
| Sicherheit / Tests | Basis vorhanden, End-to-End-Nachweis fehlt | Smoke-Tests und Rechteprüfung |

## Entscheidung für den ersten Schritt
Keine Umstellung der Datenbank, kein Umbau bestehender Entities, keine riskante Migration.
Stattdessen wird ein **lesender** Konsolenbefehl zur Prüfung von PHP, benötigten Erweiterungen und DB-Verbindung ergänzt.
Das ist ein kleiner, überprüfbarer Schritt in Richtung einer zuverlässig startbaren Umgebung.

## Fachliche Referenz
Das Contao-Archiv enthält DCA-Definitionen u. a. für Geräte, Flaschen, Atemregler, TÜV-Angebote und Buchungen.
Es dient ausschließlich als fachliche Orientierung, nicht als Codebasis. Die `composer.json` der Referenz deklariert `GPL-3.0-or-later`; eine separate Lizenzdatei liegt im ZIP nicht vor. Für dieses Patch wurde kein Contao-Quellcode übernommen.

## Nächste kleine Arbeitspakete
1. Bereitschaftsprüfung und bestehende Tests auf Plesk ausführen.
2. Aktuellen Projektstand inklusive der zuletzt eingespielten Patches abgleichen.
3. Einen TÜV-Testfall von Buchung bis E-Mail-Versand durchlaufen.
4. Einen Verleih-Testfall von Reservierung bis Rückgabe durchlaufen.
5. Erst danach fehlende Detailfunktionen ergänzen.
