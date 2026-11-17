<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add protected media references for courses and schedules';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE courses ADD media_image_id INT DEFAULT NULL, ADD INDEX idx_courses_media_image (media_image_id), ADD CONSTRAINT fk_courses_media_image FOREIGN KEY (media_image_id) REFERENCES media_file (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE schedule ADD media_image_id INT DEFAULT NULL, ADD INDEX idx_schedule_media_image (media_image_id), ADD CONSTRAINT fk_schedule_media_image FOREIGN KEY (media_image_id) REFERENCES media_file (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Bildzuordnungen dürfen nicht verloren gehen.');
    }
}
