<?php

declare(strict_types=1);

namespace App\Entity;

enum FileAction: string
{
    case READ = 'read';
    case UPLOAD = 'upload';
    case EDIT = 'edit';
    case DELETE = 'delete';
}
