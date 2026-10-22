<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Rechnungs-/Steuerkonfiguration je Verein (MySQL 5.7)'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE tuv_invoice_settings (club_id INT NOT NULL, tax_mode VARCHAR(20) NOT NULL, tax_number VARCHAR(60) DEFAULT NULL, vat_id VARCHAR(40) DEFAULT NULL, invoice_note LONGTEXT DEFAULT NULL, PRIMARY KEY(club_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE tuv_invoice_settings ADD CONSTRAINT FK_TUV_INVOICE_SETTINGS_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tuv_invoice_settings');
    }
}
