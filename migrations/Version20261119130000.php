<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add CMS page hierarchy and unify event registration/module links';
    }

    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE website_publication ADD parent_id INT DEFAULT NULL, ADD sort_order INT DEFAULT 0 NOT NULL, ADD menu_visible TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('CREATE INDEX idx_website_publication_parent ON website_publication (parent_id)');
        $this->addSql('ALTER TABLE website_publication ADD CONSTRAINT FK_website_publication_parent FOREIGN KEY (parent_id) REFERENCES website_publication (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_event ADD registration_enabled TINYINT(1) DEFAULT 1 NOT NULL, ADD course_id INT DEFAULT NULL, ADD tournament_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_club_event_course ON club_event (course_id)');
        $this->addSql('CREATE INDEX idx_club_event_tournament ON club_event (tournament_id)');
        $this->addSql('ALTER TABLE club_event ADD CONSTRAINT FK_club_event_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_event ADD CONSTRAINT FK_club_event_tournament FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE club_event DROP FOREIGN KEY FK_club_event_course, DROP FOREIGN KEY FK_club_event_tournament');
        $this->addSql('ALTER TABLE club_event DROP INDEX idx_club_event_course, DROP INDEX idx_club_event_tournament, DROP registration_enabled, DROP course_id, DROP tournament_id');
        $this->addSql('ALTER TABLE website_publication DROP FOREIGN KEY FK_website_publication_parent');
        $this->addSql('ALTER TABLE website_publication DROP INDEX idx_website_publication_parent, DROP parent_id, DROP sort_order, DROP menu_visible');
    }
}
