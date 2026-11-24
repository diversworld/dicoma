<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add auditable production approval evidence for SEPA, SKR42, retention and WCAG.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE production_approval (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, sepa_approved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', sepa_approved_by VARCHAR(180) DEFAULT NULL, sepa_evidence VARCHAR(255) DEFAULT NULL, chart_approved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', chart_approved_by VARCHAR(180) DEFAULT NULL, chart_evidence VARCHAR(255) DEFAULT NULL, retention_approved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', retention_approved_by VARCHAR(180) DEFAULT NULL, retention_evidence VARCHAR(255) DEFAULT NULL, wcag_approved_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', wcag_approved_by VARCHAR(180) DEFAULT NULL, wcag_evidence VARCHAR(255) DEFAULT NULL, UNIQUE INDEX uniq_production_approval_club (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE production_approval ADD CONSTRAINT FK_PRODUCTION_APPROVAL_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE production_approval');
    }
}
