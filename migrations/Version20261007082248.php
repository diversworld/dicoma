<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007082248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE member_qualification (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, qualification_id INT NOT NULL, certificate_number VARCHAR(120) DEFAULT NULL, issued_at DATE DEFAULT NULL, valid_until DATE DEFAULT NULL, issuer VARCHAR(120) DEFAULT NULL, verified TINYINT(1) NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_540448427597D3FE (member_id), INDEX IDX_540448421A75EE38 (qualification_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE qualification (id INT AUTO_INCREMENT NOT NULL, club_id INT DEFAULT NULL, name VARCHAR(160) NOT NULL, short_name VARCHAR(60) DEFAULT NULL, type VARCHAR(40) NOT NULL, issuer VARCHAR(120) DEFAULT NULL, validity_months INT DEFAULT NULL, description LONGTEXT DEFAULT NULL, active TINYINT(1) NOT NULL, sort_order INT NOT NULL, INDEX IDX_B712F0CE61190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member_qualification ADD CONSTRAINT FK_540448427597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE member_qualification ADD CONSTRAINT FK_540448421A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id)');
        $this->addSql('ALTER TABLE qualification ADD CONSTRAINT FK_B712F0CE61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member_qualification DROP FOREIGN KEY FK_540448427597D3FE');
        $this->addSql('ALTER TABLE member_qualification DROP FOREIGN KEY FK_540448421A75EE38');
        $this->addSql('ALTER TABLE qualification DROP FOREIGN KEY FK_B712F0CE61190A32');
        $this->addSql('DROP TABLE member_qualification');
        $this->addSql('DROP TABLE qualification');
    }
}
