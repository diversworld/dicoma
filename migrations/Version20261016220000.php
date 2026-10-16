<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TÜV-Buchungsstatus: revisionsfähige Historie der Statuswechsel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE member_tank_check_status_history (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, old_status VARCHAR(20) NOT NULL, new_status VARCHAR(20) NOT NULL, actor_identifier VARCHAR(180) NOT NULL, changed_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_tuv_history_booking (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member_tank_check_status_history ADD CONSTRAINT FK_TUV_HISTORY_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member_tank_check_status_history');
    }
}
