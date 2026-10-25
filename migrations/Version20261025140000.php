<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 6: shared inventory records, equipment maintenance, loan approval/closure and filling charges; preserve existing tank and rental history';
    }

    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE equipment_booking ADD approval_required TINYINT(1) DEFAULT 0 NOT NULL, ADD approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD approved_by VARCHAR(180) DEFAULT NULL, ADD closed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD closed_by VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE tank_maintenance_order MODIFY tank_id INT DEFAULT NULL, ADD equipment_item_id INT DEFAULT NULL, ADD regulator_id INT DEFAULT NULL, ADD kind VARCHAR(20) DEFAULT \'maintenance\' NOT NULL, ADD responsible VARCHAR(180) DEFAULT NULL, ADD opened_by VARCHAR(180) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_D850FFB664149DB1 ON tank_maintenance_order (equipment_item_id)');
        $this->addSql('CREATE INDEX IDX_D850FFB6624A5336 ON tank_maintenance_order (regulator_id)');
        $this->addSql('ALTER TABLE tank_maintenance_order ADD CONSTRAINT fk_maintenance_equipment FOREIGN KEY (equipment_item_id) REFERENCES equipment_item (id) ON DELETE RESTRICT, ADD CONSTRAINT fk_maintenance_regulator FOREIGN KEY (regulator_id) REFERENCES regulator (id) ON DELETE RESTRICT');
        $this->addSql('CREATE TABLE equipment_asset_record (id INT AUTO_INCREMENT NOT NULL, equipment_item_id INT DEFAULT NULL, regulator_id INT DEFAULT NULL, tank_id INT DEFAULT NULL, ownership VARCHAR(20) NOT NULL, owner_name VARCHAR(180) DEFAULT NULL, location VARCHAR(180) DEFAULT NULL, purchased_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', purchase_price_cents INT DEFAULT NULL, inspection_interval_months INT DEFAULT NULL, next_inspection_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', approval_required TINYINT(1) NOT NULL, history JSON NOT NULL, UNIQUE INDEX UNIQ_D672E71364149DB1 (equipment_item_id), UNIQUE INDEX UNIQ_D672E713624A5336 (regulator_id), UNIQUE INDEX UNIQ_D672E71315C652B5 (tank_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipment_document (id INT AUTO_INCREMENT NOT NULL, record_id INT NOT NULL, original_name VARCHAR(255) NOT NULL, storage_name VARCHAR(80) NOT NULL, mime_type VARCHAR(80) NOT NULL, size INT NOT NULL, uploaded_by VARCHAR(180) NOT NULL, uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E8AA0E504DFD750C (record_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE equipment_fill_charge (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, log_id INT DEFAULT NULL, batch_id INT DEFAULT NULL, amount_cents INT NOT NULL, method VARCHAR(20) DEFAULT NULL, collected_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', collected_by VARCHAR(180) DEFAULT NULL, INDEX IDX_93716D8761190A32 (club_id), UNIQUE INDEX UNIQ_93716D87EA675D86 (log_id), UNIQUE INDEX UNIQ_93716D87F39EBE7A (batch_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_asset_record ADD CONSTRAINT FK_D672E71364149DB1 FOREIGN KEY (equipment_item_id) REFERENCES equipment_item (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_D672E713624A5336 FOREIGN KEY (regulator_id) REFERENCES regulator (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_D672E71315C652B5 FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE equipment_document ADD CONSTRAINT FK_E8AA0E504DFD750C FOREIGN KEY (record_id) REFERENCES equipment_asset_record (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE equipment_fill_charge ADD CONSTRAINT FK_93716D8761190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_93716D87EA675D86 FOREIGN KEY (log_id) REFERENCES compressor_fill_log (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_93716D87F39EBE7A FOREIGN KEY (batch_id) REFERENCES compressor_fill_batch (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Inventory evidence, documents, maintenance and filling payments must not be discarded.');
    }
}
