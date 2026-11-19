<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add managed equipment locations'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('CREATE TABLE equipment_location (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, name VARCHAR(180) NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_equipment_location_club_name (club_id, name), INDEX IDX_6E5D0DED61190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_location ADD CONSTRAINT FK_equipment_location_club FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE equipment_asset_record ADD structured_location_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_D672E713F70E658C ON equipment_asset_record (structured_location_id)');
        $this->addSql('ALTER TABLE equipment_asset_record ADD CONSTRAINT FK_equipment_asset_structured_location FOREIGN KEY (structured_location_id) REFERENCES equipment_location (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE equipment_asset_record DROP FOREIGN KEY FK_equipment_asset_structured_location');
        $this->addSql('ALTER TABLE equipment_asset_record DROP INDEX IDX_D672E713F70E658C, DROP structured_location_id');
        $this->addSql('DROP TABLE equipment_location');
    }
}
