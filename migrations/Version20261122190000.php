<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add SKR42 chart metadata to finance accounts.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE finance_account ADD chart VARCHAR(40) DEFAULT 'custom' NOT NULL, ADD sphere VARCHAR(80) DEFAULT NULL, ADD usage_label VARCHAR(120) DEFAULT NULL, ADD source_reference VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE finance_account DROP source_reference, DROP usage_label, DROP sphere, DROP chart');
    }
}
