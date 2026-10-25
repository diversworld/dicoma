<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261025170000 extends AbstractMigration
{
    public function getDescription(): string { return 'Phase 8: club bar duty scheduling, exchanges, attendance, annual obligations, notifications and independent cash sessions; no existing data changes'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $suffix = ' DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB';
        $this->addSql('CREATE TABLE bar_duty_policy (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, year INT NOT NULL, required_minutes INT NOT NULL, cash_enabled TINYINT(1) NOT NULL, history JSON NOT NULL, INDEX IDX_C2E11B9761190A32 (club_id), UNIQUE INDEX uniq_bar_policy_year (club_id, year), PRIMARY KEY(id))'.$suffix);
        $this->addSql('CREATE TABLE bar_duty_requirement (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, year INT NOT NULL, required_minutes INT DEFAULT NULL, history JSON NOT NULL, INDEX IDX_FE06F93B7597D3FE (member_id), UNIQUE INDEX uniq_bar_member_year (member_id, year), PRIMARY KEY(id))'.$suffix);
        $this->addSql('CREATE TABLE bar_shift_definition (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, title VARCHAR(120) NOT NULL, location VARCHAR(180) NOT NULL, start_time VARCHAR(5) NOT NULL, duration_minutes INT NOT NULL, capacity INT NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_A66C184D61190A32 (club_id), PRIMARY KEY(id))'.$suffix);
        $this->addSql("CREATE TABLE bar_shift (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, definition_id INT NOT NULL, title VARCHAR(120) NOT NULL, location VARCHAR(180) NOT NULL, starts_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ends_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', capacity INT NOT NULL, status VARCHAR(20) NOT NULL, history JSON NOT NULL, INDEX IDX_6DDBAAD161190A32 (club_id), INDEX IDX_6DDBAAD1D11EA911 (definition_id), INDEX idx_bar_shift_period (club_id, starts_at, ends_at), UNIQUE INDEX uniq_bar_definition_start (definition_id, starts_at), PRIMARY KEY(id))".$suffix);
        $this->addSql('CREATE TABLE bar_duty_assignment (id INT AUTO_INCREMENT NOT NULL, shift_id INT NOT NULL, member_id INT NOT NULL, status VARCHAR(20) NOT NULL, attendance VARCHAR(20) NOT NULL, credited_minutes INT NOT NULL, history JSON NOT NULL, INDEX IDX_66BB8F08BB70BC0E (shift_id), INDEX IDX_66BB8F087597D3FE (member_id), INDEX idx_bar_assignment_member (member_id, status), PRIMARY KEY(id))'.$suffix);
        $this->addSql("CREATE TABLE bar_duty_exchange (id INT AUTO_INCREMENT NOT NULL, source_id INT NOT NULL, target_id INT NOT NULL, counterpart_id INT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', decided_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', decided_by VARCHAR(180) DEFAULT NULL, INDEX IDX_BAC168C953C1C61 (source_id), INDEX IDX_BAC168C158E0B66 (target_id), INDEX IDX_BAC168C606374F2 (counterpart_id), PRIMARY KEY(id))".$suffix);
        $this->addSql("CREATE TABLE bar_duty_notification (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, shift_id INT NOT NULL, event_key VARCHAR(120) NOT NULL, subject VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', read_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', mail_state VARCHAR(20) NOT NULL, mail_error VARCHAR(500) DEFAULT NULL, INDEX IDX_6511F7B67597D3FE (member_id), INDEX IDX_6511F7B6BB70BC0E (shift_id), UNIQUE INDEX uniq_bar_notification_event (event_key), PRIMARY KEY(id))".$suffix);
        $this->addSql("CREATE TABLE bar_cash_session (id INT AUTO_INCREMENT NOT NULL, shift_id INT NOT NULL, opening_cents INT NOT NULL, opened_by VARCHAR(180) NOT NULL, opened_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', opening_note VARCHAR(500) NOT NULL, closed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', closed_by VARCHAR(180) DEFAULT NULL, expected_cents INT DEFAULT NULL, counted_cents INT DEFAULT NULL, closing_note VARCHAR(500) DEFAULT NULL, movement_hash VARCHAR(64) DEFAULT NULL, UNIQUE INDEX UNIQ_7919E9F1BB70BC0E (shift_id), PRIMARY KEY(id))".$suffix);
        $this->addSql("CREATE TABLE bar_cash_entry (id INT AUTO_INCREMENT NOT NULL, session_id INT NOT NULL, reversal_of_id INT DEFAULT NULL, amount_cents INT NOT NULL, reason VARCHAR(500) NOT NULL, reference VARCHAR(120) NOT NULL, created_by VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_B7CF693B613FECDF (session_id), UNIQUE INDEX UNIQ_B7CF693B29A0BB4E (reversal_of_id), PRIMARY KEY(id))".$suffix);
        $this->addSql('ALTER TABLE bar_duty_policy ADD CONSTRAINT FK_C2E11B9761190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_duty_requirement ADD CONSTRAINT FK_FE06F93B7597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_shift_definition ADD CONSTRAINT FK_A66C184D61190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_shift ADD CONSTRAINT FK_6DDBAAD161190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_6DDBAAD1D11EA911 FOREIGN KEY (definition_id) REFERENCES bar_shift_definition (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_duty_assignment ADD CONSTRAINT FK_66BB8F08BB70BC0E FOREIGN KEY (shift_id) REFERENCES bar_shift (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_66BB8F087597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_duty_exchange ADD CONSTRAINT FK_BAC168C953C1C61 FOREIGN KEY (source_id) REFERENCES bar_duty_assignment (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_BAC168C158E0B66 FOREIGN KEY (target_id) REFERENCES member (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_BAC168C606374F2 FOREIGN KEY (counterpart_id) REFERENCES bar_duty_assignment (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_duty_notification ADD CONSTRAINT FK_6511F7B67597D3FE FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_6511F7B6BB70BC0E FOREIGN KEY (shift_id) REFERENCES bar_shift (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_cash_session ADD CONSTRAINT FK_7919E9F1BB70BC0E FOREIGN KEY (shift_id) REFERENCES bar_shift (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE bar_cash_entry ADD CONSTRAINT FK_B7CF693B613FECDF FOREIGN KEY (session_id) REFERENCES bar_cash_session (id) ON DELETE RESTRICT, ADD CONSTRAINT FK_B7CF693B29A0BB4E FOREIGN KEY (reversal_of_id) REFERENCES bar_cash_entry (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Dienstnachweise, Zustimmungshistorie, Pflichtstunden und Thekenkassenbelege dürfen nicht gelöscht werden.'); }
}
