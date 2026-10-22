<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** A persistent local completion marker prevents reopening setup after bootstrap. */
final class InstallationGuard
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    public function denyUnlessExplicitlyEnabled(): void
    {
        // Check the persistent marker first, even if an operator enables setup again.
        if (is_file($this->markerPath())) {
            throw new NotFoundHttpException('Installation has already been completed.');
        }

        $completed = $_SERVER['DICOMA_INSTALL_COMPLETED'] ?? $_ENV['DICOMA_INSTALL_COMPLETED'] ?? getenv('DICOMA_INSTALL_COMPLETED');
        if ($completed === '1') {
            throw new NotFoundHttpException('Installation has already been completed.');
        }

        $enabled = $_SERVER['DICOMA_INSTALL_ENABLED'] ?? $_ENV['DICOMA_INSTALL_ENABLED'] ?? getenv('DICOMA_INSTALL_ENABLED');
        if ($enabled !== '1') {
            throw new NotFoundHttpException('Installation is disabled.');
        }
    }

    /** Call only after the first administrator and member have been committed. */
    public function markCompleted(): void
    {
        $directory = dirname($this->markerPath());
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Installationsstatus konnte nicht gespeichert werden.');
        }

        // Exclusive create: never overwrite an existing completion marker.
        $handle = @fopen($this->markerPath(), 'x');
        if ($handle === false) {
            throw new RuntimeException('Installationsstatus existiert bereits oder kann nicht angelegt werden.');
        }
        try {
            @chmod($this->markerPath(), 0600);
            $written = fwrite($handle, "completed\n");
            if ($written === false || $written !== strlen("completed\n") || !fflush($handle)) {
                throw new RuntimeException('Installationsstatus konnte nicht vollständig geschrieben werden.');
            }
        } finally {
            fclose($handle);
        }
    }

    private function markerPath(): string
    {
        return $this->projectDir . '/var/install-completed.marker';
    }
}
