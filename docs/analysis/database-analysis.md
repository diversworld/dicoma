# DICOMA – Datenbankanalyse

Statische Inventur; Datenbankverbindung und Migrationstest wurden nicht ausgeführt.

## Doctrine-Konfiguration
```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'

        # IMPORTANT: You MUST configure your server version,
        # either here or in the DATABASE_URL env var (see .env file)
        #server_version: '15'

        profiling_collect_backtrace: '%kernel.debug%'
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        controller_resolver:
            auto_mapping: true

        mappings:
            App:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App

when@test:
    doctrine:
        dbal:
            # "TEST_TOKEN" is typically set by ParaTest
            dbname_suffix: '_test%env(default::TEST_TOKEN)%'

when@prod:
    doctrine:
        orm:
            auto_generate_proxy_classes: false
            proxy_dir: '%kernel.build_dir%/doctrine/orm/Proxies'
            query_cache_driver:
                type: pool
                pool: doctrine.system_cache_pool
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool

    framework:
        cache:
            pools:
                doctrine.result_cache_pool:
                    adapter: cache.app
                doctrine.system_cache_pool:
                    adapter: cache.system

```

## Entities
- `src/Entity/Booking.php`
- `src/Entity/Brevets.php`
- `src/Entity/Club.php`
- `src/Entity/CompressorFillBatch.php`
- `src/Entity/CompressorFillCylinder.php`
- `src/Entity/CompressorFillLog.php`
- `src/Entity/CompressorFillSession.php`
- `src/Entity/CompressorMaintenance.php`
- `src/Entity/CourseAuditEvent.php`
- `src/Entity/CourseParticipant.php`
- `src/Entity/CourseTrainingUnit.php`
- `src/Entity/Courses.php`
- `src/Entity/EquipmentAttribute.php`
- `src/Entity/EquipmentBooking.php`
- `src/Entity/EquipmentBookingItem.php`
- `src/Entity/EquipmentCashClosing.php`
- `src/Entity/EquipmentCashPayout.php`
- `src/Entity/EquipmentCategory.php`
- `src/Entity/EquipmentItem.php`
- `src/Entity/EquipmentManufacturer.php`
- `src/Entity/EquipmentModel.php`
- `src/Entity/EquipmentRegulatorDetails.php`
- `src/Entity/EquipmentRentalReceipt.php`
- `src/Entity/EquipmentReservation.php`
- `src/Entity/EquipmentSize.php`
- `src/Entity/EquipmentSubType.php`
- `src/Entity/Member.php`
- `src/Entity/MemberDocument.php`
- `src/Entity/MemberQualification.php`
- `src/Entity/MemberSport.php`
- `src/Entity/MemberTank.php`
- `src/Entity/MemberTankCheckBooking.php`
- `src/Entity/MemberTankCheckBookingItem.php`
- `src/Entity/MemberTankCheckResult.php`
- `src/Entity/MemberTankCheckStatusHistory.php`
- `src/Entity/Qualification.php`
- `src/Entity/QualificationTrainingUnit.php`
- `src/Entity/Regulator.php`
- `src/Entity/RegulatorModel.php`
- `src/Entity/ResetPasswordRequest.php`
- `src/Entity/Schedule.php`
- `src/Entity/ScheduleTemplate.php`
- `src/Entity/ScheduleTemplateEntry.php`
- `src/Entity/Sport.php`
- `src/Entity/Tank.php`
- `src/Entity/TankCheck.php`
- `src/Entity/TankCheckArticle.php`
- `src/Entity/TankCheckDetail.php`
- `src/Entity/TankConditionHistory.php`
- `src/Entity/TankInspectionResult.php`
- `src/Entity/TankMaintenanceOrder.php`
- `src/Entity/Team.php`
- `src/Entity/TeamMember.php`
- `src/Entity/TrainingUnitResult.php`
- `src/Entity/TrainingUnitType.php`
- `src/Entity/TuvBookingGroup.php`
- `src/Entity/TuvDocument.php`
- `src/Entity/TuvGroupBooking.php`
- `src/Entity/TuvHandover.php`
- `src/Entity/TuvInvoice.php`
- `src/Entity/TuvInvoiceSettings.php`
- `src/Entity/TuvMessageTemplate.php`
- `src/Entity/TuvNotification.php`
- `src/Entity/TuvPaymentReceipt.php`
- `src/Entity/User.php`
- `src/Entity/Vendor.php`

