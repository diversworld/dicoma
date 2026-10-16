<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261013220000 extends AbstractMigration {
 public function getDescription(): string {return 'Vereinsbezogene Atemreglermodelle, eigenstaendige Atemregler und drei Inventarbeziehungen fuer Buchungspositionen';}
 public function up(Schema $schema): void {
  $this->addSql("CREATE TABLE regulator_model (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, manufacturer_id INT NOT NULL, stage VARCHAR(10) NOT NULL, name VARCHAR(120) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_REG_MODEL_CLUB (club_id), INDEX IDX_REG_MODEL_MANUFACTURER (manufacturer_id), UNIQUE INDEX uniq_reg_model_club_manufacturer_stage_name (club_id, manufacturer_id, stage, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
  $this->addSql("ALTER TABLE regulator_model ADD CONSTRAINT FK_REG_MODEL_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT");
  $this->addSql("ALTER TABLE regulator_model ADD CONSTRAINT FK_REG_MODEL_MANUFACTURER FOREIGN KEY (manufacturer_id) REFERENCES equipment_manufacturer (id) ON DELETE RESTRICT");
  $this->addSql("CREATE TABLE regulator (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, manufacturer_id INT NOT NULL, first_stage_model_id INT NOT NULL, primary_second_stage_model_id INT NOT NULL, secondary_second_stage_model_id INT DEFAULT NULL, inventory_number VARCHAR(100) NOT NULL, first_stage_serial VARCHAR(100) NOT NULL, primary_second_stage_serial VARCHAR(100) DEFAULT NULL, secondary_second_stage_serial VARCHAR(100) DEFAULT NULL, condition_status VARCHAR(20) NOT NULL DEFAULT 'ready', lendable TINYINT(1) NOT NULL DEFAULT 1, rental_fee NUMERIC(10,2) NOT NULL DEFAULT 0.00, next_service_at DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, UNIQUE INDEX uniq_regulator_club_inventory (club_id, inventory_number), INDEX IDX_REG_MANUFACTURER (manufacturer_id), INDEX IDX_REG_FIRST_MODEL (first_stage_model_id), INDEX IDX_REG_SECOND_MODEL (primary_second_stage_model_id), INDEX IDX_REG_SECOND_OPTIONAL (secondary_second_stage_model_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
  $this->addSql("ALTER TABLE regulator ADD CONSTRAINT FK_REG_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT");
  $this->addSql("ALTER TABLE regulator ADD CONSTRAINT FK_REG_MANUFACTURER FOREIGN KEY (manufacturer_id) REFERENCES equipment_manufacturer (id) ON DELETE RESTRICT");
  $this->addSql("ALTER TABLE regulator ADD CONSTRAINT FK_REG_FIRST_MODEL FOREIGN KEY (first_stage_model_id) REFERENCES regulator_model (id) ON DELETE RESTRICT");
  $this->addSql("ALTER TABLE regulator ADD CONSTRAINT FK_REG_SECOND_MODEL FOREIGN KEY (primary_second_stage_model_id) REFERENCES regulator_model (id) ON DELETE RESTRICT");
  $this->addSql("ALTER TABLE regulator ADD CONSTRAINT FK_REG_SECOND_OPTIONAL FOREIGN KEY (secondary_second_stage_model_id) REFERENCES regulator_model (id) ON DELETE RESTRICT");
  $this->addSql('ALTER TABLE tank ADD club_id INT DEFAULT NULL');
  $this->addSql('CREATE INDEX IDX_TANK_CLUB ON tank (club_id)');
  $this->addSql('ALTER TABLE tank ADD CONSTRAINT FK_TANK_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
  $this->addSql('ALTER TABLE equipment_booking_item DROP FOREIGN KEY FK_EQ_BI_ITEM');
  $this->addSql('ALTER TABLE equipment_booking_item CHANGE equipment_item_id equipment_item_id INT DEFAULT NULL, ADD regulator_id INT DEFAULT NULL, ADD tank_id INT DEFAULT NULL');
  $this->addSql('ALTER TABLE equipment_booking_item ADD CONSTRAINT FK_EQ_BI_ITEM FOREIGN KEY (equipment_item_id) REFERENCES equipment_item (id) ON DELETE RESTRICT');
  $this->addSql('ALTER TABLE equipment_booking_item ADD CONSTRAINT FK_EQ_BOOK_REG FOREIGN KEY (regulator_id) REFERENCES regulator (id) ON DELETE RESTRICT');
  $this->addSql('ALTER TABLE equipment_booking_item ADD CONSTRAINT FK_EQ_BOOK_TANK FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE RESTRICT');
  $this->addSql('CREATE INDEX IDX_EQ_BOOK_REG ON equipment_booking_item (regulator_id)');
  $this->addSql('CREATE INDEX IDX_EQ_BOOK_TANK ON equipment_booking_item (tank_id)');
  $this->addSql('CREATE UNIQUE INDEX uniq_booking_regulator ON equipment_booking_item (booking_id, regulator_id)');
  $this->addSql('CREATE UNIQUE INDEX uniq_booking_tank ON equipment_booking_item (booking_id, tank_id)');
 }
 public function down(Schema $schema): void {throw new \RuntimeException('Rollback requires an explicit data migration: regulator/tank bookings may exist.');}
}
