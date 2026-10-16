<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Equipment reservations (initial reservation workflow)'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE equipment_reservation (id INT AUTO_INCREMENT NOT NULL, equipment_item_id INT NOT NULL, member_id INT NOT NULL, starts_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ends_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', quantity INT NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'reserved', notes LONGTEXT DEFAULT NULL, INDEX idx_equipment_reservation_period (equipment_item_id, starts_at, ends_at), INDEX IDX_EQ_RES_MEMBER (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE equipment_reservation ADD CONSTRAINT FK_EQ_RES_ITEM FOREIGN KEY (equipment_item_id) REFERENCES equipment_item (id)');
        $this->addSql('ALTER TABLE equipment_reservation ADD CONSTRAINT FK_EQ_RES_MEMBER FOREIGN KEY (member_id) REFERENCES member (id)');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE equipment_reservation'); }
}
