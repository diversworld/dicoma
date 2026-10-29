<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261028180000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add club events, person registrations and immutable payment records'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql("CREATE TABLE club_event (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT NOT NULL, pricing VARCHAR(30) NOT NULL, member_fee_cents INT NOT NULL, guest_fee_cents INT NOT NULL, starts_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ends_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', deadline DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', location VARCHAR(255) NOT NULL, capacity INT DEFAULT NULL, published TINYINT(1) NOT NULL, cancelled TINYINT(1) NOT NULL, INDEX IDX_9BC0201561190A32 (club_id), INDEX idx_club_event_listing (club_id, published, starts_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE club_event_registration (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, person_id INT NOT NULL, status VARCHAR(20) NOT NULL, fee_cents INT NOT NULL, tariff VARCHAR(10) NOT NULL, registered_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', cancelled_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_event_person (event_id, person_id), INDEX idx_event_registration_waiting (event_id, status, registered_at), INDEX IDX_11085451217BBB47 (person_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE club_event_payment (id INT AUTO_INCREMENT NOT NULL, registration_id INT NOT NULL, amount_cents INT NOT NULL, method VARCHAR(20) NOT NULL, reference VARCHAR(120) NOT NULL, recorded_by VARCHAR(180) NOT NULL, recorded_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_event_payment_reference (registration_id, reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE club_event ADD CONSTRAINT fk_event_club FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_event_registration ADD CONSTRAINT fk_event_registration_event FOREIGN KEY (event_id) REFERENCES club_event (id) ON DELETE RESTRICT, ADD CONSTRAINT fk_event_registration_person FOREIGN KEY (person_id) REFERENCES person (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_event_payment ADD CONSTRAINT fk_event_payment_registration FOREIGN KEY (registration_id) REFERENCES club_event_registration (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Veranstaltungen, Anmeldungen und Zahlungsnachweise müssen erhalten bleiben.'); }
}
