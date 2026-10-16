<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Append-only history of tank returns and condition reports'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tank_condition_history (id INT AUTO_INCREMENT NOT NULL, tank_id INT NOT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', event_type VARCHAR(32) NOT NULL, condition_status VARCHAR(20) NOT NULL, lendable TINYINT(1) NOT NULL, notes LONGTEXT DEFAULT NULL, booking_id INT DEFAULT NULL, booking_item_id INT DEFAULT NULL, INDEX IDX_TANK_CONDITION_TANK_TIME (tank_id, occurred_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tank_condition_history ADD CONSTRAINT FK_TANK_CONDITION_TANK FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tank_condition_history');
    }
}
