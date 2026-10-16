<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016090000 extends AbstractMigration
{
    public function getDescription(): string { return 'Tank maintenance orders with MySQL 5.7 compatible schema'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tank_maintenance_order (id INT AUTO_INCREMENT NOT NULL, tank_id INT NOT NULL, status VARCHAR(20) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', closed_by VARCHAR(180) DEFAULT NULL, resolution LONGTEXT DEFAULT NULL, INDEX idx_tank_maintenance_open (tank_id, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE tank_maintenance_order ADD CONSTRAINT FK_TANK_MAINTENANCE_TANK FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tank_maintenance_order');
    }
}
