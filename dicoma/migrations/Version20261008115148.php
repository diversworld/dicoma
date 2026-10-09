<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008115148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_course_training_unit ON course_training_unit');
        $this->addSql('ALTER TABLE schedule CHANGE start_date start_date DATE DEFAULT NULL, CHANGE duration duration INT DEFAULT NULL, CHANGE start_time start_time TIME DEFAULT NULL, CHANGE location_postal location_postal VARCHAR(10) DEFAULT NULL');
        $this->addSql('DROP INDEX uniq_training_unit_type_club_code ON training_unit_type');
        $this->addSql('ALTER TABLE training_unit_type ADD CONSTRAINT FK_6E94D58761190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_unit_type RENAME INDEX idx_training_unit_type_club TO IDX_6E94D58761190A32');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE UNIQUE INDEX uniq_course_training_unit ON course_training_unit (course_id, training_unit_type_id)');
        $this->addSql('ALTER TABLE schedule CHANGE start_date start_date DATE NOT NULL, CHANGE duration duration INT NOT NULL, CHANGE start_time start_time TIME NOT NULL, CHANGE location_postal location_postal INT DEFAULT NULL');
        $this->addSql('ALTER TABLE training_unit_type DROP FOREIGN KEY FK_6E94D58761190A32');
        $this->addSql('CREATE UNIQUE INDEX uniq_training_unit_type_club_code ON training_unit_type (club_id, code)');
        $this->addSql('ALTER TABLE training_unit_type RENAME INDEX idx_6e94d58761190a32 TO IDX_TRAINING_UNIT_TYPE_CLUB');
    }
}
