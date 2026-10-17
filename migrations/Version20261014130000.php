<?php

declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014130000 extends AbstractMigration
{
    public function getDescription(): string { return 'Vereinsbezogene Ausrüstungsmodelle und optionale Zuordnung zu Einzelinventar'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE equipment_model (id INT AUTO_INCREMENT NOT NULL, club_id INT NOT NULL, category_id INT NOT NULL, manufacturer_id INT NOT NULL, name VARCHAR(160) NOT NULL, active TINYINT(1) NOT NULL, INDEX IDX_3ECC533D61190A32 (club_id), INDEX IDX_3ECC533D12469DE2 (category_id), INDEX IDX_3ECC533DA23B42D (manufacturer_id), UNIQUE INDEX uniq_eq_model_club_category_manufacturer_name (club_id, category_id, manufacturer_id, name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE equipment_model ADD CONSTRAINT FK_EQ_MODEL_CLUB FOREIGN KEY (club_id) REFERENCES club (id)');
        $this->addSql('ALTER TABLE equipment_model ADD CONSTRAINT FK_EQ_MODEL_CATEGORY FOREIGN KEY (category_id) REFERENCES equipment_category (id)');
        $this->addSql('ALTER TABLE equipment_model ADD CONSTRAINT FK_EQ_MODEL_MANUFACTURER FOREIGN KEY (manufacturer_id) REFERENCES equipment_manufacturer (id)');
        $this->addSql('ALTER TABLE equipment_item ADD model_reference_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE equipment_item ADD INDEX IDX_259FF418BF9772C3 (model_reference_id)');
        $this->addSql('ALTER TABLE equipment_item ADD CONSTRAINT FK_EQ_ITEM_MODEL_REFERENCE FOREIGN KEY (model_reference_id) REFERENCES equipment_model (id)');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_item DROP FOREIGN KEY FK_EQ_ITEM_MODEL_REFERENCE');
        $this->addSql('DROP INDEX IDX_259FF418BF9772C3 ON equipment_item');
        $this->addSql('ALTER TABLE equipment_item DROP model_reference_id');
        $this->addSql('DROP TABLE equipment_model');
    }
}
