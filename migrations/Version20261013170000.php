<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Position-level issue and return data for equipment bookings'; }
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE equipment_booking_item ADD loan_status VARCHAR(20) NOT NULL DEFAULT 'reserved', ADD issued_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD returned_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD return_condition VARCHAR(30) DEFAULT NULL, ADD return_notes LONGTEXT DEFAULT NULL");
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_booking_item DROP loan_status, DROP issued_at, DROP returned_at, DROP return_condition, DROP return_notes');
    }
}
