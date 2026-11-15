<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Move inherited root permissions to first-level media folders';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $nested = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM media_folder_permission p JOIN media_folder d ON d.id = p.folder_id
             WHERE d.parent_id IS NOT NULL AND (p.can_read IS NOT NULL OR p.can_upload IS NOT NULL OR p.can_edit IS NOT NULL OR p.can_delete IS NOT NULL)'
        );
        $this->abortIf($nested > 0, 'Unterordner enthalten eigene Berechtigungen. Diese müssen vor der Umstellung fachlich auf die erste Ordnerebene übertragen werden.');
        $foreign = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM media_folder_permission p JOIN media_folder d ON d.id = p.folder_id JOIN club_function f ON f.id = p.club_function_id WHERE d.club_id <> f.club_id'
        );
        $this->abortIf($foreign > 0, 'Ordner und Funktion einer Berechtigungsregel gehören zu unterschiedlichen Vereinen.');
        $this->addSql('DELETE p FROM media_folder_permission p JOIN media_folder d ON d.id = p.folder_id WHERE d.parent_id IS NOT NULL');
        $this->addSql('UPDATE media_folder_permission p
            JOIN media_folder d ON d.id = p.folder_id
            JOIN club_function f ON f.id = p.club_function_id
            SET p.can_read = COALESCE(p.can_read, f.root_read), p.can_upload = COALESCE(p.can_upload, f.root_upload),
                p.can_edit = COALESCE(p.can_edit, f.root_edit), p.can_delete = COALESCE(p.can_delete, f.root_delete), p.version = p.version + 1
            WHERE d.parent_id IS NULL');
        $this->addSql('INSERT INTO media_folder_permission (club_function_id, folder_id, can_read, can_upload, can_edit, can_delete, version)
            SELECT f.id, d.id, f.root_read, f.root_upload, f.root_edit, f.root_delete, 1
            FROM club_function f JOIN media_folder d ON d.club_id = f.club_id AND d.parent_id IS NULL
            LEFT JOIN media_folder_permission p ON p.club_function_id = f.id AND p.folder_id = d.id
            WHERE p.id IS NULL AND (f.root_read = 1 OR f.root_upload = 1 OR f.root_edit = 1 OR f.root_delete = 1)');
        $this->addSql('UPDATE club_function SET root_read = NULL, root_upload = NULL, root_edit = NULL, root_delete = NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Die Rechte wurden auf einzelne Ordner verteilt und können nicht verlustfrei zusammengeführt werden.');
    }
}
