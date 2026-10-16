<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Private Mitgliederflaschen getrennt vom Vereinsinventar'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE member_tank (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, label VARCHAR(100) NOT NULL, serial_number VARCHAR(120) NOT NULL, manufacturer VARCHAR(120) DEFAULT NULL, volume VARCHAR(40) DEFAULT NULL, next_inspection_at DATE DEFAULT NULL COMMENT '(DC2Type:date_immutable)', INDEX idx_member_tank_member (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE member_tank ADD CONSTRAINT FK_MEMBER_TANK_MEMBER FOREIGN KEY (member_id) REFERENCES member (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE member_tank'); }
}
