<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019180000 extends AbstractMigration
{
    public function getDescription(): string { return 'TÜV-Vertretungsmodell je Verein, MySQL 5.7'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tuv_invoice_settings ADD member_agency_enabled TINYINT(1) NOT NULL DEFAULT 0');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tuv_invoice_settings DROP member_agency_enabled');
    }
}
