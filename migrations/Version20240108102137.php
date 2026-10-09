<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240108102137 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE instructor (id INT AUTO_INCREMENT NOT NULL, firstname VARCHAR(50) NOT NULL, lastname VARCHAR(50) NOT NULL, street VARCHAR(150) NOT NULL, postal INT DEFAULT NULL, city VARCHAR(150) DEFAULT NULL, birthdate DATE NOT NULL, mobile VARCHAR(20) DEFAULT NULL, phone VARCHAR(20) DEFAULT NULL, email VARCHAR(50) DEFAULT NULL, status INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE schedule ADD instructor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE schedule ADD CONSTRAINT FK_5A3811FB8C4FC193 FOREIGN KEY (instructor_id) REFERENCES instructor (id)');
        $this->addSql('CREATE INDEX IDX_5A3811FB8C4FC193 ON schedule (instructor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE schedule DROP FOREIGN KEY FK_5A3811FB8C4FC193');
        $this->addSql('DROP TABLE instructor');
        $this->addSql('DROP INDEX IDX_5A3811FB8C4FC193 ON schedule');
        $this->addSql('ALTER TABLE schedule DROP instructor_id');
    }
}
