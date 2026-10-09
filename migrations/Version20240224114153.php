<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240224114153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE vendor (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, street VARCHAR(150) DEFAULT NULL, postal INT DEFAULT NULL, city VARCHAR(255) DEFAULT NULL, phone VARCHAR(25) NOT NULL, email VARCHAR(60) DEFAULT NULL, website VARCHAR(250) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tank_check ADD vendor_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tank_check ADD CONSTRAINT FK_1C9493C9F603EE73 FOREIGN KEY (vendor_id) REFERENCES vendor (id)');
        $this->addSql('CREATE INDEX IDX_1C9493C9F603EE73 ON tank_check (vendor_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check DROP FOREIGN KEY FK_1C9493C9F603EE73');
        $this->addSql('DROP TABLE vendor');
        $this->addSql('DROP INDEX IDX_1C9493C9F603EE73 ON tank_check');
        $this->addSql('ALTER TABLE tank_check DROP vendor_id');
    }
}
