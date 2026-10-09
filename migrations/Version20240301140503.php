<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240301140503 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_article ADD tank_checks_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tank_check_article ADD CONSTRAINT FK_EE5CF17034F27838 FOREIGN KEY (tank_checks_id) REFERENCES tank_check (id)');
        $this->addSql('CREATE INDEX IDX_EE5CF17034F27838 ON tank_check_article (tank_checks_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_article DROP FOREIGN KEY FK_EE5CF17034F27838');
        $this->addSql('DROP INDEX IDX_EE5CF17034F27838 ON tank_check_article');
        $this->addSql('ALTER TABLE tank_check_article DROP tank_checks_id');
    }
}
