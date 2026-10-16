<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align equipment index names with Doctrine ORM mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE equipment_category '
            . 'RENAME INDEX idx_eq_cat_club '
            . 'TO IDX_368F9DE761190A32'
        );

        $this->addSql(
            'ALTER TABLE equipment_attribute '
            . 'RENAME INDEX idx_eq_attr_category '
            . 'TO IDX_2847A98212469DE2'
        );

        $this->addSql(
            'ALTER TABLE equipment_item '
            . 'RENAME INDEX idx_eq_item_club '
            . 'TO IDX_259FF41861190A32'
        );

        $this->addSql(
            'ALTER TABLE equipment_item '
            . 'RENAME INDEX idx_eq_item_category '
            . 'TO IDX_259FF41812469DE2'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE equipment_category '
            . 'RENAME INDEX IDX_368F9DE761190A32 '
            . 'TO idx_eq_cat_club'
        );

        $this->addSql(
            'ALTER TABLE equipment_attribute '
            . 'RENAME INDEX IDX_2847A98212469DE2 '
            . 'TO idx_eq_attr_category'
        );

        $this->addSql(
            'ALTER TABLE equipment_item '
            . 'RENAME INDEX IDX_259FF41861190A32 '
            . 'TO idx_eq_item_club'
        );

        $this->addSql(
            'ALTER TABLE equipment_item '
            . 'RENAME INDEX IDX_259FF41812469DE2 '
            . 'TO idx_eq_item_category'
        );
    }
}
