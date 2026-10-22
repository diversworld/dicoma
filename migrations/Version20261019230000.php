<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019230000 extends AbstractMigration {
 public function getDescription(): string {return 'Nachvollziehbare Bar-Kassenkorrekturen je Originalbeleg';}
 public function up(Schema $schema): void { $this->addSql("CREATE TABLE equipment_cash_correction (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, source_type VARCHAR(12) NOT NULL, source_id INT NOT NULL, direction VARCHAR(3) NOT NULL, amount_cents INT NOT NULL, reason VARCHAR(500) NOT NULL, created_by VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_CASH_CORRECTION_CLUB (club_id), UNIQUE INDEX UNIQ_CASH_CORRECTION_SOURCE (club_id, source_type, source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB"); $this->addSql('ALTER TABLE equipment_cash_correction ADD CONSTRAINT FK_CASH_CORRECTION_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT'); }
 public function down(Schema $schema): void { $this->addSql('DROP TABLE equipment_cash_correction'); }
}
