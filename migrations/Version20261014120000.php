<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Synchronisiert die Indexnamen der Ausruestungsmodelle und Groessen.';
    }

    public function up(Schema $schema): void
    {
		if (!$this->connection->createSchemaManager()->tablesExist([
			'equipment_model',
			'equipment_size',
			'equipment_item',
		])) {
			return;
		}
		
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
        $schemaManager = $this->connection->createSchemaManager();

        $renames = [
            'equipment_model' => [
                'IDX_3ECC533D61190A32' => 'idx_eq_model_club',
                'IDX_3ECC533D12469DE2' => 'idx_eq_model_category',
                'IDX_3ECC533DA23B42D' => 'idx_eq_model_manufacturer',
            ],
            'equipment_size' => [
                'IDX_CD44F56CA23B42D' => 'idx_eq_size_manufacturer',
            ],
            'equipment_item' => [
                'IDX_259FF418BF9772C3' => 'idx_eq_item_model_reference',
            ],
        ];

        foreach ($renames as $tableName => $indexes) {
            if (!$schemaManager->tablesExist([$tableName])) {
                continue;
            }

            $table = $schemaManager->introspectTable($tableName);

            foreach ($indexes as $oldName => $newName) {
                if (!$table->hasIndex($oldName)) {
                    continue;
                }

                if ($table->hasIndex($newName)) {
                    $this->abortIf(
                        true,
                        sprintf(
                            'Indexumbenennung nicht möglich: %s.%s existiert bereits.',
                            $tableName,
                            $newName
                        )
                    );
                }

                $this->addSql(sprintf(
                    'ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`',
                    $tableName,
                    $oldName,
                    $newName
                ));
            }
        }
    }
}
