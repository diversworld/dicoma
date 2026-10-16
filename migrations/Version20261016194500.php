<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add stamped water capacity in liters for private cylinders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_tank ADD water_capacity_liters NUMERIC(5, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_tank DROP water_capacity_liters');
    }
}
