<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add course attendance excuse requests and instructor decisions';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE booking ADD excuse_reason LONGTEXT DEFAULT NULL, ADD excuse_requested_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD excuse_decision VARCHAR(30) DEFAULT NULL, ADD excuse_reviewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD excuse_reviewed_by_id INT DEFAULT NULL, ADD INDEX IDX_E00CEDDEE3ACDAAA (excuse_reviewed_by_id)');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_booking_excuse_reviewer FOREIGN KEY (excuse_reviewed_by_id) REFERENCES member (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_booking_excuse_reviewer');
        $this->addSql('ALTER TABLE booking DROP INDEX IDX_E00CEDDEE3ACDAAA, DROP excuse_reason, DROP excuse_requested_at, DROP excuse_decision, DROP excuse_reviewed_at, DROP excuse_reviewed_by_id');
    }
}
