<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure person/club membership uniqueness on already normalized installations without changing member data';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->abortIf(!$schema->hasTable('member') || !$schema->getTable('member')->hasColumn('person_id'), 'Run the person normalization migrations first.');

        $table = $schema->getTable('member');
        if ($table->hasIndex('uniq_member_person_club')) {
            $index = $table->getIndex('uniq_member_person_club');
            $this->abortIf(!$index->isUnique() || $index->getColumns() !== ['person_id', 'club_id'], 'Existing uniq_member_person_club index has an unexpected definition; inspect it before continuing.');
            return;
        }

        $duplicates = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM (SELECT person_id, club_id FROM member WHERE person_id IS NOT NULL AND club_id IS NOT NULL GROUP BY person_id, club_id HAVING COUNT(*) > 1) duplicate_memberships'
        );
        $this->abortIf($duplicates !== 0, 'Duplicate person/club memberships exist. Resolve them explicitly; no membership data or indexes were changed.');
        $this->addSql('CREATE UNIQUE INDEX uniq_member_person_club ON member (person_id, club_id)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('The uniqueness constraint may predate this migration and must remain in place.');
    }
}
