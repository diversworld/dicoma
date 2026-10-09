<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008110126 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds course training units and training unit sequence to schedules';
    }

    public function up(Schema $schema): void
	{
		$this->addSql(
			'CREATE TABLE course_training_unit (
				id INT AUTO_INCREMENT NOT NULL,
				course_id INT NOT NULL,
				training_unit_type_id INT NOT NULL,
				required_sessions INT DEFAULT 1 NOT NULL,
				required TINYINT(1) DEFAULT 1 NOT NULL,
				notes VARCHAR(255) DEFAULT NULL,
				INDEX IDX_10AC0FC9591CC992 (course_id),
				INDEX IDX_10AC0FC98916AE5C (training_unit_type_id),
				UNIQUE INDEX uniq_course_training_unit (
					course_id,
					training_unit_type_id
				),
				PRIMARY KEY(id)
			)
			DEFAULT CHARACTER SET utf8mb4
			COLLATE `utf8mb4_unicode_ci`
			ENGINE = InnoDB'
		);

		$this->addSql(
			'ALTER TABLE course_training_unit
			 ADD CONSTRAINT FK_10AC0FC9591CC992
			 FOREIGN KEY (course_id)
			 REFERENCES courses (id)
			 ON DELETE CASCADE'
		);

		$this->addSql(
			'ALTER TABLE course_training_unit
			 ADD CONSTRAINT FK_10AC0FC98916AE5C
			 FOREIGN KEY (training_unit_type_id)
			 REFERENCES training_unit_type (id)
			 ON DELETE CASCADE'
		);

		$this->addSql(
			'ALTER TABLE schedule
			 ADD training_unit_sequence INT DEFAULT NULL'
		);
	}

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE course_training_unit
             DROP FOREIGN KEY FK_10AC0FC9591CC992'
        );

        $this->addSql(
            'ALTER TABLE course_training_unit
             DROP FOREIGN KEY FK_10AC0FC98916AE5C'
        );

        $this->addSql(
            'DROP TABLE course_training_unit'
        );

        $this->addSql(
            'ALTER TABLE schedule
             DROP training_unit_sequence'
        );
    }
}