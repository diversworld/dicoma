<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013110000 extends AbstractMigration
{
    public function getDescription(): string { return 'Universal equipment categories, attributes and inventory (MySQL 5.7)'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE equipment_category (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, code VARCHAR(60) NOT NULL, name VARCHAR(180) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_EQ_CAT_CLUB (club_id), UNIQUE INDEX uniq_equipment_category_club_code (club_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE equipment_category ADD CONSTRAINT FK_EQ_CAT_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql("CREATE TABLE equipment_attribute (id INT AUTO_INCREMENT NOT NULL, category_id INT NOT NULL, code VARCHAR(60) NOT NULL, label VARCHAR(180) NOT NULL, data_type VARCHAR(20) NOT NULL, required TINYINT(1) NOT NULL, INDEX IDX_EQ_ATTR_CATEGORY (category_id), UNIQUE INDEX uniq_equipment_attribute_category_code (category_id, code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE equipment_attribute ADD CONSTRAINT FK_EQ_ATTR_CATEGORY FOREIGN KEY (category_id) REFERENCES equipment_category (id) ON DELETE CASCADE');
        $this->addSql("CREATE TABLE equipment_item (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, category_id INT NOT NULL, inventory_number VARCHAR(80) NOT NULL, name VARCHAR(180) NOT NULL, stock_type VARCHAR(30) NOT NULL, total_quantity INT NOT NULL, condition_status VARCHAR(30) NOT NULL, lendable TINYINT(1) NOT NULL, serial_number VARCHAR(120) DEFAULT NULL, attribute_values JSON NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_EQ_ITEM_CLUB (club_id), INDEX IDX_EQ_ITEM_CATEGORY (category_id), UNIQUE INDEX uniq_equipment_item_club_inventory (club_id, inventory_number), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE equipment_item ADD CONSTRAINT FK_EQ_ITEM_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE equipment_item ADD CONSTRAINT FK_EQ_ITEM_CATEGORY FOREIGN KEY (category_id) REFERENCES equipment_category (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE equipment_item');
        $this->addSql('DROP TABLE equipment_attribute');
        $this->addSql('DROP TABLE equipment_category');
    }
}
