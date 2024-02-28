<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240226125139 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tank_check_article (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, price_netto NUMERIC(10, 2) NOT NULL, price_brutto NUMERIC(10, 2) NOT NULL, notes LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tank_check_detail (id INT AUTO_INCREMENT NOT NULL, tank_check_id INT DEFAULT NULL, tank_id INT DEFAULT NULL, INDEX IDX_2B0B4F525531E667 (tank_check_id), INDEX IDX_2B0B4F5215C652B5 (tank_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tank_check_detail ADD CONSTRAINT FK_2B0B4F525531E667 FOREIGN KEY (tank_check_id) REFERENCES tank_check (id)');
        $this->addSql('ALTER TABLE tank_check_detail ADD CONSTRAINT FK_2B0B4F5215C652B5 FOREIGN KEY (tank_id) REFERENCES tank (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_detail DROP FOREIGN KEY FK_2B0B4F525531E667');
        $this->addSql('ALTER TABLE tank_check_detail DROP FOREIGN KEY FK_2B0B4F5215C652B5');
        $this->addSql('DROP TABLE tank_check_article');
        $this->addSql('DROP TABLE tank_check_detail');
    }
}
