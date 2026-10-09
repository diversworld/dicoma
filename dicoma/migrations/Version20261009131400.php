<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009131400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE training_unit_result (id INT AUTO_INCREMENT NOT NULL, course_participant_id INT NOT NULL, schedule_id INT NOT NULL, assessor_id INT DEFAULT NULL, status VARCHAR(30) DEFAULT \'open\' NOT NULL, assessed_at DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_2058503262D0D72D (course_participant_id), INDEX IDX_20585032A40BC2D5 (schedule_id), INDEX IDX_20585032A5E4B630 (assessor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE training_unit_result ADD CONSTRAINT FK_2058503262D0D72D FOREIGN KEY (course_participant_id) REFERENCES course_participant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_unit_result ADD CONSTRAINT FK_20585032A40BC2D5 FOREIGN KEY (schedule_id) REFERENCES schedule (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE training_unit_result ADD CONSTRAINT FK_20585032A5E4B630 FOREIGN KEY (assessor_id) REFERENCES member (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE training_unit_result DROP FOREIGN KEY FK_2058503262D0D72D');
        $this->addSql('ALTER TABLE training_unit_result DROP FOREIGN KEY FK_20585032A40BC2D5');
        $this->addSql('ALTER TABLE training_unit_result DROP FOREIGN KEY FK_20585032A5E4B630');
        $this->addSql('DROP TABLE training_unit_result');
    }
}
