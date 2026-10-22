<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019140000 extends AbstractMigration {
 public function getDescription():string{return 'TÜV-Rechnungssnapshots pro Anmeldung';}
 public function up(Schema $schema):void {
  $this->addSql("CREATE TABLE tuv_invoice (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, number VARCHAR(40) DEFAULT NULL, recipient VARCHAR(180) NOT NULL, issuer VARCHAR(180) NOT NULL, issued_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', `lines` LONGTEXT NOT NULL COMMENT '(DC2Type:json)', net_cents INT NOT NULL, gross_cents INT NOT NULL, UNIQUE INDEX uniq_tuv_invoice_booking (booking_id), UNIQUE INDEX uniq_tuv_invoice_number (number), INDEX IDX_TUV_INVOICE_BOOKING (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
  $this->addSql('ALTER TABLE tuv_invoice ADD CONSTRAINT FK_TUV_INVOICE_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE RESTRICT');
 }
 public function down(Schema $schema):void {$this->addSql('DROP TABLE tuv_invoice');}
}
