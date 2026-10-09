<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240115161153 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD students_id INT DEFAULT NULL, ADD bookingdate DATE NOT NULL, ADD bookingnumber VARCHAR(255) DEFAULT NULL, ADD status VARCHAR(50) DEFAULT NULL, DROP booking_date, DROP firstname, DROP lastname, DROP email');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE1AD8D010 FOREIGN KEY (students_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDE1AD8D010 ON booking (students_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE1AD8D010');
        $this->addSql('DROP INDEX IDX_E00CEDDE1AD8D010 ON booking');
        $this->addSql('ALTER TABLE booking ADD booking_date DATETIME NOT NULL, ADD firstname VARCHAR(60) NOT NULL, ADD lastname VARCHAR(60) NOT NULL, ADD email VARCHAR(150) NOT NULL, DROP students_id, DROP bookingdate, DROP bookingnumber, DROP status');
    }
}
