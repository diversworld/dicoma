<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013190000 extends AbstractMigration
{
    public function getDescription(): string { return 'Equipment manufacturer, subtype and size master data'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment_manufacturer (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, name VARCHAR(120) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_EQUIPMENT_MANUFACTURER_CLUB (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_manufacturer ADD CONSTRAINT FK_EQUIPMENT_MANUFACTURER_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('CREATE TABLE equipment_sub_type (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, category_id INT NOT NULL, name VARCHAR(120) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_EQUIPMENT_SUB_TYPE_CLUB (club_id), INDEX IDX_EQUIPMENT_SUB_TYPE_CATEGORY (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_sub_type ADD CONSTRAINT FK_EQUIPMENT_SUB_TYPE_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE equipment_sub_type ADD CONSTRAINT FK_EQUIPMENT_SUB_TYPE_CATEGORY FOREIGN KEY (category_id) REFERENCES equipment_category (id)');
        $this->addSql('CREATE TABLE equipment_size (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, category_id INT NOT NULL, name VARCHAR(120) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_EQUIPMENT_SIZE_CLUB (club_id), INDEX IDX_EQUIPMENT_SIZE_CATEGORY (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_size ADD CONSTRAINT FK_EQUIPMENT_SIZE_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE equipment_size ADD CONSTRAINT FK_EQUIPMENT_SIZE_CATEGORY FOREIGN KEY (category_id) REFERENCES equipment_category (id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE equipment_size');
        $this->addSql('DROP TABLE equipment_sub_type');
        $this->addSql('DROP TABLE equipment_manufacturer');
    }
}
