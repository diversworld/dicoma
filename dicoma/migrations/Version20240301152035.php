<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240301152035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_detail ADD article_id INT DEFAULT NULL, ADD amount NUMERIC(10, 2) NOT NULL');
        $this->addSql('ALTER TABLE tank_check_detail ADD CONSTRAINT FK_2B0B4F527294869C FOREIGN KEY (article_id) REFERENCES tank_check_article (id)');
        $this->addSql('CREATE INDEX IDX_2B0B4F527294869C ON tank_check_detail (article_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tank_check_detail DROP FOREIGN KEY FK_2B0B4F527294869C');
        $this->addSql('DROP INDEX IDX_2B0B4F527294869C ON tank_check_detail');
        $this->addSql('ALTER TABLE tank_check_detail DROP article_id, DROP amount');
    }
}
