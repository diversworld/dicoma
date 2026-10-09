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
        /*
         * Stammdatentabelle für Ausbildungseinheiten.
         */
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

        $this->addSql(
            'ALTER TABLE training_unit_type
             ADD CONSTRAINT FK_TRAINING_UNIT_TYPE_CLUB
             FOREIGN KEY (club_id)
             REFERENCES club (id)
             ON DELETE CASCADE'
        );

        /*
         * training_unit_type_id existiert in deiner Datenbank
         * bereits. Deshalb wird die Spalte hier NICHT erneut
         * angelegt.
         */
        $this->addSql(
            'CREATE INDEX IDX_5A3811FB8916AE5C
             ON schedule (training_unit_type_id)'
        );

        $this->addSql(
            'ALTER TABLE schedule
             ADD CONSTRAINT FK_5A3811FB8916AE5C
             FOREIGN KEY (training_unit_type_id)
             REFERENCES training_unit_type (id)
             ON DELETE SET NULL'
        );
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
}