<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add protected media files and optional publication and event images'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql("CREATE TABLE media_file (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, storage_name VARCHAR(80) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, alt_text VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_4FD8E9C3570EB513 (storage_name), INDEX idx_media_file_club (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE media_file ADD CONSTRAINT fk_media_file_club FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE website_publication ADD image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE website_publication ADD CONSTRAINT fk_website_publication_image FOREIGN KEY (image_id) REFERENCES media_file (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_E4B99EF03DA5256D ON website_publication (image_id)');
        $this->addSql('ALTER TABLE club_event ADD image_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE club_event ADD CONSTRAINT fk_club_event_image FOREIGN KEY (image_id) REFERENCES media_file (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_9BC020153DA5256D ON club_event (image_id)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Dateien und ihre Zuordnungen müssen erhalten bleiben.');
    }
}
