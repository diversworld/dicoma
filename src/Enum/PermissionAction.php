<?php

declare(strict_types=1);

namespace App\Enum;

enum PermissionAction: string
{
    case READ = 'read';
    case CREATE = 'create';
    case EDIT = 'edit';
    case DELETE = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::READ => 'Lesen',
            self::CREATE => 'Anlegen',
            self::EDIT => 'Bearbeiten',
            self::DELETE => 'Löschen',
        };
    }
}
