<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127102000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair missing equipment cash correction journal table from legacy deployments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS equipment_cash_correction (
    id INT AUTO_INCREMENT NOT NULL,
    club_id INT NOT NULL,
    source_type VARCHAR(12) NOT NULL,
    source_id INT NOT NULL,
    direction VARCHAR(3) NOT NULL,
    amount_cents INT NOT NULL,
    reason VARCHAR(500) NOT NULL,
    created_by VARCHAR(180) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_CASH_CORRECTION_CLUB (club_id),
    UNIQUE INDEX UNIQ_CASH_CORRECTION_SOURCE (club_id, source_type, source_id),
    PRIMARY KEY(id),
    CONSTRAINT FK_CASH_CORRECTION_CLUB_REPAIR FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Repair migration is intentionally not rolled back because existing correction data may predate it.');
    }
}
