<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017223000 extends AbstractMigration
{
    private const INDEXES = [
        ['tuv_booking_group', 'IDX_TUV_GROUP_CHECK', 'IDX_DD9DF8C95531E667', 'tank_check_id'],
        ['tuv_notification', 'IDX_TUV_NOTIFICATION_BOOKING', 'IDX_2AE17AFE3301C60', 'booking_id'],
        ['tuv_document', 'IDX_TUV_DOCUMENT_BOOKING', 'IDX_381BAC323301C60', 'booking_id'],
        ['member_tank_check_booking', 'IDX_TUV_BOOKING_CLUB_TANK', 'IDX_CD4256C3188EF737', 'club_tank_id'],
        ['tuv_handover', 'IDX_TUV_HANDOVER_BOOKING', 'IDX_606BC6903301C60', 'booking_id'],
        ['tuv_group_booking', 'IDX_TUV_GROUP_MEMBER_GROUP', 'IDX_472DF86BFE54D947', 'group_id'],
    ];

    public function getDescription(): string
    {
        return 'Align remaining TUV foreign key index names with Doctrine without changing data or unique constraints';
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
        foreach (self::INDEXES as [$tableName, $old, $new, $column]) {
            if ($reverse) {
                [$old, $new] = [$new, $old];
            }
            $table = $schema->getTable($tableName);
            $this->abortIf($table->hasIndex($old) && $table->hasIndex($new), sprintf(
                'Both indexes %s and %s exist on %s; inspect their definitions before retrying.',
                $old, $new, $tableName
            ));
            $this->abortIf(!$table->hasIndex($old) && !$table->hasIndex($new), sprintf(
                'Neither index %s nor %s exists on %s; inspect SHOW INDEX before retrying.',
                $old, $new, $tableName
            ));
            $index = $table->getIndex($table->hasIndex($old) ? $old : $new);
            $this->abortIf($index->isUnique() || $index->getColumns() !== [$column], sprintf(
                'Unexpected definition of index %s on %s; expected a non-unique index on %s.',
                $index->getName(), $tableName, $column
            ));
            if ($table->hasIndex($old)) {
                $this->addSql(sprintf('ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`', $tableName, $index->getName(), $new));
            }
        }
    }
}
