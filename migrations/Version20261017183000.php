<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261017183000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 29: Doctrine-Datumskommentar und Indexnamen abgleichen (Groß-/Kleinschreibung robust)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Nur MySQL/MariaDB unterstützt.');

        $comment = $this->connection->fetchOne(
            "SELECT COLUMN_COMMENT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'member_tank_check_result' AND COLUMN_NAME = 'inspected_at'"
        );
        $this->abortIf($comment === false, 'Spalte member_tank_check_result.inspected_at fehlt.');
        if ($comment !== '(DC2Type:date_immutable)') {
            $this->addSql("ALTER TABLE member_tank_check_result CHANGE inspected_at inspected_at DATE NOT NULL COMMENT '(DC2Type:date_immutable)'");
        }

        $this->renameIndexIfNeeded('member_tank_check_result', 'uniq_tuv_result_booking', 'UNIQ_5F3E51EC3301C60');
        $this->renameIndexIfNeeded('tank_inspection_result', 'idx_tank_inspection_tank', 'IDX_9EC2776B15C652B5');
    }

    private function renameIndexIfNeeded(string $table, string $old, string $new): void
    {
        // MySQL/MariaDB können Indexnamen in information_schema in anderer
        // Groß-/Kleinschreibung liefern. PHPs in_array(..., true) tat dies nicht.
        $names = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );
        $oldActual = null;
        $newActual = null;
        foreach ($names as $name) {
            if (strcasecmp((string) $name, $old) === 0) {
                $oldActual = (string) $name;
            }
            if (strcasecmp((string) $name, $new) === 0) {
                $newActual = (string) $name;
            }
        }
        $this->abortIf($oldActual !== null && $newActual !== null, "Beide Indexnamen sind auf {$table} vorhanden. Bitte manuell prüfen.");
        $this->abortIf($oldActual === null && $newActual === null, "Keiner der erwarteten Indizes ist auf {$table} vorhanden. Bitte SHOW INDEX FROM {$table} prüfen.");
        if ($oldActual !== null) {
            $this->addSql(sprintf('ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`', $table, $oldActual, $new));
        }
    }

    public function down(Schema $schema): void
    {
        // Keine automatische Rücknahme der Metadaten- und Indexnamen-Angleichung.
    }
}
