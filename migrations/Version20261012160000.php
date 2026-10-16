<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Private Mitglieder-Dokumentenmetadaten'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE member_document (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, storage_name VARCHAR(64) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, file_size INT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_MEMBER_DOCUMENT_STORAGE (storage_name), INDEX IDX_MEMBER_DOCUMENT_MEMBER (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE member_document ADD CONSTRAINT FK_MEMBER_DOCUMENT_MEMBER FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member_document');
    }
}
