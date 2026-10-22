<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Optional agency confirmation snapshot on TUV booking group (MySQL 5.7)'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tuv_booking_group ADD agency_confirmed_at DATETIME DEFAULT NULL, ADD agency_text_version VARCHAR(40) DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tuv_booking_group DROP agency_confirmed_at, DROP agency_text_version');
    }
}
