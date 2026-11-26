<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add per-user dashboard KPI order preferences for phase C.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD dashboard_preferences JSON DEFAULT NULL COMMENT '(DC2Type:json)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP dashboard_preferences');
    }
}
