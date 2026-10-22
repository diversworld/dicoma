<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Aligns two known schema differences; intentionally leaves indexes untouched. */
final class Version20261022100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DICOMA: align TUV invoice JSON and immutable confirmation timestamp on MySQL';
    }

    public function isTransactional(): bool
    {
        // MySQL ALTER TABLE implicitly commits; do not imply atomic rollback.
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'This migration is intended only for MySQL.'
        );

        $invalid = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM tuv_invoice WHERE `lines` IS NULL OR JSON_VALID(`lines`) = 0'
        );
        $this->abortIf($invalid !== 0, 'Invoice lines contain NULL or invalid JSON; migration aborted.');

        $this->addSql('ALTER TABLE tuv_invoice MODIFY COLUMN `lines` JSON NOT NULL');
        $this->addSql(
            "ALTER TABLE tuv_booking_group MODIFY COLUMN agency_confirmed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'This migration is intended only for MySQL.'
        );

        $this->addSql('ALTER TABLE tuv_invoice MODIFY COLUMN `lines` LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE tuv_booking_group MODIFY COLUMN agency_confirmed_at DATETIME DEFAULT NULL');
    }
}
