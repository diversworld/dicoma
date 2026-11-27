<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127093000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add configurable image size and detail position for articles and events.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE website_publication ADD image_width VARCHAR(10) DEFAULT 'large' NOT NULL, ADD image_position VARCHAR(10) DEFAULT 'top' NOT NULL");
        $this->addSql("ALTER TABLE club_event ADD image_width VARCHAR(10) DEFAULT 'large' NOT NULL, ADD image_position VARCHAR(10) DEFAULT 'top' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE club_event DROP image_position, DROP image_width');
        $this->addSql('ALTER TABLE website_publication DROP image_position, DROP image_width');
    }
}
