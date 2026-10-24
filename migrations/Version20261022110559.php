<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261022110559 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align equipment index names while accepting already canonical historical indexes';
    }

    public function isTransactional(): bool
    {
        return false;
    }

    public function up(Schema $schema): void
    {
        $this->renameIndexes($schema, false);
    }

    public function down(Schema $schema): void
    {
        $this->renameIndexes($schema, true);
    }

    private function renameIndexes(Schema $schema, bool $reverse): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $indexes = [
            ['equipment_model', 'idx_eq_model_club', 'IDX_3ECC533D61190A32', ['club_id']],
            ['equipment_model', 'idx_eq_model_category', 'IDX_3ECC533D12469DE2', ['category_id']],
            ['equipment_model', 'idx_eq_model_manufacturer', 'IDX_3ECC533DA23B42D', ['manufacturer_id']],
            ['equipment_size', 'idx_eq_size_manufacturer', 'IDX_CD44F56CA23B42D', ['manufacturer_id']],
            ['equipment_item', 'idx_eq_item_model_reference', 'IDX_259FF418BF9772C3', ['model_reference_id']],
        ];
        $statements = [];
        foreach ($reverse ? array_reverse($indexes) : $indexes as [$tableName, $old, $new, $columns]) {
            $source = $reverse ? $new : $old;
            $target = $reverse ? $old : $new;
            $this->abortIf(!$schema->hasTable($tableName), 'Missing equipment table: ' . $tableName);
            $table = $schema->getTable($tableName);
            $hasSource = $table->hasIndex($source);
            $hasTarget = $table->hasIndex($target);
            $this->abortIf($hasSource === $hasTarget, 'Expected exactly one equipment index name on ' . $tableName . ': ' . $source . ' or ' . $target);
            $index = $table->getIndex($hasSource ? $source : $target);
            $prefixLengths = $index->hasOption('lengths') ? array_filter($index->getOption('lengths'), static fn ($length): bool => $length !== null) : [];
            $this->abortIf(!$index->isSimpleIndex() || $index->getColumns() !== $columns || $prefixLengths !== [], 'Unexpected equipment index definition on ' . $tableName . '.' . $index->getName());
            if ($hasSource) {
                $statements[] = sprintf(
                    'ALTER TABLE %s RENAME INDEX %s TO %s',
                    $this->connection->quoteIdentifier($tableName),
                    $this->connection->quoteIdentifier($source),
                    $this->connection->quoteIdentifier($target),
                );
            }
        }
        foreach ($statements as $statement) {
            $this->addSql($statement);
        }
    }
}
