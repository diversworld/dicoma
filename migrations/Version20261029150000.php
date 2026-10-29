<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add nested club media folders without moving stored files'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql("CREATE TABLE media_folder (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, INDEX idx_media_folder_club (club_id), INDEX IDX_50DB9313727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE media_folder ADD CONSTRAINT fk_media_folder_club FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE media_folder ADD CONSTRAINT fk_media_folder_parent FOREIGN KEY (parent_id) REFERENCES media_folder (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE media_file ADD folder_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE media_file ADD CONSTRAINT fk_media_file_folder FOREIGN KEY (folder_id) REFERENCES media_folder (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_4FD8E9C3162CB942 ON media_file (folder_id)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ordner und ihre Dateizuordnungen müssen erhalten bleiben.');
    }
}
