<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240223122358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE tank (id INT AUTO_INCREMENT NOT NULL, inventory VARCHAR(20) NOT NULL, size INT NOT NULL, buy_date DATE DEFAULT NULL, last_check_date DATE NOT NULL, next_check_date DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, serielnumber VARCHAR(20) NOT NULL, oxigen_clean TINYINT(1) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tank_check (id INT AUTO_INCREMENT NOT NULL, check_date DATE NOT NULL, vendor_name VARCHAR(150) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, cost_information LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tank_check_tank (tank_check_id INT NOT NULL, tank_id INT NOT NULL, INDEX IDX_A7AA4EB35531E667 (tank_check_id), INDEX IDX_A7AA4EB315C652B5 (tank_id), PRIMARY KEY(tank_check_id, tank_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tank_check_tank ADD CONSTRAINT FK_A7AA4EB35531E667 FOREIGN KEY (tank_check_id) REFERENCES tank_check (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE tank_check_tank ADD CONSTRAINT FK_A7AA4EB315C652B5 FOREIGN KEY (tank_id) REFERENCES tank (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_tank DROP FOREIGN KEY FK_A7AA4EB35531E667');
        $this->addSql('ALTER TABLE tank_check_tank DROP FOREIGN KEY FK_A7AA4EB315C652B5');
        $this->addSql('DROP TABLE tank');
        $this->addSql('DROP TABLE tank_check');
        $this->addSql('DROP TABLE tank_check_tank');
    }
}
