<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006103259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE club (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(180) NOT NULL, short_name VARCHAR(100) DEFAULT NULL, association VARCHAR(100) DEFAULT NULL, club_number VARCHAR(100) DEFAULT NULL, email VARCHAR(180) DEFAULT NULL, phone VARCHAR(30) DEFAULT NULL, website VARCHAR(180) DEFAULT NULL, street VARCHAR(180) DEFAULT NULL, postal_code VARCHAR(20) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, country VARCHAR(2) NOT NULL, description LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member ADD club_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA7861190A32 FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('CREATE INDEX IDX_70E4FA7861190A32 ON member (club_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_70E4FA7861190A32');
        $this->addSql('DROP TABLE club');
        $this->addSql('DROP INDEX IDX_70E4FA7861190A32 ON member');
        $this->addSql('ALTER TABLE member DROP club_id');
    }
}
