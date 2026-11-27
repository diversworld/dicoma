<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;
use Doctrine\DBAL\Connection;

final class InstallationMigrator
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Connection $connection,
    ) {}

    public function migrate(): void
    {
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            throw new \RuntimeException('Die Verbindung zur angegebenen Datenbank ist fehlgeschlagen. Prüfen Sie Host, Datenbankname, Benutzer und Passwort.');
        }

        $process = new Process(
            [PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction', '--allow-no-migration'],
            $this->projectDir,
            ['APP_ENV' => 'prod', 'APP_DEBUG' => '0'],
            null,
            600,
        );
        $process->run();
        if (!$process->isSuccessful()) {
            $output = trim($process->getErrorOutput()."\n".$process->getOutput());
            $summary = $this->safeSummary($output);
            throw new \RuntimeException('Die Datenbankmigration ist fehlgeschlagen.'.$summary);
        }
    }

    private function safeSummary(string $output): string
    {
        if ($output === '') {
            return ' Prüfen Sie die Serverprotokolle.';
        }
        $output = preg_replace('#(mysql|pgsql|postgresql)://[^\\s@]+@#i', '$1://***@', $output) ?? $output;
        $lines = preg_split('/\R/', trim($output)) ?: [];
        $lastLine = end($lines);
        return ' Ursache: '.trim(is_string($lastLine) ? $lastLine : 'Unbekannter Migrationsfehler');
    }
}
