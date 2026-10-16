<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013210000 extends AbstractMigration
{
    public function getDescription(): string { return 'Link tank inventory and add regulator technical details'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_item ADD tank_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EQUIPMENT_ITEM_TANK ON equipment_item (tank_id)');
        $this->addSql('ALTER TABLE equipment_item ADD CONSTRAINT FK_EQUIPMENT_ITEM_TANK FOREIGN KEY (tank_id) REFERENCES tank (id)');
        $this->addSql('CREATE TABLE equipment_regulator_details (id INT AUTO_INCREMENT NOT NULL, equipment_item_id INT NOT NULL, first_stage_serial VARCHAR(120) NOT NULL, first_stage_model VARCHAR(160) DEFAULT NULL, primary_second_stage_serial VARCHAR(120) NOT NULL, primary_second_stage_model VARCHAR(160) DEFAULT NULL, secondary_second_stage_serial VARCHAR(120) DEFAULT NULL, secondary_second_stage_model VARCHAR(160) DEFAULT NULL, next_service_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', UNIQUE INDEX UNIQ_EQUIPMENT_REGULATOR_ITEM (equipment_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_regulator_details ADD CONSTRAINT FK_EQUIPMENT_REGULATOR_ITEM FOREIGN KEY (equipment_item_id) REFERENCES equipment_item (id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE equipment_regulator_details');
        $this->addSql('ALTER TABLE equipment_item DROP FOREIGN KEY FK_EQUIPMENT_ITEM_TANK');
        $this->addSql('DROP INDEX UNIQ_EQUIPMENT_ITEM_TANK ON equipment_item');
        $this->addSql('ALTER TABLE equipment_item DROP tank_id');
    }
}
