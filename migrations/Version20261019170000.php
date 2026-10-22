<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261019170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Leihgebuehren-Kassenbelege, MySQL 5.7'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment_rental_receipt (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, amount_cents INT NOT NULL, method VARCHAR(20) NOT NULL, collected_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', collected_by VARCHAR(180) NOT NULL, note VARCHAR(500) DEFAULT NULL, UNIQUE INDEX uniq_rental_receipt_booking (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_rental_receipt ADD CONSTRAINT FK_RENTAL_RECEIPT_BOOKING FOREIGN KEY (booking_id) REFERENCES equipment_booking (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE equipment_rental_receipt'); }
}
