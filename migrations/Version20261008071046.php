<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008071046 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE1AD8D010');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE1AD8D010 FOREIGN KEY (students_id) REFERENCES member (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDE1AD8D010');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDE1AD8D010 FOREIGN KEY (students_id) REFERENCES member (id)');
    }
}
