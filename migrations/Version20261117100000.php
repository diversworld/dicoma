<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow explicitly published website content to be assigned to a division';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE website_publication ADD division_id INT DEFAULT NULL, ADD INDEX idx_website_publication_division (division_id), ADD CONSTRAINT fk_website_publication_division FOREIGN KEY (division_id) REFERENCES sport (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM website_publication WHERE division_id IS NOT NULL') > 0, 'Spartenzuordnungen dürfen nicht verloren gehen.');
        $this->addSql('ALTER TABLE website_publication DROP FOREIGN KEY fk_website_publication_division, DROP INDEX idx_website_publication_division, DROP division_id');
    }
}
