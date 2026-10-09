<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240830154716 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_article DROP FOREIGN KEY FK_EE5CF17034F27838');
        $this->addSql('DROP INDEX IDX_EE5CF17034F27838 ON tank_check_article');
        $this->addSql('ALTER TABLE tank_check_article ADD tank_check_id INT NOT NULL, ADD is_default TINYINT(1) NOT NULL, DROP tank_checks_id');
        $this->addSql('ALTER TABLE tank_check_article ADD CONSTRAINT FK_EE5CF1705531E667 FOREIGN KEY (tank_check_id) REFERENCES tank_check (id)');
        $this->addSql('CREATE INDEX IDX_EE5CF1705531E667 ON tank_check_article (tank_check_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_article DROP FOREIGN KEY FK_EE5CF1705531E667');
        $this->addSql('DROP INDEX IDX_EE5CF1705531E667 ON tank_check_article');
        $this->addSql('ALTER TABLE tank_check_article ADD tank_checks_id INT DEFAULT NULL, DROP tank_check_id, DROP is_default');
        $this->addSql('ALTER TABLE tank_check_article ADD CONSTRAINT FK_EE5CF17034F27838 FOREIGN KEY (tank_checks_id) REFERENCES tank_check (id)');
        $this->addSql('CREATE INDEX IDX_EE5CF17034F27838 ON tank_check_article (tank_checks_id)');
    }
}
