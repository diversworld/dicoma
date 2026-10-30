<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261030110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Add reviewed multi-division course assignments'; }
    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE courses ADD division_reviewed TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("CREATE TABLE course_division (course_id INT NOT NULL, sport_id INT NOT NULL, INDEX IDX_B9A32A42591CC992 (course_id), INDEX IDX_B9A32A42AC78BCF8 (sport_id), PRIMARY KEY(course_id, sport_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE course_division ADD CONSTRAINT fk_course_division_course FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE course_division ADD CONSTRAINT fk_course_division_sport FOREIGN KEY (sport_id) REFERENCES sport (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Kurs-Spartenzuordnungen und ihr Prüfstatus müssen erhalten bleiben.');
    }
}
