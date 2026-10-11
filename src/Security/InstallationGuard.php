<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** Disable legacy installation endpoints by default on deployed systems. */
final class InstallationGuard
{
    public function denyUnlessExplicitlyEnabled(): void
    {
        $value = $_SERVER['DICOMA_INSTALL_ENABLED'] ?? $_ENV['DICOMA_INSTALL_ENABLED'] ?? getenv('DICOMA_INSTALL_ENABLED');
        if ($value !== '1') {
            throw new NotFoundHttpException('Installation is disabled.');
        }
    }
}
