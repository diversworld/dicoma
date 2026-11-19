<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Configure membership application quorum and onboarding; record individual approvals.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership_policy ADD required_approval_count INT DEFAULT 1 NOT NULL, ADD onboarding_contribution_class_id INT DEFAULT NULL, ADD onboarding_issue_annual_charge TINYINT(1) DEFAULT 0 NOT NULL, ADD onboarding_charge_due VARCHAR(5) DEFAULT NULL');
        $this->addSql('ALTER TABLE membership_policy ADD CONSTRAINT FK_POLICY_ONBOARDING_CONTRIBUTION FOREIGN KEY (onboarding_contribution_class_id) REFERENCES contribution_class (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_POLICY_ONBOARDING_CONTRIBUTION ON membership_policy (onboarding_contribution_class_id)');
        $this->addSql('CREATE TABLE membership_application_approval (id INT AUTO_INCREMENT NOT NULL, application_id INT NOT NULL, reviewer_id INT NOT NULL, reviewer_name VARCHAR(180) NOT NULL, note LONGTEXT NOT NULL, approved_at DATETIME NOT NULL, UNIQUE INDEX uniq_application_reviewer (application_id, reviewer_id), INDEX IDX_APPLICATION_APPROVAL_APPLICATION (application_id), INDEX IDX_APPLICATION_APPROVAL_REVIEWER (reviewer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE membership_application_approval ADD CONSTRAINT FK_APPLICATION_APPROVAL_APPLICATION FOREIGN KEY (application_id) REFERENCES membership_application (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membership_application_approval ADD CONSTRAINT FK_APPLICATION_APPROVAL_REVIEWER FOREIGN KEY (reviewer_id) REFERENCES user (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE membership_application_approval');
        $this->addSql('ALTER TABLE membership_policy DROP FOREIGN KEY FK_POLICY_ONBOARDING_CONTRIBUTION');
        $this->addSql('DROP INDEX IDX_POLICY_ONBOARDING_CONTRIBUTION ON membership_policy');
        $this->addSql('ALTER TABLE membership_policy DROP required_approval_count, DROP onboarding_contribution_class_id, DROP onboarding_issue_annual_charge, DROP onboarding_charge_due');
    }
}
