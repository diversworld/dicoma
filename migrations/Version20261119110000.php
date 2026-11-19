<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event kinds, divisions, responsibility, recurrence and series links';
    }

    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE club_event ADD event_type VARCHAR(20) DEFAULT \'event\' NOT NULL, ADD division_id INT DEFAULT NULL, ADD responsible_id INT DEFAULT NULL, ADD recurrence VARCHAR(10) DEFAULT NULL, ADD recurrence_until DATE DEFAULT NULL COMMENT \'(DC2Type:date_immutable)\', ADD series_parent_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_5B0B8D9A41859289 ON club_event (division_id)');
        $this->addSql('CREATE INDEX IDX_5B0B8D9A602AD315 ON club_event (responsible_id)');
        $this->addSql('CREATE INDEX IDX_5B0B8D9A7B4CFBA ON club_event (series_parent_id)');
        $this->addSql('ALTER TABLE club_event ADD CONSTRAINT FK_club_event_division FOREIGN KEY (division_id) REFERENCES sport (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE club_event ADD CONSTRAINT FK_club_event_responsible FOREIGN KEY (responsible_id) REFERENCES member (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE club_event ADD CONSTRAINT FK_club_event_series_parent FOREIGN KEY (series_parent_id) REFERENCES club_event (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql('ALTER TABLE club_event DROP FOREIGN KEY FK_club_event_division, DROP FOREIGN KEY FK_club_event_responsible, DROP FOREIGN KEY FK_club_event_series_parent');
        $this->addSql('ALTER TABLE club_event DROP INDEX IDX_5B0B8D9A41859289, DROP INDEX IDX_5B0B8D9A602AD315, DROP INDEX IDX_5B0B8D9A7B4CFBA, DROP event_type, DROP division_id, DROP responsible_id, DROP recurrence, DROP recurrence_until, DROP series_parent_id');
    }
}
