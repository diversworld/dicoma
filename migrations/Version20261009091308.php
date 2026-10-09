<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009091308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE schedule_template (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, name VARCHAR(150) NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_2B8BF6B561190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE schedule_template_entry (id INT AUTO_INCREMENT NOT NULL, template_id INT NOT NULL, training_unit_type_id INT NOT NULL, sequence INT NOT NULL, day_offset INT NOT NULL, start_time TIME DEFAULT NULL, duration_minutes INT DEFAULT NULL, INDEX IDX_222ACEEC5DA0FB8 (template_id), INDEX IDX_222ACEEC8916AE5C (training_unit_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE schedule_template ADD CONSTRAINT FK_2B8BF6B561190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE schedule_template_entry ADD CONSTRAINT FK_222ACEEC5DA0FB8 FOREIGN KEY (template_id) REFERENCES schedule_template (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE schedule_template_entry ADD CONSTRAINT FK_222ACEEC8916AE5C FOREIGN KEY (training_unit_type_id) REFERENCES training_unit_type (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE schedule_template DROP FOREIGN KEY FK_2B8BF6B561190A32');
        $this->addSql('ALTER TABLE schedule_template_entry DROP FOREIGN KEY FK_222ACEEC5DA0FB8');
        $this->addSql('ALTER TABLE schedule_template_entry DROP FOREIGN KEY FK_222ACEEC8916AE5C');
        $this->addSql('DROP TABLE schedule_template');
        $this->addSql('DROP TABLE schedule_template_entry');
    }
}
