<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add function definitions, assignments and inherited folder policies without changing existing roles';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql("CREATE TABLE club_function (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, name VARCHAR(120) NOT NULL, permissions JSON NOT NULL COMMENT '(DC2Type:json)', successor_required TINYINT(1) NOT NULL, starts_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', ends_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', version INT DEFAULT 1 NOT NULL, root_read TINYINT(1) DEFAULT NULL, root_upload TINYINT(1) DEFAULT NULL, root_edit TINYINT(1) DEFAULT NULL, root_delete TINYINT(1) DEFAULT NULL, INDEX IDX_C915BC9461190A32 (club_id), UNIQUE INDEX uniq_club_function_name (club_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE club_function_assignment (id INT AUTO_INCREMENT NOT NULL, club_function_id INT NOT NULL, user_id INT NOT NULL, starts_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', ends_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', version INT DEFAULT 1 NOT NULL, INDEX IDX_2D340681B9F6D08A (club_function_id), INDEX IDX_2D340681A76ED395 (user_id), UNIQUE INDEX uniq_function_assignee (club_function_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE media_folder_permission (id INT AUTO_INCREMENT NOT NULL, club_function_id INT NOT NULL, folder_id INT NOT NULL, can_read TINYINT(1) DEFAULT NULL, can_upload TINYINT(1) DEFAULT NULL, can_edit TINYINT(1) DEFAULT NULL, can_delete TINYINT(1) DEFAULT NULL, version INT DEFAULT 1 NOT NULL, INDEX IDX_5DFEDFD0B9F6D08A (club_function_id), INDEX IDX_5DFEDFD0162CB942 (folder_id), UNIQUE INDEX uniq_folder_function_permission (club_function_id, folder_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE club_function ADD CONSTRAINT FK_C915BC9461190A32 FOREIGN KEY (club_id) REFERENCES club (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_function_assignment ADD CONSTRAINT FK_2D340681B9F6D08A FOREIGN KEY (club_function_id) REFERENCES club_function (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_function_assignment ADD CONSTRAINT FK_2D340681A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE media_folder_permission ADD CONSTRAINT FK_5DFEDFD0B9F6D08A FOREIGN KEY (club_function_id) REFERENCES club_function (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE media_folder_permission ADD CONSTRAINT FK_5DFEDFD0162CB942 FOREIGN KEY (folder_id) REFERENCES media_folder (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Funktionszuordnungen und Ordnerrechte müssen erhalten bleiben.');
    }
}
