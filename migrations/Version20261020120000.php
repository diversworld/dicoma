<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store agreed equipment rental fee at booking creation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_booking ADD agreed_rental_fee_cents INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_booking DROP agreed_rental_fee_cents');
    }
}
