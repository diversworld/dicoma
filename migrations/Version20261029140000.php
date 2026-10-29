<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261029140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add protected shared and individual event documents'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql("CREATE TABLE club_event_document (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, registration_id INT DEFAULT NULL, storage_name VARCHAR(80) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(120) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_5234D375570EB513 (storage_name), INDEX IDX_5234D37571F7E88B (event_id), INDEX IDX_5234D375833D8F43 (registration_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE club_event_document ADD CONSTRAINT fk_event_document_event FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_event_document ADD CONSTRAINT fk_event_document_registration FOREIGN KEY (registration_id) REFERENCES club_event_registration (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Veranstaltungsdokumente und ihre Zuordnungen müssen erhalten bleiben.');
    }
}
