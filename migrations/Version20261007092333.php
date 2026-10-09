<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261007092333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses ADD club_id INT DEFAULT NULL, ADD qualification_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE courses ADD CONSTRAINT FK_A9A55A4C61190A32 FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE courses ADD CONSTRAINT FK_A9A55A4C1A75EE38 FOREIGN KEY (qualification_id) REFERENCES qualification (id)');
        $this->addSql('CREATE INDEX IDX_A9A55A4C61190A32 ON courses (club_id)');
        $this->addSql('CREATE INDEX IDX_A9A55A4C1A75EE38 ON courses (qualification_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE courses DROP FOREIGN KEY FK_A9A55A4C61190A32');
        $this->addSql('ALTER TABLE courses DROP FOREIGN KEY FK_A9A55A4C1A75EE38');
        $this->addSql('DROP INDEX IDX_A9A55A4C61190A32 ON courses');
        $this->addSql('DROP INDEX IDX_A9A55A4C1A75EE38 ON courses');
        $this->addSql('ALTER TABLE courses DROP club_id, DROP qualification_id');
    }
}
