<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261022110559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

	public function up(Schema $schema): void
	{
		$this->addSql(
			'ALTER TABLE equipment_model RENAME INDEX idx_eq_model_club TO IDX_3ECC533D61190A32'
		);

		$this->addSql(
			'ALTER TABLE equipment_model RENAME INDEX idx_eq_model_category TO IDX_3ECC533D12469DE2'
		);

		$this->addSql(
			'ALTER TABLE equipment_model RENAME INDEX idx_eq_model_manufacturer TO IDX_3ECC533DA23B42D'
		);

		$this->addSql(
			'ALTER TABLE equipment_size RENAME INDEX idx_eq_size_manufacturer TO IDX_CD44F56CA23B42D'
		);

		$this->addSql(
			'ALTER TABLE equipment_item RENAME INDEX idx_eq_item_model_reference TO IDX_259FF418BF9772C3'
		);
	}
	
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE equipment_item RENAME INDEX IDX_259FF418BF9772C3 TO idx_eq_item_model_reference');
        $this->addSql('ALTER TABLE equipment_size RENAME INDEX IDX_CD44F56CA23B42D TO idx_eq_size_manufacturer');
        $this->addSql('ALTER TABLE equipment_model RENAME INDEX IDX_3ECC533DA23B42D TO idx_eq_model_manufacturer');
        $this->addSql('ALTER TABLE equipment_model RENAME INDEX IDX_3ECC533D12469DE2 TO idx_eq_model_category');
        $this->addSql('ALTER TABLE equipment_model RENAME INDEX IDX_3ECC533D61190A32 TO idx_eq_model_club');
    }
}
