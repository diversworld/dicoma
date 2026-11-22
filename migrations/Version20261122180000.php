<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align membership history JSON mapping with Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE membership_history CHANGE changes changes JSON NOT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE membership_history CHANGE changes changes LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");
    }
}
