<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016200000 extends AbstractMigration
{
    private const UNIQUE_INDEXES = [
        ['qualification_training_unit', 'uniq_qualification_training_unit', ['qualification_id', 'training_unit_type_id']],
        ['schedule_template_entry', 'uniq_schedule_template_unit', ['template_id', 'training_unit_type_id', 'sequence']],
        ['course_training_unit', 'uniq_course_training_unit', ['course_id', 'training_unit_type_id']],
        ['schedule', 'uniq_schedule_course_training_sequence', ['courses_id', 'training_unit_type_id', 'training_unit_sequence']],
        ['training_unit_type', 'uniq_training_unit_type_club_code', ['club_id', 'code']],
        ['training_unit_result', 'uniq_training_unit_result_participant_schedule', ['course_participant_id', 'schedule_id']],
    ];

    public function getDescription(): string
    {
        return 'Complete missing inventory fields, training constraints and Doctrine schema metadata without deleting data';
    }

    public function up(Schema $schema): void
    {
        foreach (self::UNIQUE_INDEXES as [$table, $index, $columns]) {
            $columnList = '`' . implode('`, `', $columns) . '`';
            $notNull = implode(' AND ', array_map(
                static fn (string $column): string => sprintf('`%s` IS NOT NULL', $column),
                $columns
            ));
            $duplicate = $this->connection->fetchAssociative(sprintf(
                'SELECT %s, COUNT(*) AS duplicate_count FROM `%s` WHERE %s GROUP BY %s HAVING COUNT(*) > 1 LIMIT 1',
                $columnList,
                $table,
                $notNull,
                $columnList
            ));

            $this->abortIf($duplicate !== false, sprintf(
                'Cannot create %s on %s: duplicate key %s. Resolve duplicates explicitly before retrying; no records have been deleted.',
                $index,
                $table,
                json_encode($duplicate, JSON_THROW_ON_ERROR)
            ));

            if (!$schema->getTable($table)->hasIndex($index)) {
                $this->addSql(sprintf('CREATE UNIQUE INDEX `%s` ON `%s` (%s)', $index, $table, $columnList));
            }
        }

        foreach ([
            'issued_at' => "DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
            'returned_at' => "DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'",
            'condition_at_issue' => 'VARCHAR(30) DEFAULT NULL',
            'condition_at_return' => 'VARCHAR(30) DEFAULT NULL',
            'returned_notes' => 'LONGTEXT DEFAULT NULL',
        ] as $column => $definition) {
            if (!$schema->getTable('equipment_reservation')->hasColumn($column)) {
                $this->addSql(sprintf('ALTER TABLE equipment_reservation ADD `%s` %s', $column, $definition));
            }
        }

        if (!$schema->getTable('tank')->hasColumn('condition_status')) {
            $this->addSql("ALTER TABLE tank ADD condition_status VARCHAR(20) DEFAULT 'ready' NOT NULL");
        }
        if (!$schema->getTable('tank')->hasColumn('lendable')) {
            $this->addSql('ALTER TABLE tank ADD lendable TINYINT(1) DEFAULT 1 NOT NULL');
        }

        $this->addSql("ALTER TABLE tank_check CHANGE registration_deadline registration_deadline DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
        $this->addSql("ALTER TABLE equipment_item CHANGE purchased_at purchased_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");
        $this->addSql("ALTER TABLE regulator CHANGE next_service_at next_service_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)'");

        if ($schema->getTable('equipment_item')->hasIndex('UNIQ_259FF418C6525B5')) {
            $this->addSql('ALTER TABLE equipment_item RENAME INDEX UNIQ_259FF418C6525B5 TO UNIQ_259FF41815C652B5');
        }
        if ($schema->getTable('training_unit_type')->hasForeignKey('FK_6E94D58761190A32')) {
            $schema->getTable('training_unit_type')->removeForeignKey('FK_6E94D58761190A32');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Inventory condition and return history must be preserved; restore a verified backup for a full rollback.');
    }
}
