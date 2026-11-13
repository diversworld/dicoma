<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add division module settings and an explicit tournament division';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE sport ADD training_enabled TINYINT(1) DEFAULT 1 NOT NULL, ADD competition_enabled TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE tournament ADD division_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_BD5FB8D941859289 ON tournament (division_id)');
        $this->addSql('ALTER TABLE tournament ADD CONSTRAINT fk_tournament_division FOREIGN KEY (division_id) REFERENCES sport (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Spartenmodule und Turnierzuordnungen müssen erhalten bleiben.');
    }
}
