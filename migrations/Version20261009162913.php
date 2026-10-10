<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009162913 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Restores the brevets table required by the Brevets entity.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->createSchemaManager()->tablesExist(['brevets'])) {
            $this->addSql(
                'CREATE TABLE brevets (
                    id INT AUTO_INCREMENT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description LONGTEXT DEFAULT NULL,
                    vendor VARCHAR(100) DEFAULT NULL,
                    published TINYINT(1) DEFAULT NULL,
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
                  COLLATE `utf8mb4_unicode_ci`
                  ENGINE = InnoDB'
            );
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->connection->createSchemaManager()->tablesExist(['brevets'])) {
            $this->addSql('DROP TABLE brevets');
        }
    }
}