## Migrationen
- `migrations/Version20240108075321.php`
- `migrations/Version20240108085448.php`
- `migrations/Version20240108102137.php`
- `migrations/Version20240108103412.php`
- `migrations/Version20240108104154.php`
- `migrations/Version20240108112801.php`
- `migrations/Version20240108122148.php`
- `migrations/Version20240108123807.php`
- `migrations/Version20240110135446.php`
- `migrations/Version20240110143303.php`
- `migrations/Version20240110143431.php`
- `migrations/Version20240110143914.php`
- `migrations/Version20240110144623.php`
- `migrations/Version20240112130614.php`
- `migrations/Version20240115161153.php`
- `migrations/Version20240118160400.php`
- `migrations/Version20240208082808.php`
- `migrations/Version20240208090320.php`
- `migrations/Version20240208090722.php`
- `migrations/Version20240208090752.php`
- `migrations/Version20240210143454.php`
- `migrations/Version20240216141057.php`
- `migrations/Version20240217125907.php`
- `migrations/Version20240217132002.php`
- `migrations/Version20240218165946.php`
- `migrations/Version20240222122526.php`
- `migrations/Version20240223122358.php`
- `migrations/Version20240223132435.php`
- `migrations/Version20240223144423.php`
- `migrations/Version20240224114153.php`
- `migrations/Version20240224121656.php`
- `migrations/Version20240226122112.php`
- `migrations/Version20240226125139.php`
- `migrations/Version20240226130939.php`
- `migrations/Version20240301140503.php`
- `migrations/Version20240301152035.php`
- `migrations/Version20240302084923.php`
- `migrations/Version20240623112012.php`
- `migrations/Version20240830154716.php`
- `migrations/Version20240831123522.php`
- `migrations/Version20240831131002.php`
- `migrations/Version20240831131757.php`
- `migrations/Version20261006075301.php`
- `migrations/Version20261006103259.php`
- `migrations/Version20261006104253.php`
- `migrations/Version20261006105355.php`
- `migrations/Version20261007073905.php`
- `migrations/Version20261007075848.php`
- `migrations/Version20261007080610.php`
- `migrations/Version20261007082248.php`
- `migrations/Version20261007092333.php`
- `migrations/Version20261007111716.php`
- `migrations/Version20261007115317.php`
- `migrations/Version20261007121111.php`
- `migrations/Version20261007132228.php`
- `migrations/Version20261008071046.php`
- `migrations/Version20261008081433.php`
- `migrations/Version20261008094721.php`
- `migrations/Version20261008095454.php`
- `migrations/Version20261008110126.php`
- `migrations/Version20261008115148.php`
- `migrations/Version20261008125209.php`
- `migrations/Version20261008130207.php`
- `migrations/Version20261009091308.php`
- `migrations/Version20261009131400.php`
- `migrations/Version20261009162913.php`
- `migrations/Version20261010122243.php`
- `migrations/Version20261012130000.php`
- `migrations/Version20261012160000.php`
- `migrations/Version20261013110000.php`
- `migrations/Version20261013120000.php`
- `migrations/Version20261013130000.php`
- `migrations/Version20261013150000.php`
- `migrations/Version20261013170000.php`
- `migrations/Version20261013190000.php`
- `migrations/Version20261013200000.php`
- `migrations/Version20261013210000.php`
- `migrations/Version20261013220000.php`
- `migrations/Version20261013235000.php`
- `migrations/Version20261014100000.php`
- `migrations/Version20261014120000.php`
- `migrations/Version20261014130000.php`
- `migrations/Version20261016090000.php`
- `migrations/Version20261016110000.php`
- `migrations/Version20261016120000.php`
- `migrations/Version20261016140000.php`
- `migrations/Version20261016150000.php`
- `migrations/Version20261016160000.php`
- `migrations/Version20261016170000.php`
- `migrations/Version20261016180000.php`
- `migrations/Version20261016194500.php`
- `migrations/Version20261016220000.php`
- `migrations/Version20261017140000.php`
- `migrations/Version20261017173000.php`
- `migrations/Version20261017180000.php`
- `migrations/Version20261017183000.php`
- `migrations/Version20261017200000.php`
- `migrations/Version20261017210000.php`
- `migrations/Version20261017220000.php`
- `migrations/Version20261018150000.php`
- `migrations/Version20261019140000.php`
- `migrations/Version20261019153000.php`
- `migrations/Version20261019160000.php`
- `migrations/Version20261019170000.php`
- `migrations/Version20261019180000.php`
- `migrations/Version20261019190000.php`
- `migrations/Version20261019195000.php`
- `migrations/Version20261019210000.php`
- `migrations/Version20261019230000.php`
- `migrations/Version20261020090000.php`
- `migrations/Version20261020120000.php`
- `migrations/Version20261020143000.php`
- `migrations/Version20261020150000.php`
- `migrations/Version20261020151000.php`
- `migrations/Version20261020160000.php`
- `migrations/Version20261020170000.php`
- `migrations/Version20261020180000.php`

## Risiken und Prüfaufträge
- Bestehende Relationen, FK-Constraints, Unique-Indizes und Nullable-Felder anhand realer DB prüfen.
- Migrationen mit Kopie bestehender Daten auf Ausführbarkeit prüfen.
- Personen-/Mitgliedschaftsmodell, Club-Isolation, Buchungskonkurrenz und Kassenbuchungskonsistenz vertieft untersuchen.
- Keine destruktiven Migrationen ohne Freigabe.
