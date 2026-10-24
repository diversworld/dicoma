<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Verify every extracted personal field before removing redundant member columns (approved normalization)';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->abortIf(!$schema->hasTable('person') || !$schema->getTable('member')->hasColumn('person_id'), 'Run the person extraction migration first.');

        $differences = ['p.id IS NULL'];
        foreach (['firstname', 'lastname', 'birthday', 'email', 'mobile', 'phone', 'street', 'postal_code', 'city'] as $column) {
            $differences[] = sprintf('NOT (BINARY m.%1$s <=> BINARY p.%1$s)', $column);
        }
        $mismatches = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM member m LEFT JOIN person p ON p.id = m.person_id WHERE ' . implode(' OR ', $differences)
        );
        $this->abortIf($mismatches !== 0, sprintf('%d member records differ from their extracted person. No legacy columns were removed; resolve the differences first.', $mismatches));
        $accountMismatches = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM member m INNER JOIN `user` u ON u.id = m.user_id WHERE NOT (u.person_id <=> m.person_id)'
        );
        $this->abortIf($accountMismatches !== 0, 'Account/person assignments differ. No legacy columns were removed.');

        $this->addSql('ALTER TABLE member DROP firstname, DROP lastname, DROP birthday, DROP email, DROP mobile, DROP phone, DROP street, DROP postal_code, DROP city');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Restoring legacy identity columns requires an explicit reverse data migration or a verified backup.');
    }
}
