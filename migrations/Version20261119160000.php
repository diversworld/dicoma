<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119160000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add contribution runs, division surcharges and invoice/reminder state'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('CREATE TABLE division_contribution (id INT AUTO_INCREMENT NOT NULL, division_id INT NOT NULL, year INT NOT NULL, annual_cents INT NOT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX uniq_division_contribution (division_id, year), INDEX IDX_2048BE2F41859289 (division_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE division_contribution ADD CONSTRAINT FK_division_contribution_sport FOREIGN KEY (division_id) REFERENCES sport (id) ON DELETE RESTRICT');
        $this->addSql('CREATE TABLE membership_billing_run (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, year INT NOT NULL, due_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', status VARCHAR(20) NOT NULL, issued_count INT NOT NULL, total_cents INT NOT NULL, created_by VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_membership_billing_run (club_id, year), INDEX IDX_4DA78D2961190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE membership_billing_run ADD CONSTRAINT FK_membership_billing_run_club FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_charge ADD invoice_number VARCHAR(40) DEFAULT NULL, ADD invoice_issued_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD last_reminder_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD reminder_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE membership_charge DROP invoice_number, DROP invoice_issued_at, DROP last_reminder_at, DROP reminder_count');
        $this->addSql('DROP TABLE membership_billing_run');
        $this->addSql('DROP TABLE division_contribution');
    }
}
