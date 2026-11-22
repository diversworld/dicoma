<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add Phase B periodic contribution classes, member profile/history, communication templates and finance indexes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE contribution_class ADD billing_period VARCHAR(10) DEFAULT 'yearly' NOT NULL, ADD monthly_cents INT DEFAULT 0 NOT NULL, ADD member_category VARCHAR(30) DEFAULT 'standard' NOT NULL");
        $this->addSql("ALTER TABLE person ADD gender VARCHAR(20) DEFAULT NULL, ADD profile_image_storage_name VARCHAR(45) DEFAULT NULL");
        $this->addSql("CREATE TABLE communication_template (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, code VARCHAR(60) NOT NULL, name VARCHAR(120) NOT NULL, subject VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, active TINYINT(1) NOT NULL, updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_communication_template_code (club_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE membership_history (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, actor VARCHAR(180) NOT NULL, action VARCHAR(80) NOT NULL, changes LONGTEXT NOT NULL COMMENT '(DC2Type:json)', occurred_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_membership_history_member_at (member_id, occurred_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE communication_template ADD CONSTRAINT FK_COMMUNICATION_TEMPLATE_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_history ADD CONSTRAINT FK_MEMBERSHIP_HISTORY_MEMBER FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX idx_membership_charge_member_due ON membership_charge (member_id, due_at)');
        $this->addSql('CREATE INDEX idx_internal_message_template_club_sent ON internal_message (club_id, audience, sent_at)');
        $this->addSql('CREATE INDEX idx_contribution_class_active_period ON contribution_class (club_id, active, billing_period)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_contribution_class_active_period ON contribution_class');
        $this->addSql('DROP INDEX idx_internal_message_template_club_sent ON internal_message');
        $this->addSql('DROP INDEX idx_membership_charge_member_due ON membership_charge');
        $this->addSql('DROP TABLE membership_history');
        $this->addSql('DROP TABLE communication_template');
        $this->addSql('ALTER TABLE person DROP profile_image_storage_name, DROP gender');
        $this->addSql('ALTER TABLE contribution_class DROP member_category, DROP monthly_cents, DROP billing_period');
    }
}
