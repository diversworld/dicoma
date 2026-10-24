<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\Migrations\AbstractMigration;

final class Version20261024150454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 5: team eligibility, training sessions and independent tournaments with rosters, results, fees and documents';
    }

    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT sport_id, name FROM team GROUP BY sport_id, name HAVING COUNT(*) > 1) duplicates') > 0, 'Duplicate teams require explicit resolution before migration.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM (SELECT team_id, member_id FROM team_member GROUP BY team_id, member_id HAVING COUNT(*) > 1) duplicates') > 0, 'Duplicate roster assignments require explicit resolution before migration.');
        $this->addSql('ALTER TABLE team_member ADD position VARCHAR(80) DEFAULT NULL, ADD jersey_number INT DEFAULT NULL, ADD competition_eligible TINYINT(1) DEFAULT 0 NOT NULL, ADD eligibility_until DATE DEFAULT NULL, ADD eligibility_note VARCHAR(255) DEFAULT NULL');
        if (!$schema->getTable('team')->hasIndex('uniq_team_sport_name')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_team_sport_name ON team (sport_id, name)');
        }
        if (!$schema->getTable('team_member')->hasIndex('uniq_team_member')) {
            $this->addSql('CREATE UNIQUE INDEX uniq_team_member ON team_member (team_id, member_id)');
        }
        $this->addSql('CREATE TABLE team_training (id INT AUTO_INCREMENT NOT NULL, team_id INT NOT NULL, title VARCHAR(150) NOT NULL, starts_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', location VARCHAR(255) NOT NULL, cancelled TINYINT(1) DEFAULT 0 NOT NULL, INDEX IDX_6D5056F1296CD8AE (team_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tournament (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, version INT DEFAULT 1 NOT NULL, name VARCHAR(150) NOT NULL, sport_code VARCHAR(80) NOT NULL, organizer VARCHAR(150) NOT NULL, location VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, starts_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', registration_deadline DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(20) NOT NULL, fee_cents INT NOT NULL, max_teams INT NOT NULL, min_players INT NOT NULL, max_players INT NOT NULL, win_points INT NOT NULL, draw_points INT NOT NULL, loss_points INT NOT NULL, archive JSON DEFAULT NULL COMMENT \'(DC2Type:json)\', archived_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BD5FB8D961190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tournament_document (id INT AUTO_INCREMENT NOT NULL, tournament_id INT NOT NULL, original_name VARCHAR(255) NOT NULL, storage_name VARCHAR(80) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_38D76C9333D1A3E7 (tournament_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tournament_entry (id INT AUTO_INCREMENT NOT NULL, tournament_id INT NOT NULL, team_id INT DEFAULT NULL, name VARCHAR(150) NOT NULL, group_name VARCHAR(40) NOT NULL, contact_email VARCHAR(180) DEFAULT NULL, fee_cents INT NOT NULL, withdrawn TINYINT(1) DEFAULT 0 NOT NULL, registered_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_EA770D6933D1A3E7 (tournament_id), INDEX IDX_EA770D69296CD8AE (team_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tournament_match (id INT AUTO_INCREMENT NOT NULL, tournament_id INT NOT NULL, home_id INT NOT NULL, away_id INT NOT NULL, version INT DEFAULT 1 NOT NULL, round_number INT NOT NULL, starts_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', location VARCHAR(150) DEFAULT NULL, home_score INT DEFAULT NULL, away_score INT DEFAULT NULL, cancelled TINYINT(1) DEFAULT 0 NOT NULL, result_history JSON NOT NULL COMMENT \'(DC2Type:json)\', INDEX IDX_BB0D551C33D1A3E7 (tournament_id), INDEX IDX_BB0D551C28CDC89C (home_id), INDEX IDX_BB0D551C8DEF089F (away_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tournament_payment (id INT AUTO_INCREMENT NOT NULL, entry_id INT NOT NULL, amount_cents INT NOT NULL, reference VARCHAR(120) NOT NULL, recorded_by VARCHAR(180) NOT NULL, recorded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_6277F07BA364942 (entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tournament_player (id INT AUTO_INCREMENT NOT NULL, entry_id INT NOT NULL, team_member_id INT DEFAULT NULL, name VARCHAR(150) NOT NULL, position VARCHAR(80) DEFAULT NULL, jersey_number INT DEFAULT NULL, eligibility_evidence VARCHAR(255) NOT NULL, INDEX IDX_FCB38435BA364942 (entry_id), INDEX IDX_FCB38435C292CD19 (team_member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE team_training ADD CONSTRAINT FK_6D5056F1296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament ADD CONSTRAINT FK_BD5FB8D961190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_document ADD CONSTRAINT FK_38D76C9333D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT FK_EA770D6933D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_entry ADD CONSTRAINT FK_EA770D69296CD8AE FOREIGN KEY (team_id) REFERENCES team (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_match ADD CONSTRAINT FK_BB0D551C33D1A3E7 FOREIGN KEY (tournament_id) REFERENCES tournament (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_match ADD CONSTRAINT FK_BB0D551C28CDC89C FOREIGN KEY (home_id) REFERENCES tournament_entry (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_match ADD CONSTRAINT FK_BB0D551C8DEF089F FOREIGN KEY (away_id) REFERENCES tournament_entry (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_payment ADD CONSTRAINT FK_6277F07BA364942 FOREIGN KEY (entry_id) REFERENCES tournament_entry (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_player ADD CONSTRAINT FK_FCB38435BA364942 FOREIGN KEY (entry_id) REFERENCES tournament_entry (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tournament_player ADD CONSTRAINT FK_FCB38435C292CD19 FOREIGN KEY (team_member_id) REFERENCES team_member (id) ON DELETE RESTRICT');
        $this->addSql('CREATE UNIQUE INDEX uniq_tournament_team ON tournament_entry (tournament_id, team_id)');
        $this->addSql('ALTER TABLE tournament_match ADD duration_minutes INT NOT NULL, MODIFY location VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_tournament_roster_member ON tournament_player (entry_id, team_member_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_tournament_payment_reference ON tournament_payment (entry_id, reference)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Tournament history, payments, player evidence and existing roster data must be preserved.');
    }
}
