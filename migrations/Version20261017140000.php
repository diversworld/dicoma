<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'TÜV-Prüfergebnisse je Anmeldung';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE member_tank_check_result (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, result VARCHAR(10) NOT NULL, inspected_at DATE NOT NULL, notes LONGTEXT DEFAULT NULL, updated_by VARCHAR(180) NOT NULL, updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX UNIQ_TUV_RESULT_BOOKING (booking_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE member_tank_check_result ADD CONSTRAINT FK_TUV_RESULT_BOOKING FOREIGN KEY (booking_id) REFERENCES member_tank_check_booking (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE member_tank_check_result');
    }
}
