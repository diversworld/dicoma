<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008095454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds training unit types and links schedules to them';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        /*
         * Stammdatentabelle für Ausbildungseinheiten.
         */
        $trainingUnitTypeExists = $schemaManager->tablesExist(['training_unit_type']);
        if (!$trainingUnitTypeExists) {
            $this->addSql(
                'CREATE TABLE training_unit_type (
                    id INT AUTO_INCREMENT NOT NULL,
                    club_id INT DEFAULT NULL,
                    code VARCHAR(50) NOT NULL,
                    name VARCHAR(150) NOT NULL,
                    description VARCHAR(255) DEFAULT NULL,
                    active TINYINT(1) DEFAULT 1 NOT NULL,
                    examination TINYINT(1) DEFAULT 0 NOT NULL,
                    INDEX IDX_TRAINING_UNIT_TYPE_CLUB (club_id),
                    UNIQUE INDEX uniq_training_unit_type_club_code (club_id, code),
                    PRIMARY KEY(id)
                ) DEFAULT CHARACTER SET utf8mb4
                  COLLATE `utf8mb4_unicode_ci`
                  ENGINE = InnoDB'
            );
        }

        if (
            !$trainingUnitTypeExists
            || !$this->hasForeignKey('training_unit_type', 'FK_TRAINING_UNIT_TYPE_CLUB')
        ) {
            $this->addSql(
                'ALTER TABLE training_unit_type
                 ADD CONSTRAINT FK_TRAINING_UNIT_TYPE_CLUB
                 FOREIGN KEY (club_id)
                 REFERENCES club (id)
                 ON DELETE CASCADE'
            );
        }

        /*
         * Recover the schedule column if an earlier attempt stopped
         * after creating the training unit type table.
         */
        $scheduleColumns = $schemaManager->listTableColumns('schedule');
        $hasTrainingUnitTypeColumn = false;
        foreach ($scheduleColumns as $column) {
            if (strcasecmp($column->getName(), 'training_unit_type_id') === 0) {
                $hasTrainingUnitTypeColumn = true;
                break;
            }
        }

        if (!$hasTrainingUnitTypeColumn) {
            $this->addSql(
                'ALTER TABLE schedule
                 ADD training_unit_type_id INT DEFAULT NULL'
            );
        }

        $scheduleIndexes = $schemaManager->listTableIndexes('schedule');
        $hasScheduleIndex = false;
        foreach ($scheduleIndexes as $index) {
            if (strcasecmp($index->getName(), 'IDX_5A3811FB8916AE5C') === 0) {
                $hasScheduleIndex = true;
                break;
            }
        }

        if (!$hasScheduleIndex) {
            $this->addSql(
                'CREATE INDEX IDX_5A3811FB8916AE5C
                 ON schedule (training_unit_type_id)'
            );
        }

        if (!$this->hasForeignKey('schedule', 'FK_5A3811FB8916AE5C')) {
            $this->addSql(
                'ALTER TABLE schedule
                 ADD CONSTRAINT FK_5A3811FB8916AE5C
                 FOREIGN KEY (training_unit_type_id)
                 REFERENCES training_unit_type (id)
                 ON DELETE SET NULL'
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE schedule
             DROP FOREIGN KEY FK_5A3811FB8916AE5C'
        );

        $this->addSql(
            'DROP INDEX IDX_5A3811FB8916AE5C
             ON schedule'
        );

        /*
         * Die Spalte gehörte ursprünglich nicht zu dieser
         * Migration und wird deshalb hier bewusst nicht gelöscht.
         */

        $this->addSql(
            'ALTER TABLE training_unit_type
             DROP FOREIGN KEY FK_TRAINING_UNIT_TYPE_CLUB'
        );

        $this->addSql(
            'DROP TABLE training_unit_type'
        );
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist([$table])) {
            return false;
        }

        foreach ($schemaManager->listTableForeignKeys($table) as $foreignKey) {
            if (strcasecmp($foreignKey->getName(), $name) === 0) {
                return true;
            }
        }

        return false;
    }
}