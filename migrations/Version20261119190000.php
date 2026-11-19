<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add board tasks, meetings, resolutions, document governance and staged dunning notices.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE board_meeting (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, created_by_id INT NOT NULL, title VARCHAR(180) NOT NULL, starts_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', ends_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', location VARCHAR(255) DEFAULT NULL, agenda LONGTEXT DEFAULT NULL, minutes LONGTEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BOARD_MEETING_CLUB_STARTS (club_id, starts_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE board_resolution (id INT AUTO_INCREMENT NOT NULL, meeting_id INT NOT NULL, responsible_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, decision LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, due_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BOARD_RESOLUTION_STATUS_DUE (status, due_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE board_task (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, assignee_id INT DEFAULT NULL, created_by_id INT NOT NULL, resolution_id INT DEFAULT NULL, title VARCHAR(180) NOT NULL, description LONGTEXT DEFAULT NULL, priority VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, due_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', remind_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', reminder_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_BOARD_TASK_CLUB_STATUS_DUE (club_id, status, due_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE board_meeting_participant (meeting_id INT NOT NULL, user_id INT NOT NULL, PRIMARY KEY(meeting_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE club_document (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, created_by_id INT NOT NULL, title VARCHAR(180) NOT NULL, category VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, member_visible TINYINT(1) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_CLUB_DOCUMENT_CATEGORY_STATUS (club_id, category, status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE club_document_version (id INT AUTO_INCREMENT NOT NULL, document_id INT NOT NULL, uploaded_by_id INT NOT NULL, approved_by_id INT DEFAULT NULL, version INT NOT NULL, storage_name VARCHAR(255) NOT NULL, original_name VARCHAR(255) NOT NULL, mime_type VARCHAR(100) NOT NULL, size INT NOT NULL, uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_CLUB_DOCUMENT_VERSION (document_id, version), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membership_dunning_notice (id INT AUTO_INCREMENT NOT NULL, charge_id INT NOT NULL, stage VARCHAR(20) NOT NULL, subject VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', next_action_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_MEMBERSHIP_DUNNING_STAGE (charge_id, stage), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE board_meeting ADD CONSTRAINT FK_BOARD_MEETING_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE board_meeting ADD CONSTRAINT FK_BOARD_MEETING_CREATOR FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE board_resolution ADD CONSTRAINT FK_BOARD_RESOLUTION_MEETING FOREIGN KEY (meeting_id) REFERENCES board_meeting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE board_resolution ADD CONSTRAINT FK_BOARD_RESOLUTION_RESPONSIBLE FOREIGN KEY (responsible_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE board_task ADD CONSTRAINT FK_BOARD_TASK_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE board_task ADD CONSTRAINT FK_BOARD_TASK_ASSIGNEE FOREIGN KEY (assignee_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE board_task ADD CONSTRAINT FK_BOARD_TASK_CREATOR FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE board_task ADD CONSTRAINT FK_BOARD_TASK_RESOLUTION FOREIGN KEY (resolution_id) REFERENCES board_resolution (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE board_meeting_participant ADD CONSTRAINT FK_BOARD_PARTICIPANT_MEETING FOREIGN KEY (meeting_id) REFERENCES board_meeting (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE board_meeting_participant ADD CONSTRAINT FK_BOARD_PARTICIPANT_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_document ADD CONSTRAINT FK_CLUB_DOCUMENT_CLUB FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_document ADD CONSTRAINT FK_CLUB_DOCUMENT_CREATOR FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_document_version ADD CONSTRAINT FK_CLUB_DOCUMENT_VERSION_DOCUMENT FOREIGN KEY (document_id) REFERENCES club_document (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_document_version ADD CONSTRAINT FK_CLUB_DOCUMENT_VERSION_UPLOADER FOREIGN KEY (uploaded_by_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_document_version ADD CONSTRAINT FK_CLUB_DOCUMENT_VERSION_APPROVER FOREIGN KEY (approved_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE membership_dunning_notice ADD CONSTRAINT FK_MEMBERSHIP_DUNNING_CHARGE FOREIGN KEY (charge_id) REFERENCES membership_charge (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE membership_dunning_notice');
        $this->addSql('DROP TABLE board_task');
        $this->addSql('DROP TABLE club_document_version');
        $this->addSql('DROP TABLE club_document');
        $this->addSql('DROP TABLE board_meeting_participant');
        $this->addSql('DROP TABLE board_resolution');
        $this->addSql('DROP TABLE board_meeting');
    }
}
