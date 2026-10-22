<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019195000 extends AbstractMigration
{
    public function getDescription(): string { return 'Nachvollziehbare Barauszahlungen der Gerätewart-Kasse (MySQL 5.7)'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE equipment_cash_payout (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, category VARCHAR(30) NOT NULL, recipient VARCHAR(180) NOT NULL, amount_cents INT NOT NULL, paid_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', paid_by VARCHAR(180) NOT NULL, purpose VARCHAR(500) NOT NULL, document_reference VARCHAR(120) DEFAULT NULL, INDEX idx_cash_payout_club_date (club_id, paid_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE equipment_cash_payout ADD CONSTRAINT FK_CASH_PAYOUT_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE equipment_cash_payout'); }
}
