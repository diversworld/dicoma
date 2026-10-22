# DICOMA – Vorläufige Funktionsmatrix

**Wichtig:** Dateiexistenz belegt keine vollständige Funktion. Status „teilweise / ungeprüft“ bedeutet nur, dass zugehörige Komponenten auffindbar sind.

| Modul | Nachweis | Status | Priorität |
|---|---|---|---|
| Mitglieder | src/Entity/Member.php; src/Entity/MemberDocument.php; src/Entity/MemberQualification.php; src/Entity/MemberSpo | teilweise / ungeprüft | hoch |
| Ausbildung | src/Entity/CourseAuditEvent.php; src/Entity/CourseParticipant.php; src/Entity/CourseTrainingUnit.php; src/Enti | teilweise / ungeprüft | hoch |
| Mannschaften | src/Entity/Team.php; src/Entity/TeamMember.php; src/Controller/Admin/TeamCrudController.php; src/Controller/Ad | teilweise / ungeprüft | mittel |
| Turniere | Keine namensgleiche PHP-Komponente identifiziert | nicht nachgewiesen | mittel |
| Ausrüstung | src/Entity/EquipmentAttribute.php; src/Entity/EquipmentBooking.php; src/Entity/EquipmentBookingItem.php; src/E | teilweise / ungeprüft | hoch |
| Flaschen | src/Entity/MemberTank.php; src/Entity/MemberTankCheckBooking.php; src/Entity/MemberTankCheckBookingItem.php; s | teilweise / ungeprüft | hoch |
| Gerätewartkasse | src/Entity/EquipmentCashClosing.php; src/Entity/EquipmentCashPayout.php; src/Controller/EquipmentManagerCashCl | teilweise / ungeprüft | kritisch |
| Thekendienste | Keine namensgleiche PHP-Komponente identifiziert | nicht nachgewiesen | mittel |
| Veranstaltungen | src/Entity/CourseAuditEvent.php | teilweise / ungeprüft | mittel |
| Clubreisen | Keine namensgleiche PHP-Komponente identifiziert | nicht nachgewiesen | mittel |
| Kalender | src/Entity/Schedule.php; src/Entity/ScheduleTemplate.php; src/Entity/ScheduleTemplateEntry.php; src/Controller | teilweise / ungeprüft | hoch |
| Dokumente | src/Entity/MemberDocument.php; src/Entity/TuvDocument.php; src/Service/PrivateMemberDocumentStorage.php; src/S | teilweise / ungeprüft | hoch |
| Benachrichtigungen | src/Entity/TuvNotification.php; src/Controller/Admin/TuvNotificationCrudController.php; src/Controller/Admin/T | teilweise / ungeprüft | mittel |
| Vereine/Mandanten | src/Entity/Club.php; src/Controller/Admin/ClubCrudController.php; src/Controller/Admin/ClubTankCheckBookingSav | teilweise / ungeprüft | kritisch |
| Berechtigungen | src/Controller/SecurityController.php | teilweise / ungeprüft | kritisch |

Die ausführliche Matrix mit allen neun Feldern liegt als `feature-matrix.csv` vor. Eine vollständige Anforderung-zu-Methode-Abnahme steht aus.
