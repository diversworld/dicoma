<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013235000 extends AbstractMigration
{
    private const RENAMES = [
        ['regulator_model', 'idx_reg_model_club', 'IDX_E9E916E761190A32'],
        ['regulator_model', 'idx_reg_model_manufacturer', 'IDX_E9E916E7A23B42D'],
        ['equipment_regulator_details', 'uniq_equipment_regulator_item', 'UNIQ_A629797864149DB1'],
        ['equipment_reservation', 'idx_eq_res_member', 'IDX_238ABD727597D3FE'],
        ['equipment_size', 'idx_equipment_size_club', 'IDX_CD44F56C61190A32'],
        ['equipment_size', 'idx_equipment_size_category', 'IDX_CD44F56C12469DE2'],
        ['equipment_item', 'uniq_equipment_item_tank', 'UNIQ_259FF418C6525B5'],
        ['equipment_manufacturer', 'idx_equipment_manufacturer_club', 'IDX_9861114361190A32'],
        ['equipment_sub_type', 'idx_equipment_sub_type_club', 'IDX_9B8B4CCE61190A32'],
        ['equipment_sub_type', 'idx_equipment_sub_type_category', 'IDX_9B8B4CCE12469DE2'],
        ['tank', 'idx_tank_club', 'IDX_AD12B73961190A32'],
        ['regulator', 'idx_reg_manufacturer', 'IDX_656712E5A23B42D'],
        ['regulator', 'idx_reg_first_model', 'IDX_656712E59BC049E4'],
        ['regulator', 'idx_reg_second_model', 'IDX_656712E5D60E0D53'],
        ['regulator', 'idx_reg_second_optional', 'IDX_656712E527D80EF5'],
    ];

    public function getDescription(): string
    {
        return 'Align 15 equipment index names with Doctrine mapping; retain unique indexes';
    }

    public function up(Schema $schema): void
    {
        $this->renameIndexes(false);
    }

    public function down(Schema $schema): void
    {
        $this->renameIndexes(true);
    }

    private function renameIndexes(bool $reverse): void
    {
        $rows = $reverse ? array_reverse(self::RENAMES) : self::RENAMES;
        foreach ($rows as [$table, $old, $new]) {
            if ($reverse) {
                [$old, $new] = [$new, $old];
            }
            $this->addSql(sprintf('ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`', $table, $old, $new));
        }
    }
}
