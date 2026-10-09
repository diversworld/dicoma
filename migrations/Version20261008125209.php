<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008125209 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE qualification_training_unit (id INT AUTO_INCREMENT NOT NULL, qualification_id INT NOT NULL, training_unit_type_id INT NOT NULL, required_sessions INT DEFAULT 1 NOT NULL, required TINYINT(1) DEFAULT 1 NOT NULL, notes VARCHAR(255) DEFAULT NULL, INDEX IDX_F92D075D1A75EE38 (qualification_id), INDEX IDX_F92D075D8916AE5C (training_unit_type_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE qualification_training_unit ADD CONSTRAINT FK_F92D075D1A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE qualification_training_unit ADD CONSTRAINT FK_F92D075D8916AE5C FOREIGN KEY (training_unit_type_id) REFERENCES training_unit_type (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE qualification_training_unit DROP FOREIGN KEY FK_F92D075D1A75EE38');
        $this->addSql('ALTER TABLE qualification_training_unit DROP FOREIGN KEY FK_F92D075D8916AE5C');
        $this->addSql('DROP TABLE qualification_training_unit');
    }
}
