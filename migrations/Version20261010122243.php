<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010122243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_course_training_unit ON course_training_unit');
        $this->addSql('DROP INDEX uniq_qualification_training_unit ON qualification_training_unit');
        $this->addSql('DROP INDEX uniq_schedule_course_training_sequence ON schedule');
        $this->addSql('DROP INDEX uniq_schedule_template_unit ON schedule_template_entry');
        $this->addSql('DROP INDEX uniq_training_unit_result_participant_schedule ON training_unit_result');
        $this->addSql('DROP INDEX uniq_training_unit_type_club_code ON training_unit_type');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_schedule_template_unit ON schedule_template_entry (template_id, training_unit_type_id, sequence)');
        $this->addSql('CREATE UNIQUE INDEX uniq_training_unit_type_club_code ON training_unit_type (club_id, code)');
        $this->addSql('CREATE UNIQUE INDEX uniq_training_unit_result_participant_schedule ON training_unit_result (course_participant_id, schedule_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_course_training_unit ON course_training_unit (course_id, training_unit_type_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_schedule_course_training_sequence ON schedule (courses_id, training_unit_type_id, training_unit_sequence)');
        $this->addSql('CREATE UNIQUE INDEX uniq_qualification_training_unit ON qualification_training_unit (qualification_id, training_unit_type_id)');
    }
}
