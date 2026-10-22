<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DICOMA: index names, redundant TUV invoice index; preserve club payout index';
    }

    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Only MySQL is supported.');
        // Fail closed if production index state differs from the reviewed schema.
        $expected = [
            ['compressor_maintenance', 'idx_cm_club', 'IDX_BD52903A61190A32'],
            ['compressor_maintenance', 'idx_cm_user', 'IDX_BD52903AD05A957B'],
            ['compressor_fill_cylinder', 'idx_cfc_batch', 'IDX_3F3D12DDF39EBE7A'],
            ['compressor_fill_cylinder', 'idx_cfc_tank', 'IDX_3F3D12DD15C652B5'],
            ['compressor_fill_batch', 'idx_cfb_session', 'IDX_C3F6EFFC613FECDF'],
            ['compressor_fill_batch', 'idx_cfb_user', 'IDX_C3F6EFFCD05A957B'],
            ['compressor_fill_session', 'idx_cfs_club', 'IDX_38C98FB261190A32'],
            ['compressor_fill_session', 'idx_cfs_user', 'IDX_38C98FB2584598A3'],
            ['courses', 'idx_course_lead_instructor', 'IDX_A9A55A4CB6AD0179'],
            ['compressor_fill_log', 'idx_fill_user', 'IDX_1B20D548D05A957B'],
            ['compressor_fill_log', 'idx_fill_tank', 'IDX_1B20D54815C652B5'],
        ];
        foreach ($expected as [$table, $old, $new]) {
            $this->abortIf(!$this->indexExists($table, $old), 'Missing source index: ' . $table . '.' . $old);
            $this->abortIf($this->indexExists($table, $new), 'Target index already exists: ' . $table . '.' . $new);
        }
        $this->abortIf(!$this->indexExists('tuv_invoice', 'IDX_TUV_INVOICE_BOOKING'), 'Missing redundant invoice index.');
        $this->abortIf(!$this->indexExists('tuv_invoice', 'uniq_tuv_invoice_booking'), 'Required unique booking index missing.');
        $this->abortIf(!$this->indexExists('equipment_cash_payout', 'idx_cash_payout_club_date'), 'Required payout index missing.');
        // Verify both invoice indexes address booking_id before removing the redundant one.
        $this->abortIf($this->indexColumns('tuv_invoice', 'IDX_TUV_INVOICE_BOOKING') !== ['booking_id'], 'Invoice index columns differ.');
        $this->abortIf($this->indexColumns('tuv_invoice', 'uniq_tuv_invoice_booking') !== ['booking_id'], 'Unique invoice index columns differ.');
        $this->abortIf($this->indexColumns('equipment_cash_payout', 'idx_cash_payout_club_date') !== ['club_id', 'paid_at'], 'Payout index columns differ.');
        $this->addSql("ALTER TABLE `compressor_maintenance` RENAME INDEX `idx_cm_club` TO `IDX_BD52903A61190A32`");
        $this->addSql("ALTER TABLE `compressor_maintenance` RENAME INDEX `idx_cm_user` TO `IDX_BD52903AD05A957B`");
        $this->addSql("ALTER TABLE `compressor_fill_cylinder` RENAME INDEX `idx_cfc_batch` TO `IDX_3F3D12DDF39EBE7A`");
        $this->addSql("ALTER TABLE `compressor_fill_cylinder` RENAME INDEX `idx_cfc_tank` TO `IDX_3F3D12DD15C652B5`");
        $this->addSql("ALTER TABLE `compressor_fill_batch` RENAME INDEX `idx_cfb_session` TO `IDX_C3F6EFFC613FECDF`");
        $this->addSql("ALTER TABLE `compressor_fill_batch` RENAME INDEX `idx_cfb_user` TO `IDX_C3F6EFFCD05A957B`");
        $this->addSql("ALTER TABLE `compressor_fill_session` RENAME INDEX `idx_cfs_club` TO `IDX_38C98FB261190A32`");
        $this->addSql("ALTER TABLE `compressor_fill_session` RENAME INDEX `idx_cfs_user` TO `IDX_38C98FB2584598A3`");
        $this->addSql("ALTER TABLE `courses` RENAME INDEX `idx_course_lead_instructor` TO `IDX_A9A55A4CB6AD0179`");
        $this->addSql("ALTER TABLE `compressor_fill_log` RENAME INDEX `idx_fill_user` TO `IDX_1B20D548D05A957B`");
        $this->addSql("ALTER TABLE `compressor_fill_log` RENAME INDEX `idx_fill_tank` TO `IDX_1B20D54815C652B5`");
        $this->addSql('ALTER TABLE `tuv_invoice` DROP INDEX `IDX_TUV_INVOICE_BOOKING`');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Only MySQL is supported.');
        $this->addSql('ALTER TABLE `tuv_invoice` ADD INDEX `IDX_TUV_INVOICE_BOOKING` (`booking_id`)');
        $this->addSql("ALTER TABLE `compressor_fill_log` RENAME INDEX `IDX_1B20D54815C652B5` TO `idx_fill_tank`");
        $this->addSql("ALTER TABLE `compressor_fill_log` RENAME INDEX `IDX_1B20D548D05A957B` TO `idx_fill_user`");
        $this->addSql("ALTER TABLE `courses` RENAME INDEX `IDX_A9A55A4CB6AD0179` TO `idx_course_lead_instructor`");
        $this->addSql("ALTER TABLE `compressor_fill_session` RENAME INDEX `IDX_38C98FB2584598A3` TO `idx_cfs_user`");
        $this->addSql("ALTER TABLE `compressor_fill_session` RENAME INDEX `IDX_38C98FB261190A32` TO `idx_cfs_club`");
        $this->addSql("ALTER TABLE `compressor_fill_batch` RENAME INDEX `IDX_C3F6EFFCD05A957B` TO `idx_cfb_user`");
        $this->addSql("ALTER TABLE `compressor_fill_batch` RENAME INDEX `IDX_C3F6EFFC613FECDF` TO `idx_cfb_session`");
        $this->addSql("ALTER TABLE `compressor_fill_cylinder` RENAME INDEX `IDX_3F3D12DD15C652B5` TO `idx_cfc_tank`");
        $this->addSql("ALTER TABLE `compressor_fill_cylinder` RENAME INDEX `IDX_3F3D12DDF39EBE7A` TO `idx_cfc_batch`");
        $this->addSql("ALTER TABLE `compressor_maintenance` RENAME INDEX `IDX_BD52903AD05A957B` TO `idx_cm_user`");
        $this->addSql("ALTER TABLE `compressor_maintenance` RENAME INDEX `IDX_BD52903A61190A32` TO `idx_cm_club`");
    }

    private function indexExists(string $table, string $name): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?', [$table, $name]) > 0;
    }

    private function indexColumns(string $table, string $name): array
    {
        return $this->connection->fetchFirstColumn('SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX', [$table, $name]);
    }
}
