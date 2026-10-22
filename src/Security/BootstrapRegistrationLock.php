<?php

declare(strict_types=1);

namespace App\Security;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Serializes bootstrap registrations on a single host with a shared filesystem.
 * This is NOT a distributed lock across independent application hosts.
 */
final class BootstrapRegistrationLock
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /** @template T @param callable(): T $callback @return T */
    public function synchronized(callable $callback): mixed
    {
        $directory = $this->projectDir . '/var/lock';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Das Verzeichnis für die Installationssperre konnte nicht angelegt werden.');
        }

        $path = $directory . '/dicoma-bootstrap.lock';
        $handle = @fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Die Installationssperre konnte nicht geöffnet werden.');
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException('Die Installationssperre konnte nicht erworben werden.');
            }
            try {
                return $callback();
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }
}
