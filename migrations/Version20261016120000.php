<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create missing tank maintenance table safely; preserve existing history indexes';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if ($schemaManager->tablesExist(['tank_maintenance_order'])) {
            $this->write('tank_maintenance_order already exists; no changes applied.');
            return;
        }

        $this->addSql("CREATE TABLE tank_maintenance_order (id INT AUTO_INCREMENT NOT NULL, tank_id INT NOT NULL, status VARCHAR(20) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', closed_by VARCHAR(180) DEFAULT NULL, resolution LONGTEXT DEFAULT NULL, INDEX idx_tank_maintenance_open (tank_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE tank_maintenance_order ADD CONSTRAINT FK_D850FFB615C652B5 FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // Deliberately non-destructive: never drop maintenance records on rollback.
        $this->write('No automatic rollback: maintenance records must be preserved.');
    }
}
