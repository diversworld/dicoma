<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261122141502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE equipment_cash_correction DROP FOREIGN KEY FK_CASH_CORRECTION_CLUB');
        $this->addSql('DROP TABLE equipment_cash_correction');
        $this->addSql('ALTER TABLE board_meeting RENAME INDEX fk_board_meeting_creator TO IDX_BD0BC936B03A8386');
        $this->addSql('ALTER TABLE board_meeting_participant RENAME INDEX fk_board_participant_user TO IDX_E91984C3A76ED395');
        $this->addSql('ALTER TABLE board_resolution RENAME INDEX fk_board_resolution_meeting TO IDX_51DDDB3667433D9C');
        $this->addSql('ALTER TABLE board_resolution RENAME INDEX fk_board_resolution_responsible TO IDX_51DDDB36602AD315');
        $this->addSql('ALTER TABLE board_task RENAME INDEX fk_board_task_assignee TO IDX_88E8820659EC7D60');
        $this->addSql('ALTER TABLE board_task RENAME INDEX fk_board_task_creator TO IDX_88E88206B03A8386');
        $this->addSql('ALTER TABLE board_task RENAME INDEX fk_board_task_resolution TO IDX_88E8820612A1C43A');
        $this->addSql('ALTER TABLE club_document RENAME INDEX fk_club_document_creator TO IDX_DBD220DFB03A8386');
        $this->addSql('DROP INDEX UNIQ_CLUB_DOCUMENT_VERSION ON club_document_version');
        $this->addSql('ALTER TABLE club_document_version RENAME INDEX fk_club_document_version_uploader TO IDX_7A079CFEA2B28FE8');
        $this->addSql('ALTER TABLE club_document_version RENAME INDEX fk_club_document_version_approver TO IDX_7A079CFE2D234F6A');
        $this->addSql('ALTER TABLE finance_annual_close RENAME INDEX fk_finance_close_actor TO IDX_CB7F85C9E1FA7797');
        $this->addSql('ALTER TABLE finance_transaction RENAME INDEX fk_finance_tx_debit TO IDX_8D0AD41A204C4EAA');
        $this->addSql('ALTER TABLE finance_transaction RENAME INDEX fk_finance_tx_credit TO IDX_8D0AD41A6813E404');
        $this->addSql('ALTER TABLE finance_transaction RENAME INDEX uniq_finance_reverse TO UNIQ_8D0AD41A6EEF78AB');
        $this->addSql('ALTER TABLE membership_application_approval CHANGE approved_at approved_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE membership_application_approval RENAME INDEX idx_application_approval_application TO IDX_DEDC56B43E030ACD');
        $this->addSql('ALTER TABLE membership_application_approval RENAME INDEX idx_application_approval_reviewer TO IDX_DEDC56B470574616');
        $this->addSql('ALTER TABLE membership_policy RENAME INDEX idx_policy_onboarding_contribution TO IDX_8EC0B48F3E76C660');
        $this->addSql('ALTER TABLE privacy_policy RENAME INDEX uniq_privacy_club TO UNIQ_3EE6A81B61190A32');
        $this->addSql('ALTER TABLE sepa_direct_debit_batch RENAME INDEX fk_sepa_batch_creator TO IDX_D64E33CEB03A8386');
        $this->addSql('ALTER TABLE sepa_direct_debit_batch RENAME INDEX fk_sepa_batch_approver TO IDX_D64E33CE2D234F6A');
        $this->addSql('ALTER TABLE sepa_direct_debit_item RENAME INDEX fk_sepa_item_charge TO IDX_EE77BE6355284914');
        $this->addSql('ALTER TABLE sepa_direct_debit_item RENAME INDEX fk_sepa_item_mandate TO IDX_EE77BE636C1129CD');
        $this->addSql('ALTER TABLE tank DROP source_id, DROP alias, DROP manufacturer, DROP baz_number, DROP check_id, DROP owner_id, DROP published, DROP add_notes, DROP source_tstamp, DROP last_order, DROP rental_start, DROP rental_stop, CHANGE serialnumber serialnumber VARCHAR(20) NOT NULL, CHANGE condition_status condition_status VARCHAR(20) DEFAULT \'ready\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE equipment_cash_correction (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, source_type VARCHAR(12) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, source_id INT NOT NULL, direction VARCHAR(3) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, amount_cents INT NOT NULL, reason VARCHAR(500) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_by VARCHAR(180) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, created_at DATETIME NOT NULL, INDEX IDX_CASH_CORRECTION_CLUB (club_id), UNIQUE INDEX UNIQ_CASH_CORRECTION_SOURCE (club_id, source_type, source_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE equipment_cash_correction ADD CONSTRAINT FK_CASH_CORRECTION_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE sepa_direct_debit_item RENAME INDEX idx_ee77be6355284914 TO FK_SEPA_ITEM_CHARGE');
        $this->addSql('ALTER TABLE sepa_direct_debit_item RENAME INDEX idx_ee77be636c1129cd TO FK_SEPA_ITEM_MANDATE');
        $this->addSql('ALTER TABLE membership_application_approval CHANGE approved_at approved_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE membership_application_approval RENAME INDEX idx_dedc56b43e030acd TO IDX_APPLICATION_APPROVAL_APPLICATION');
        $this->addSql('ALTER TABLE membership_application_approval RENAME INDEX idx_dedc56b470574616 TO IDX_APPLICATION_APPROVAL_REVIEWER');
        $this->addSql('ALTER TABLE club_document RENAME INDEX idx_dbd220dfb03a8386 TO FK_CLUB_DOCUMENT_CREATOR');
        $this->addSql('ALTER TABLE board_meeting_participant RENAME INDEX idx_e91984c3a76ed395 TO FK_BOARD_PARTICIPANT_USER');
        $this->addSql('ALTER TABLE board_resolution RENAME INDEX idx_51dddb36602ad315 TO FK_BOARD_RESOLUTION_RESPONSIBLE');
        $this->addSql('ALTER TABLE board_resolution RENAME INDEX idx_51dddb3667433d9c TO FK_BOARD_RESOLUTION_MEETING');
        $this->addSql('ALTER TABLE sepa_direct_debit_batch RENAME INDEX idx_d64e33ceb03a8386 TO FK_SEPA_BATCH_CREATOR');
        $this->addSql('ALTER TABLE sepa_direct_debit_batch RENAME INDEX idx_d64e33ce2d234f6a TO FK_SEPA_BATCH_APPROVER');
        $this->addSql('ALTER TABLE finance_annual_close RENAME INDEX idx_cb7f85c9e1fa7797 TO FK_FINANCE_CLOSE_ACTOR');
        $this->addSql('ALTER TABLE membership_policy RENAME INDEX idx_8ec0b48f3e76c660 TO IDX_POLICY_ONBOARDING_CONTRIBUTION');
        $this->addSql('ALTER TABLE board_meeting RENAME INDEX idx_bd0bc936b03a8386 TO FK_BOARD_MEETING_CREATOR');
        $this->addSql('ALTER TABLE board_task RENAME INDEX idx_88e8820612a1c43a TO FK_BOARD_TASK_RESOLUTION');
        $this->addSql('ALTER TABLE board_task RENAME INDEX idx_88e8820659ec7d60 TO FK_BOARD_TASK_ASSIGNEE');
        $this->addSql('ALTER TABLE board_task RENAME INDEX idx_88e88206b03a8386 TO FK_BOARD_TASK_CREATOR');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CLUB_DOCUMENT_VERSION ON club_document_version (document_id, version)');
        $this->addSql('ALTER TABLE club_document_version RENAME INDEX idx_7a079cfea2b28fe8 TO FK_CLUB_DOCUMENT_VERSION_UPLOADER');
        $this->addSql('ALTER TABLE club_document_version RENAME INDEX idx_7a079cfe2d234f6a TO FK_CLUB_DOCUMENT_VERSION_APPROVER');
        $this->addSql('ALTER TABLE privacy_policy RENAME INDEX uniq_3ee6a81b61190a32 TO UNIQ_PRIVACY_CLUB');
        $this->addSql('ALTER TABLE tank ADD source_id INT UNSIGNED DEFAULT NULL, ADD alias VARCHAR(255) DEFAULT NULL, ADD manufacturer VARCHAR(255) DEFAULT NULL, ADD baz_number VARCHAR(50) DEFAULT NULL, ADD check_id INT UNSIGNED DEFAULT NULL, ADD owner_id INT UNSIGNED DEFAULT NULL, ADD published TINYINT(1) DEFAULT 1 NOT NULL, ADD add_notes TINYINT(1) DEFAULT 0 NOT NULL, ADD source_tstamp INT UNSIGNED DEFAULT NULL, ADD last_order VARCHAR(255) DEFAULT NULL, ADD rental_start VARCHAR(10) DEFAULT NULL, ADD rental_stop VARCHAR(10) DEFAULT NULL, CHANGE condition_status condition_status VARCHAR(255) DEFAULT \'ready\' NOT NULL, CHANGE serialnumber serialnumber VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE finance_transaction RENAME INDEX idx_8d0ad41a204c4eaa TO FK_FINANCE_TX_DEBIT');
        $this->addSql('ALTER TABLE finance_transaction RENAME INDEX idx_8d0ad41a6813e404 TO FK_FINANCE_TX_CREDIT');
        $this->addSql('ALTER TABLE finance_transaction RENAME INDEX uniq_8d0ad41a6eef78ab TO UNIQ_FINANCE_REVERSE');
    }
}
