<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028181000 extends AbstractMigration
{
    public function getDescription(): string { return 'Reconcile event foreign-key index names from the initial local migration'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        foreach ([
            ['club_event', 'idx_event_club', 'idx_9bc0201561190a32'],
            ['club_event_registration', 'idx_event_reg_person', 'idx_11085451217bbb47'],
        ] as [$table, $old, $expected]) {
            $indexes = array_change_key_case($this->connection->createSchemaManager()->listTableIndexes($table), CASE_LOWER);
            $this->abortIf(isset($indexes[$old]) === isset($indexes[$expected]), 'Unerwartete Veranstaltungsindizes in '.$table.'; manuelle Prüfung erforderlich.');
            if (isset($indexes[$old])) {
                $this->addSql(sprintf('ALTER TABLE %s RENAME INDEX %s TO %s', $table, $old, $expected));
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Veranstaltungsindizes müssen erhalten bleiben.');
    }
}
