<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\Request;

final class EasyAdminRequest
{
    public static function matches(?Request $request): bool
    {
        return (bool) $request?->attributes->get('routeCreatedByEasyAdmin');
    }
}
