<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261017200000 extends AbstractMigration {
 public function getDescription():string{return 'TÜV Übergaben, private Dokumente, Mehrfachgruppen, Benachrichtigungs-Postausgang';}
 public function up(Schema $schema):void{
  $this->addSql("CREATE TABLE tuv_handover (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, event_type VARCHAR(20) NOT NULL, occurred_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', actor VARCHAR(180) NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_TUV_HANDOVER_BOOKING (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
  $this->addSql('ALTER TABLE tuv_handover ADD CONSTRAINT FK_TUV_HANDOVER_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE CASCADE');
  $this->addSql("CREATE TABLE tuv_document (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, stored_name VARCHAR(80) NOT NULL, original_name VARCHAR(255) NOT NULL, mime VARCHAR(80) NOT NULL, size INT NOT NULL, sha256 VARCHAR(64) NOT NULL, uploaded_by VARCHAR(180) NOT NULL, uploaded_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_TUV_DOCUMENT_BOOKING (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
  $this->addSql('ALTER TABLE tuv_document ADD CONSTRAINT FK_TUV_DOCUMENT_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE CASCADE');
  $this->addSql("CREATE TABLE tuv_booking_group (id INT AUTO_INCREMENT NOT NULL, tank_check_id INT NOT NULL, created_by VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_TUV_GROUP_CHECK (tank_check_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
  $this->addSql('ALTER TABLE tuv_booking_group ADD CONSTRAINT FK_TUV_GROUP_CHECK FOREIGN KEY (tank_check_id) REFERENCES tank_check (id) ON DELETE RESTRICT');
  $this->addSql("CREATE TABLE tuv_group_booking (id INT AUTO_INCREMENT NOT NULL, group_id INT NOT NULL, booking_id INT NOT NULL, INDEX IDX_TUV_GROUP_MEMBER_GROUP (group_id), UNIQUE INDEX uniq_tuv_group_booking (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
  $this->addSql('ALTER TABLE tuv_group_booking ADD CONSTRAINT FK_TUV_GROUP_MEMBER_GROUP FOREIGN KEY (group_id) REFERENCES tuv_booking_group (id) ON DELETE CASCADE');
  $this->addSql('ALTER TABLE tuv_group_booking ADD CONSTRAINT FK_TUV_GROUP_MEMBER_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE CASCADE');
  $this->addSql("CREATE TABLE tuv_notification (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, dedupe_key VARCHAR(120) NOT NULL, recipient VARCHAR(180) NOT NULL, subject VARCHAR(200) NOT NULL, body LONGTEXT NOT NULL, state VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', sent_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_TUV_NOTIFICATION_BOOKING (booking_id), UNIQUE INDEX uniq_tuv_notification_key (dedupe_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
  $this->addSql('ALTER TABLE tuv_notification ADD CONSTRAINT FK_TUV_NOTIFICATION_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE CASCADE');
 }
 public function down(Schema $schema):void{foreach(['tuv_notification','tuv_group_booking','tuv_booking_group','tuv_document','tuv_handover'] as $table)$this->addSql('DROP TABLE '.$table);}
}
