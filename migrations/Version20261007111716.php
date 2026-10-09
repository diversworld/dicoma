<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007111716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE course_participant (id INT AUTO_INCREMENT NOT NULL, course_id INT NOT NULL, member_id INT NOT NULL, status VARCHAR(20) DEFAULT \'registered\' NOT NULL, registered_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', started_at DATE DEFAULT NULL, completed_at DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_A3F15BC5591CC992 (course_id), INDEX IDX_A3F15BC57597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE course_participant ADD CONSTRAINT FK_A3F15BC5591CC992 FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_participant ADD CONSTRAINT FK_A3F15BC57597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE course_participant DROP FOREIGN KEY FK_A3F15BC5591CC992');
        $this->addSql('ALTER TABLE course_participant DROP FOREIGN KEY FK_A3F15BC57597D3FE');
        $this->addSql('DROP TABLE course_participant');
    }
}
