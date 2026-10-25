<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add club-scoped editorial articles and curated public event announcements without publishing existing private data'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql("CREATE TABLE website_publication (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, course_id INT DEFAULT NULL, tournament_id INT DEFAULT NULL, version INT DEFAULT 1 NOT NULL, kind VARCHAR(20) NOT NULL, title VARCHAR(180) NOT NULL, summary VARCHAR(500) NOT NULL, body LONGTEXT NOT NULL, published TINYINT(1) NOT NULL, publish_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', starts_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ends_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', location VARCHAR(255) NOT NULL, history JSON NOT NULL, INDEX idx_website_publication_visibility (club_id, published, publish_at), INDEX idx_website_publication_course (course_id), INDEX idx_website_publication_tournament (tournament_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE website_publication ADD CONSTRAINT fk_website_publication_club FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT, ADD CONSTRAINT fk_website_publication_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE RESTRICT, ADD CONSTRAINT fk_website_publication_tournament FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Published articles and editorial evidence must be preserved.'); }
}
