<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add manufacturer-specific equipment sizes without altering existing size records';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_size ADD manufacturer_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment_size ADD CONSTRAINT FK_EQ_SIZE_MANUFACTURER FOREIGN KEY (manufacturer_id) REFERENCES equipment_manufacturer (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_EQ_SIZE_MANUFACTURER ON equipment_size (manufacturer_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_size DROP FOREIGN KEY FK_EQ_SIZE_MANUFACTURER');
        $this->addSql('DROP INDEX IDX_EQ_SIZE_MANUFACTURER ON equipment_size');
        $this->addSql('ALTER TABLE equipment_size DROP manufacturer_id');
    }
}
