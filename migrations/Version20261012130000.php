<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit history for course and participant status changes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE course_audit_event (id INT AUTO_INCREMENT NOT NULL, course_id INT NOT NULL, participant_id INT DEFAULT NULL, actor_user_id INT DEFAULT NULL, action VARCHAR(40) NOT NULL, old_status VARCHAR(40) DEFAULT NULL, new_status VARCHAR(40) NOT NULL, reason LONGTEXT DEFAULT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_course_audit_course_date (course_id, occurred_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE course_audit_event');
    }
}
