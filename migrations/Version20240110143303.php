<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240110143303 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE instructor ADD username VARCHAR(180) NOT NULL, ADD roles JSON NOT NULL COMMENT \'(DC2Type:json)\', ADD password VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_31FC43DDF85E0677 ON instructor (username)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_31FC43DDF85E0677 ON instructor');
        $this->addSql('ALTER TABLE instructor DROP username, DROP roles, DROP password');
    }
}
