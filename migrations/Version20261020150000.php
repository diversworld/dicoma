<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261020150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Append-only compressor breathing-air fill log per club'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE compressor_fill_log (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, recorded_by_id INT NOT NULL, tank_id INT DEFAULT NULL, cylinder_identification VARCHAR(100) NOT NULL, compressor_identification VARCHAR(100) NOT NULL, gas_type VARCHAR(30) NOT NULL, start_pressure_bar INT NOT NULL, end_pressure_bar INT NOT NULL, operating_hours_tenths INT DEFAULT NULL, filter_reference VARCHAR(120) DEFAULT NULL, cylinder_check_confirmed TINYINT(1) NOT NULL, air_quality_confirmed TINYINT(1) NOT NULL, filter_check_confirmed TINYINT(1) NOT NULL, notes LONGTEXT DEFAULT NULL, filled_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_fill_club_date (club_id, filled_at), INDEX IDX_FILL_USER (recorded_by_id), INDEX IDX_FILL_TANK (tank_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE compressor_fill_log ADD CONSTRAINT FK_FILL_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE compressor_fill_log ADD CONSTRAINT FK_FILL_USER FOREIGN KEY (recorded_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE compressor_fill_log ADD CONSTRAINT FK_FILL_TANK FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE SET NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE compressor_fill_log');
    }
}
