<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align Phase A JSON column types and privacy defaults with Doctrine mappings.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE membership_policy CHANGE dunning_intervals dunning_intervals JSON NOT NULL COMMENT '(DC2Type:json)', CHANGE dunning_templates dunning_templates JSON NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("ALTER TABLE finance_annual_close CHANGE balances balances JSON NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("ALTER TABLE audit_event CHANGE context context JSON NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql('ALTER TABLE privacy_policy CHANGE message_retention_days message_retention_days INT DEFAULT 365 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE membership_policy CHANGE dunning_intervals dunning_intervals LONGTEXT NOT NULL COMMENT '(DC2Type:json)', CHANGE dunning_templates dunning_templates LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("ALTER TABLE finance_annual_close CHANGE balances balances LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql("ALTER TABLE audit_event CHANGE context context LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");
        $this->addSql('ALTER TABLE privacy_policy CHANGE message_retention_days message_retention_days INT DEFAULT 3 NOT NULL');
    }
}
