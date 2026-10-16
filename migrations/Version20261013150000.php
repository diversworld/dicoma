<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Equipment multi-item booking foundation, preserving legacy reservations'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE equipment_booking (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, starts_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', ends_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', status VARCHAR(20) NOT NULL DEFAULT 'reserved', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', notes LONGTEXT DEFAULT NULL, INDEX IDX_EQ_BOOK_MEMBER (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE equipment_booking_item (id INT AUTO_INCREMENT NOT NULL, booking_id INT NOT NULL, equipment_item_id INT NOT NULL, quantity INT NOT NULL, INDEX IDX_EQ_BI_BOOK (booking_id), INDEX IDX_EQ_BI_ITEM (equipment_item_id), UNIQUE INDEX uniq_booking_item (booking_id, equipment_item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE equipment_booking ADD CONSTRAINT FK_EQ_BOOK_MEMBER FOREIGN KEY (member_id) REFERENCES member (id)');
        $this->addSql('ALTER TABLE equipment_booking_item ADD CONSTRAINT FK_EQ_BI_BOOK FOREIGN KEY (booking_id) REFERENCES equipment_booking (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE equipment_booking_item ADD CONSTRAINT FK_EQ_BI_ITEM FOREIGN KEY (equipment_item_id) REFERENCES equipment_item (id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE equipment_booking_item');
        $this->addSql('DROP TABLE equipment_booking');
    }
}
