<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008094721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking ADD makeup_for_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEEF67F437 FOREIGN KEY (makeup_for_id) REFERENCES booking (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_E00CEDDEEF67F437 ON booking (makeup_for_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEEF67F437');
        $this->addSql('DROP INDEX IDX_E00CEDDEEF67F437 ON booking');
        $this->addSql('ALTER TABLE booking DROP makeup_for_id');
    }
}
