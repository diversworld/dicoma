<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024102048 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add club membership lifecycle, contributions, SEPA mandates and private document categories without removing existing tables';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $duplicates = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT club_id, member_number FROM member WHERE club_id IS NOT NULL AND member_number IS NOT NULL GROUP BY club_id, member_number HAVING COUNT(*) > 1) duplicates');
        $this->abortIf($duplicates !== 0, 'Duplicate club membership numbers must be resolved explicitly before this migration; no data was changed.');
        $this->addSql('CREATE TABLE contribution_class (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, name VARCHAR(100) NOT NULL, annual_cents INT NOT NULL, family TINYINT(1) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_8838122D61190A32 (club_id), UNIQUE INDEX uniq_contribution_club_name (club_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership_application (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, member_id INT DEFAULT NULL, version INT DEFAULT 1 NOT NULL, firstname VARCHAR(100) NOT NULL, lastname VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, birthday DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', street VARCHAR(100) NOT NULL, postal_code VARCHAR(20) NOT NULL, city VARCHAR(120) NOT NULL, status VARCHAR(20) NOT NULL, submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', decided_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', decided_by VARCHAR(180) DEFAULT NULL, decision_note VARCHAR(1000) DEFAULT NULL, INDEX IDX_A1B9886461190A32 (club_id), INDEX IDX_A1B988647597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership_charge (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, charge_key VARCHAR(100) NOT NULL, description VARCHAR(180) NOT NULL, amount_cents INT NOT NULL, paid_cents INT NOT NULL, due_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2BD615AD7597D3FE (member_id), UNIQUE INDEX uniq_membership_charge_key (charge_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership_event (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, kind VARCHAR(50) NOT NULL, actor VARCHAR(180) NOT NULL, description VARCHAR(1000) NOT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_37632EAA7597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership_message (id INT AUTO_INCREMENT NOT NULL, message_key VARCHAR(150) NOT NULL, recipient VARCHAR(180) NOT NULL, subject VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, state VARCHAR(20) NOT NULL, error LONGTEXT DEFAULT NULL, sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_membership_message_key (message_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership_payment (id INT AUTO_INCREMENT NOT NULL, charge_id INT NOT NULL, amount_cents INT NOT NULL, reference VARCHAR(180) NOT NULL, actor VARCHAR(180) NOT NULL, received_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_E485125C55284914 (charge_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership_policy (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, cancellation_cutoff VARCHAR(5) DEFAULT NULL, membership_year_end VARCHAR(5) DEFAULT NULL, applications_enabled TINYINT(1) NOT NULL, contact_email VARCHAR(180) DEFAULT NULL, required_documents JSON NOT NULL COMMENT \'(DC2Type:json)\', creditor_identifier VARCHAR(35) DEFAULT NULL, UNIQUE INDEX UNIQ_8EC0B48F61190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sepa_mandate (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, member_id INT NOT NULL, document_id INT NOT NULL, reference VARCHAR(35) NOT NULL, creditor_identifier VARCHAR(35) NOT NULL, holder VARCHAR(180) NOT NULL, encrypted_iban VARCHAR(255) NOT NULL, iban_suffix VARCHAR(4) NOT NULL, signed_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', revoked_at DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', INDEX IDX_BCC48E9461190A32 (club_id), INDEX IDX_BCC48E947597D3FE (member_id), INDEX IDX_BCC48E94C33F7837 (document_id), UNIQUE INDEX uniq_sepa_club_reference (club_id, reference), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE contribution_class ADD CONSTRAINT FK_8838122D61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_application ADD CONSTRAINT FK_A1B9886461190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_application ADD CONSTRAINT FK_A1B988647597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_charge ADD CONSTRAINT FK_2BD615AD7597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_event ADD CONSTRAINT FK_37632EAA7597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_payment ADD CONSTRAINT FK_E485125C55284914 FOREIGN KEY (charge_id) REFERENCES membership_charge (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE membership_policy ADD CONSTRAINT FK_8EC0B48F61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sepa_mandate ADD CONSTRAINT FK_BCC48E9461190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sepa_mandate ADD CONSTRAINT FK_BCC48E947597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE sepa_mandate ADD CONSTRAINT FK_BCC48E94C33F7837 FOREIGN KEY (document_id) REFERENCES member_document (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE member ADD contribution_class_id INT DEFAULT NULL, ADD family_payer_id INT DEFAULT NULL, ADD membership_type VARCHAR(60) DEFAULT NULL, ADD club_functions VARCHAR(500) DEFAULT NULL, ADD emergency_contact_name VARCHAR(180) DEFAULT NULL, ADD emergency_contact_phone VARCHAR(40) DEFAULT NULL, ADD emergency_contact_relation VARCHAR(100) DEFAULT NULL, ADD termination_date DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', ADD contribution_discount_cents INT DEFAULT 0 NOT NULL, ADD contribution_discount_reason VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA78622D846 FOREIGN KEY (contribution_class_id) REFERENCES contribution_class (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_70E4FA783C032DA FOREIGN KEY (family_payer_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('CREATE INDEX IDX_70E4FA78622D846 ON member (contribution_class_id)');
        $this->addSql('CREATE INDEX IDX_70E4FA783C032DA ON member (family_payer_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_member_club_number ON member (club_id, member_number)');
        $this->addSql('ALTER TABLE member_document ADD kind VARCHAR(30) DEFAULT \'other\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Membership history, financial records and mandates require an explicitly approved retention/restore procedure.');
    }
}
