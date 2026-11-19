<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add protected internal messages and explicit recipients';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('CREATE TABLE internal_message (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, sender_id INT NOT NULL, division_id INT DEFAULT NULL, audience VARCHAR(20) NOT NULL, target_role VARCHAR(50) DEFAULT NULL, subject VARCHAR(180) NOT NULL, body LONGTEXT NOT NULL, sent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_internal_message_club_sent (club_id, sent_at), INDEX IDX_internal_message_sender (sender_id), INDEX IDX_internal_message_division (division_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE internal_message_recipient (id INT AUTO_INCREMENT NOT NULL, message_id INT NOT NULL, member_id INT NOT NULL, read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_internal_message_recipient_member_read (member_id, read_at), INDEX IDX_internal_message_recipient_message (message_id), UNIQUE INDEX uniq_internal_message_recipient (message_id, member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE internal_message ADD CONSTRAINT FK_internal_message_club FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE internal_message ADD CONSTRAINT FK_internal_message_sender FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE internal_message ADD CONSTRAINT FK_internal_message_division FOREIGN KEY (division_id) REFERENCES sport (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE internal_message_recipient ADD CONSTRAINT FK_internal_message_recipient_message FOREIGN KEY (message_id) REFERENCES internal_message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE internal_message_recipient ADD CONSTRAINT FK_internal_message_recipient_member FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE internal_message_recipient DROP FOREIGN KEY FK_internal_message_recipient_message, DROP FOREIGN KEY FK_internal_message_recipient_member');
        $this->addSql('ALTER TABLE internal_message DROP FOREIGN KEY FK_internal_message_club, DROP FOREIGN KEY FK_internal_message_sender, DROP FOREIGN KEY FK_internal_message_division');
        $this->addSql('DROP TABLE internal_message_recipient');
        $this->addSql('DROP TABLE internal_message');
    }
}
