<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronisiert den Herstellerindex der Ausruestungsgroessen.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE equipment_size RENAME INDEX idx_eq_size_manufacturer TO IDX_CD44F56CA23B42D'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE equipment_size RENAME INDEX IDX_CD44F56CA23B42D TO idx_eq_size_manufacturer'
        );
    }
}
