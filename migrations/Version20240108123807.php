<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240108123807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE booking (id INT AUTO_INCREMENT NOT NULL, schedule_id INT DEFAULT NULL, booking_date DATETIME NOT NULL, firstname VARCHAR(60) NOT NULL, lastname VARCHAR(60) NOT NULL, email VARCHAR(150) NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_E00CEDDEA40BC2D5 (schedule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA40BC2D5 FOREIGN KEY (schedule_id) REFERENCES schedule (id)');
        $this->addSql('ALTER TABLE schedule ADD price NUMERIC(6, 2) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA40BC2D5');
        $this->addSql('DROP TABLE booking');
        $this->addSql('ALTER TABLE schedule DROP price');
    }
}
