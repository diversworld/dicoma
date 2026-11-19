<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make the membership application approval group configurable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE membership_policy ADD approval_role VARCHAR(40) DEFAULT 'ROLE_ADMIN' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE membership_policy DROP approval_role');
    }
}
