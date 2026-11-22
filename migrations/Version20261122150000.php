<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add strict Phase A SEPA export, club accounting, audit and privacy retention configuration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sepa_mandate ADD encrypted_bic VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE membership_policy ADD sepa_creditor_name VARCHAR(35) DEFAULT NULL, ADD sepa_creditor_iban VARCHAR(34) DEFAULT NULL, ADD sepa_creditor_bic VARCHAR(11) DEFAULT NULL, ADD dunning_intervals LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)', ADD dunning_templates LONGTEXT DEFAULT NULL COMMENT '(DC2Type:json)'");
        $this->addSql(<<<'SQL'
UPDATE membership_policy SET
dunning_intervals = '{"reminder":14,"first":28,"second":42,"final":56}',
dunning_templates = '{"reminder":"Zahlungserinnerung: {{ description }}\\nOffener Betrag: {{ amount }} EUR\\nFällig seit: {{ dueDate }}\\nRechnung: {{ invoice }}","first":"Erste Mahnung: {{ description }}\\nOffener Betrag: {{ amount }} EUR\\nFällig seit: {{ dueDate }}\\nRechnung: {{ invoice }}","second":"Zweite Mahnung: {{ description }}\\nOffener Betrag: {{ amount }} EUR\\nFällig seit: {{ dueDate }}\\nRechnung: {{ invoice }}","final":"Letzte Mahnung: {{ description }}\\nOffener Betrag: {{ amount }} EUR\\nFällig seit: {{ dueDate }}\\nRechnung: {{ invoice }}"}'
SQL);
        $this->addSql("ALTER TABLE membership_policy MODIFY dunning_intervals LONGTEXT NOT NULL COMMENT '(DC2Type:json)', MODIFY dunning_templates LONGTEXT NOT NULL COMMENT '(DC2Type:json)'");

        $this->addSql('CREATE TABLE sepa_direct_debit_batch (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, created_by_id INT NOT NULL, approved_by_id INT DEFAULT NULL, batch_key VARCHAR(100) NOT NULL, collection_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', total_cents INT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', exported_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', sha256 VARCHAR(64) DEFAULT NULL, UNIQUE INDEX uniq_sepa_batch_key (club_id, batch_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sepa_direct_debit_item (id INT AUTO_INCREMENT NOT NULL, batch_id INT NOT NULL, charge_id INT NOT NULL, mandate_id INT NOT NULL, amount_cents INT NOT NULL, UNIQUE INDEX uniq_sepa_batch_charge (batch_id, charge_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE finance_account (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, code VARCHAR(20) NOT NULL, name VARCHAR(120) NOT NULL, type VARCHAR(20) NOT NULL, active TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_finance_account_code (club_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE finance_transaction (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, debit_account_id INT NOT NULL, credit_account_id INT NOT NULL, reversed_by_id INT DEFAULT NULL, effective_at DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\', amount_cents INT NOT NULL, description VARCHAR(240) NOT NULL, reference VARCHAR(180) DEFAULT NULL, source_key VARCHAR(180) NOT NULL, actor VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_finance_source (club_id, source_key), UNIQUE INDEX UNIQ_FINANCE_REVERSE (reversed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql("CREATE TABLE finance_annual_close (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, closed_by_id INT NOT NULL, year INT NOT NULL, income_cents INT NOT NULL, expense_cents INT NOT NULL, balances LONGTEXT NOT NULL COMMENT '(DC2Type:json)', sha256 VARCHAR(64) NOT NULL, closed_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_finance_annual_close (club_id, year), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE audit_event (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, actor VARCHAR(180) NOT NULL, action VARCHAR(80) NOT NULL, subject_type VARCHAR(180) NOT NULL, subject_id VARCHAR(100) DEFAULT NULL, context LONGTEXT NOT NULL COMMENT '(DC2Type:json)', occurred_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_audit_club_occurred (club_id, occurred_at), INDEX idx_audit_purge (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('CREATE TABLE privacy_policy (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, member_retention_years INT DEFAULT 3 NOT NULL, message_retention_days INT DEFAULT 365 NOT NULL, financial_retention_years INT DEFAULT 10 NOT NULL, audit_retention_years INT DEFAULT 3 NOT NULL, purge_enabled TINYINT(1) DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_PRIVACY_CLUB (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        foreach ([
            'ALTER TABLE sepa_direct_debit_batch ADD CONSTRAINT FK_SEPA_BATCH_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT',
            'ALTER TABLE sepa_direct_debit_batch ADD CONSTRAINT FK_SEPA_BATCH_CREATOR FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE RESTRICT',
            'ALTER TABLE sepa_direct_debit_batch ADD CONSTRAINT FK_SEPA_BATCH_APPROVER FOREIGN KEY (approved_by_id) REFERENCES `user` (id) ON DELETE SET NULL',
            'ALTER TABLE sepa_direct_debit_item ADD CONSTRAINT FK_SEPA_ITEM_BATCH FOREIGN KEY (batch_id) REFERENCES sepa_direct_debit_batch (id) ON DELETE CASCADE',
            'ALTER TABLE sepa_direct_debit_item ADD CONSTRAINT FK_SEPA_ITEM_CHARGE FOREIGN KEY (charge_id) REFERENCES membership_charge (id) ON DELETE RESTRICT',
            'ALTER TABLE sepa_direct_debit_item ADD CONSTRAINT FK_SEPA_ITEM_MANDATE FOREIGN KEY (mandate_id) REFERENCES sepa_mandate (id) ON DELETE RESTRICT',
            'ALTER TABLE finance_account ADD CONSTRAINT FK_FINANCE_ACCOUNT_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT',
            'ALTER TABLE finance_transaction ADD CONSTRAINT FK_FINANCE_TX_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT',
            'ALTER TABLE finance_transaction ADD CONSTRAINT FK_FINANCE_TX_DEBIT FOREIGN KEY (debit_account_id) REFERENCES finance_account (id) ON DELETE RESTRICT',
            'ALTER TABLE finance_transaction ADD CONSTRAINT FK_FINANCE_TX_CREDIT FOREIGN KEY (credit_account_id) REFERENCES finance_account (id) ON DELETE RESTRICT',
            'ALTER TABLE finance_transaction ADD CONSTRAINT FK_FINANCE_TX_REVERSE FOREIGN KEY (reversed_by_id) REFERENCES finance_transaction (id) ON DELETE RESTRICT',
            'ALTER TABLE finance_annual_close ADD CONSTRAINT FK_FINANCE_CLOSE_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT',
            'ALTER TABLE finance_annual_close ADD CONSTRAINT FK_FINANCE_CLOSE_ACTOR FOREIGN KEY (closed_by_id) REFERENCES `user` (id) ON DELETE RESTRICT',
            'ALTER TABLE audit_event ADD CONSTRAINT FK_AUDIT_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT',
            'ALTER TABLE privacy_policy ADD CONSTRAINT FK_PRIVACY_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT',
        ] as $sql) {
            $this->addSql($sql);
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE privacy_policy, audit_event, finance_annual_close, finance_transaction, finance_account, sepa_direct_debit_item, sepa_direct_debit_batch');
        $this->addSql('ALTER TABLE membership_policy DROP dunning_templates, DROP dunning_intervals, DROP sepa_creditor_bic, DROP sepa_creditor_iban, DROP sepa_creditor_name');
        $this->addSql('ALTER TABLE sepa_mandate DROP encrypted_bic');
    }
}
