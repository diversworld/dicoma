<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add structured rental inventory data without changing existing reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE equipment_item ADD manufacturer_id INT DEFAULT NULL, ADD sub_type_id INT DEFAULT NULL, ADD size_id INT DEFAULT NULL, ADD model VARCHAR(160) DEFAULT NULL, ADD color VARCHAR(80) DEFAULT NULL, ADD rental_fee NUMERIC(10, 2) DEFAULT '0.00' NOT NULL, ADD purchased_at DATE DEFAULT NULL");
        $this->addSql('CREATE INDEX IDX_259FF418A23B42D ON equipment_item (manufacturer_id)');
        $this->addSql('CREATE INDEX IDX_259FF418BA94D067 ON equipment_item (sub_type_id)');
        $this->addSql('CREATE INDEX IDX_259FF418498DA827 ON equipment_item (size_id)');
        $this->addSql('ALTER TABLE equipment_item ADD CONSTRAINT FK_259FF418A23B42D FOREIGN KEY (manufacturer_id) REFERENCES equipment_manufacturer (id)');
        $this->addSql('ALTER TABLE equipment_item ADD CONSTRAINT FK_259FF418BA94D067 FOREIGN KEY (sub_type_id) REFERENCES equipment_sub_type (id)');
        $this->addSql('ALTER TABLE equipment_item ADD CONSTRAINT FK_259FF418498DA827 FOREIGN KEY (size_id) REFERENCES equipment_size (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_item DROP FOREIGN KEY FK_259FF418A23B42D');
        $this->addSql('ALTER TABLE equipment_item DROP FOREIGN KEY FK_259FF418BA94D067');
        $this->addSql('ALTER TABLE equipment_item DROP FOREIGN KEY FK_259FF418498DA827');
        $this->addSql('DROP INDEX IDX_259FF418A23B42D ON equipment_item');
        $this->addSql('DROP INDEX IDX_259FF418BA94D067 ON equipment_item');
        $this->addSql('DROP INDEX IDX_259FF418498DA827 ON equipment_item');
        $this->addSql('ALTER TABLE equipment_item DROP manufacturer_id, DROP sub_type_id, DROP size_id, DROP model, DROP color, DROP rental_fee, DROP purchased_at');
    }
}
