<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class InstallationMigrator
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) {}

    public function migrate(): void
    {
        foreach ([
            [PHP_BINARY, 'bin/console', 'doctrine:database:create', '--if-not-exists', '--no-interaction'],
            [PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'],
        ] as $command) {
            $process = new Process($command, $this->projectDir, ['APP_ENV' => 'prod'], null, 180);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException('Die Datenbankeinrichtung ist fehlgeschlagen. Prüfen Sie Zugangsdaten, Rechte und die Serverprotokolle.');
            }
        }
    }
}
