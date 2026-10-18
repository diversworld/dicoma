<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repariert fehlende Phase-29-Spalten und Constraints ohne bestehende TÜV-Daten zu verändern';
    }

    public function up(Schema $schema): void
    {
        $table = 'member_tank_check_booking';
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Nur MySQL/MariaDB unterstützt.');

        if (!$this->columnExists($table, 'club_tank_id')) {
            $this->addSql('ALTER TABLE member_tank_check_booking ADD club_tank_id INT DEFAULT NULL');
        }
        if (!$this->columnNullable($table, 'member_id')) {
            $this->addSql('ALTER TABLE member_tank_check_booking MODIFY member_id INT DEFAULT NULL');
        }
        if (!$this->columnNullable($table, 'member_tank_id')) {
            $this->addSql('ALTER TABLE member_tank_check_booking MODIFY member_tank_id INT DEFAULT NULL');
        }
        if (!$this->indexExists($table, 'IDX_CD4256C3188EF737') && !$this->indexExists($table, 'IDX_TUV_BOOKING_CLUB_TANK')) {
            $this->addSql('CREATE INDEX IDX_CD4256C3188EF737 ON member_tank_check_booking (club_tank_id)');
        }
        if (!$this->indexExists($table, 'UNIQ_TUV_CLUB_TANK_CHECK')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_TUV_CLUB_TANK_CHECK ON member_tank_check_booking (club_tank_id, tank_check_id)');
        }
        if (!$this->foreignKeyOnColumnExists($table, 'club_tank_id')) {
            $this->addSql('ALTER TABLE member_tank_check_booking ADD CONSTRAINT FK_CD4256C3188EF737 FOREIGN KEY (club_tank_id) REFERENCES tank (id) ON DELETE RESTRICT');
        }
    }

    public function down(Schema $schema): void
    {
        // Bewusst keine automatische Rücknahme: Bestehende Vereinsbuchungen könnten Daten verlieren.
        $this->write('Keine automatische Rücknahme, um Vereinsbuchungen zu schützen.');
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        ) > 0;
    }

    private function columnNullable(string $table, string $column): bool
    {
        $nullable = $this->connection->fetchOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
        $this->abortIf($nullable === false, sprintf('Spalte %s.%s fehlt.', $table, $column));
        return $nullable === 'YES';
    }

    private function indexExists(string $table, string $index): bool
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        ) > 0;
    }

    private function foreignKeyOnColumnExists(string $table, string $column): bool
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$table, $column]
        ) > 0;
    }
}
