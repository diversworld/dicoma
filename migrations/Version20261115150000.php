<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Preserve existing file access when introducing file module permissions';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform, 'Only MySQL/MariaDB is supported.');
        $this->addSql(<<<'SQL'
            UPDATE club_function f
            LEFT JOIN (
                SELECT p.club_function_id,
                    MAX(CASE WHEN p.can_read = 1 THEN 1 ELSE 0 END) AS may_read,
                    MAX(CASE WHEN p.can_upload = 1 OR p.can_edit = 1 THEN 1 ELSE 0 END) AS may_create,
                    MAX(CASE WHEN p.can_edit = 1 THEN 1 ELSE 0 END) AS may_edit,
                    MAX(CASE WHEN p.can_delete = 1 THEN 1 ELSE 0 END) AS may_delete
                FROM media_folder_permission p
                INNER JOIN media_folder d ON d.id = p.folder_id AND d.parent_id IS NULL
                GROUP BY p.club_function_id
            ) rights_by_function ON rights_by_function.club_function_id = f.id
            SET f.module_permissions = JSON_MERGE_PRESERVE(
                CASE WHEN COALESCE(rights_by_function.may_read, 0) = 1 OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_ADMIN')) OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_BOARD')) THEN JSON_ARRAY('files:read') ELSE JSON_ARRAY() END,
                CASE WHEN COALESCE(rights_by_function.may_create, 0) = 1 OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_ADMIN')) OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_BOARD')) THEN JSON_ARRAY('files:create') ELSE JSON_ARRAY() END,
                CASE WHEN COALESCE(rights_by_function.may_edit, 0) = 1 OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_ADMIN')) OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_BOARD')) THEN JSON_ARRAY('files:edit') ELSE JSON_ARRAY() END,
                CASE WHEN COALESCE(rights_by_function.may_delete, 0) = 1 OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_ADMIN')) OR JSON_CONTAINS(f.permissions, JSON_QUOTE('ROLE_BOARD')) THEN JSON_ARRAY('files:delete') ELSE JSON_ARRAY() END
            )
            WHERE f.module_permissions IS NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Bestehende Dateiberechtigungen dürfen nicht verloren gehen.');
    }
}
