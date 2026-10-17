<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017181500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create missing club tank inspection history before its index alignment; preserve existing results';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');

        if ($schema->hasTable('tank_inspection_result')) {
            $this->write('tank_inspection_result already exists; preserving existing inspection results.');
            return;
        }

        $this->addSql("CREATE TABLE tank_inspection_result (id INT AUTO_INCREMENT NOT NULL, tank_id INT NOT NULL, result VARCHAR(10) NOT NULL, inspected_at DATE NOT NULL COMMENT '(DC2Type:date_immutable)', notes LONGTEXT DEFAULT NULL, updated_by VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_9EC2776B15C652B5 (tank_id), PRIMARY KEY(id), CONSTRAINT FK_9EC2776B15C652B5 FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Existing tank inspection history must be preserved; restore a verified backup for a full rollback.');
    }
}
