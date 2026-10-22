<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019210000 extends AbstractMigration
{
    public function getDescription(): string {return 'Unveraenderliche Tagesabschluesse der Geraetewart-Barkasse, MySQL 5.7';}
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE equipment_cash_closing (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, closing_date DATE NOT NULL COMMENT '(DC2Type:date_immutable)', opening_cents INT NOT NULL, cash_in_cents INT NOT NULL, cash_out_cents INT NOT NULL, expected_cents INT NOT NULL, counted_cents INT NOT NULL, difference_cents INT NOT NULL, movement_hash VARCHAR(64) NOT NULL, closed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', closed_by VARCHAR(180) NOT NULL, note VARCHAR(500) DEFAULT NULL, handover_to VARCHAR(180) DEFAULT NULL, UNIQUE INDEX uniq_cash_closing_club_day (club_id, closing_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE equipment_cash_closing ADD CONSTRAINT FK_CASH_CLOSING_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void {$this->addSql('DROP TABLE equipment_cash_closing');}
}
