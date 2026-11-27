<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair orphaned schedule instructors and allow deleted members to become unassigned.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE schedule s LEFT JOIN member m ON m.id = s.instructor_id SET s.instructor_id = NULL WHERE s.instructor_id IS NOT NULL AND m.id IS NULL');
        $this->addSql('ALTER TABLE schedule DROP FOREIGN KEY FK_5A3811FB8C4FC193');
        $this->addSql('ALTER TABLE schedule ADD CONSTRAINT FK_5A3811FB8C4FC193 FOREIGN KEY (instructor_id) REFERENCES member (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE schedule DROP FOREIGN KEY FK_5A3811FB8C4FC193');
        $this->addSql('ALTER TABLE schedule ADD CONSTRAINT FK_5A3811FB8C4FC193 FOREIGN KEY (instructor_id) REFERENCES member (id)');
    }
}
