<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006105355 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE member_sport (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, sport_id INT NOT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, joined_at DATE DEFAULT NULL, left_at DATE DEFAULT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_78A47A217597D3FE (member_id), INDEX IDX_78A47A21AC78BCF8 (sport_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sport (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, name VARCHAR(120) NOT NULL, short_name VARCHAR(50) DEFAULT NULL, description LONGTEXT DEFAULT NULL, active TINYINT(1) NOT NULL, sort_order INT NOT NULL, INDEX IDX_1A85EFD261190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member_sport ADD CONSTRAINT FK_78A47A217597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_sport ADD CONSTRAINT FK_78A47A21AC78BCF8 FOREIGN KEY (sport_id) REFERENCES sport (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sport ADD CONSTRAINT FK_1A85EFD261190A32 FOREIGN KEY (club_id) REFERENCES club (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member_sport DROP FOREIGN KEY FK_78A47A217597D3FE');
        $this->addSql('ALTER TABLE member_sport DROP FOREIGN KEY FK_78A47A21AC78BCF8');
        $this->addSql('ALTER TABLE sport DROP FOREIGN KEY FK_1A85EFD261190A32');
        $this->addSql('DROP TABLE member_sport');
        $this->addSql('DROP TABLE sport');
    }
}
