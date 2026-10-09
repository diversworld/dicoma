<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261006104253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member ADD member_number VARCHAR(50) DEFAULT NULL, ADD joined_at DATE DEFAULT NULL, ADD left_at DATE DEFAULT NULL, ADD membership_status VARCHAR(255) NOT NULL, ADD postal_code VARCHAR(20) DEFAULT NULL, DROP postal');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE member ADD postal INT DEFAULT NULL, DROP member_number, DROP joined_at, DROP left_at, DROP membership_status, DROP postal_code');
    }
}
