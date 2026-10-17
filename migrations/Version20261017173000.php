<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017173000 extends AbstractMigration
{
    public function getDescription(): string { return 'Gemeinsame TÜV-Buchungen für Mitglieder- und Vereinsflaschen'; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member_tank_check_booking ADD club_tank_id INT DEFAULT NULL, CHANGE member_id member_id INT DEFAULT NULL, CHANGE member_tank_id member_tank_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_TUV_BOOKING_CLUB_TANK ON member_tank_check_booking (club_tank_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TUV_CLUB_TANK_CHECK ON member_tank_check_booking (club_tank_id, tank_check_id)');
        $this->addSql('ALTER TABLE member_tank_check_booking ADD CONSTRAINT FK_TUV_BOOKING_CLUB_TANK FOREIGN KEY (club_tank_id) REFERENCES tank (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM member_tank_check_booking WHERE member_id IS NULL OR member_tank_id IS NULL') > 0,
            'Club tank bookings exist. Rollback requires an explicit data migration before restoring mandatory member references.'
        );
        $this->addSql('ALTER TABLE member_tank_check_booking DROP FOREIGN KEY FK_TUV_BOOKING_CLUB_TANK');
        $this->addSql('DROP INDEX UNIQ_TUV_CLUB_TANK_CHECK ON member_tank_check_booking');
        $this->addSql('DROP INDEX IDX_TUV_BOOKING_CLUB_TANK ON member_tank_check_booking');
        $this->addSql('ALTER TABLE member_tank_check_booking DROP club_tank_id, CHANGE member_id member_id INT NOT NULL, CHANGE member_tank_id member_tank_id INT NOT NULL');
    }
}
