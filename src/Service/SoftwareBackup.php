<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class SoftwareBackup
{
    private const EXCLUDED_PATHS = ['.git', 'vendor', 'public/bundles', 'var/backups', 'var/cache', 'var/log'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly Connection $connection,
    ) {}

    /** @return array{path:string,size:int} */
    public function create(): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('Die PHP-Erweiterung zip ist für das Sicherheits-Backup erforderlich.');
        }

        $filesystem = new Filesystem();
        $backupDir = $this->projectDir.'/var/backups';
        if (is_link($backupDir)) {
            throw new \RuntimeException('Das Backup-Verzeichnis darf kein symbolischer Link sein.');
        }
        $filesystem->mkdir($backupDir, 0700);
        if (!chmod($backupDir, 0700)) {
            throw new \RuntimeException('Das Backup-Verzeichnis konnte nicht geschützt werden.');
        }
        $timestamp = (new \DateTimeImmutable())->format('Ymd-His');
        $temporaryDir = $backupDir.'/.update-'.$timestamp.'-'.bin2hex(random_bytes(4));
        $archivePath = $backupDir.'/dicoma-'.$timestamp.'-'.bin2hex(random_bytes(4)).'.zip';
        $filesystem->mkdir($temporaryDir, 0700);

        try {
            $databasePath = $temporaryDir.'/database.dump';
            $this->dumpDatabase($databasePath);

            $zip = new \ZipArchive();
            if ($zip->open($archivePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Das Backup-Archiv konnte nicht geöffnet werden.');
            }

            try {
                if (!$zip->addFile($databasePath, '_database/database.dump')) {
                    throw new \RuntimeException('Der Datenbank-Dump konnte nicht in das Backup aufgenommen werden.');
                }
                if (!$zip->addFromString('_backup.json', json_encode([
                    'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'database' => (string) ($this->connection->getParams()['driver'] ?? 'unknown'),
                ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR))) {
                    throw new \RuntimeException('Die Backup-Metadaten konnten nicht gespeichert werden.');
                }

                $entries = new \RecursiveDirectoryIterator($this->projectDir, \FilesystemIterator::SKIP_DOTS);
                $filtered = new \RecursiveCallbackFilterIterator($entries, function (\SplFileInfo $file): bool {
                    $relative = substr($file->getPathname(), strlen(rtrim($this->projectDir, '/')) + 1);
                    if (self::isExcluded($relative)) {
                        return false;
                    }
                    if ($file->isLink()) {
                        throw new \RuntimeException('Symbolischer Link kann nicht automatisch gesichert werden: '.$relative);
                    }
                    return true;
                });
                $iterator = new \RecursiveIteratorIterator($filtered);
                foreach ($iterator as $file) {
                    if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->isLink()) {
                        continue;
                    }
                    $relative = substr($file->getPathname(), strlen(rtrim($this->projectDir, '/')) + 1);
                    if (self::isExcluded($relative)) {
                        continue;
                    }
                    if (!$zip->addFile($file->getPathname(), str_replace('\\', '/', $relative))) {
                        throw new \RuntimeException('Datei konnte nicht gesichert werden: '.$relative);
                    }
                }
            } finally {
                if (!$zip->close()) {
                    throw new \RuntimeException('Das Backup-Archiv konnte nicht vollständig abgeschlossen werden.');
                }
            }

            chmod($archivePath, 0600);
            $size = filesize($archivePath);
            if ($size === false || $size === 0) {
                throw new \RuntimeException('Das Backup-Archiv wurde nicht vollständig geschrieben.');
            }

            return ['path' => $archivePath, 'size' => $size];
        } catch (\Throwable $exception) {
            @unlink($archivePath);
            throw $exception;
        } finally {
            $filesystem->remove($temporaryDir);
        }
    }

    public static function isExcluded(string $relative): bool
    {
        $relative = str_replace('\\', '/', ltrim($relative, '/'));
        foreach (self::EXCLUDED_PATHS as $excluded) {
            if ($relative === $excluded || str_starts_with($relative, $excluded.'/')) {
                return true;
            }
        }

        return false;
    }

    private function dumpDatabase(string $target): void
    {
        $params = $this->connection->getParams();
        $driver = (string) ($params['driver'] ?? '');

        if (str_contains($driver, 'sqlite')) {
            $this->connection->executeStatement('VACUUM INTO '.$this->connection->quote($target));
            if (!is_file($target)) {
                throw new \RuntimeException('Die SQLite-Datenbank konnte nicht konsistent gesichert werden.');
            }
            return;
        }

        if (str_contains($driver, 'mysql')) {
            $command = ['mysqldump', '--single-transaction', '--quick', '--skip-comments', '--result-file='.$target];
            $this->addOption($command, '--host', $params['host'] ?? null);
            $this->addOption($command, '--port', $params['port'] ?? null);
            $this->addOption($command, '--socket', $params['unix_socket'] ?? null);
            $this->addOption($command, '--user', $params['user'] ?? null);
            $command[] = (string) ($params['dbname'] ?? '');
            $this->run($command, ['MYSQL_PWD' => (string) ($params['password'] ?? '')], 'Datenbank-Backup');
            return;
        }

        if (str_contains($driver, 'pgsql') || str_contains($driver, 'postgres')) {
            $command = ['pg_dump', '--format=plain', '--no-owner', '--no-privileges', '--file='.$target];
            $this->addOption($command, '--host', $params['host'] ?? null);
            $this->addOption($command, '--port', $params['port'] ?? null);
            $this->addOption($command, '--username', $params['user'] ?? null);
            $command[] = '--dbname='.(string) ($params['dbname'] ?? '');
            $this->run($command, ['PGPASSWORD' => (string) ($params['password'] ?? '')], 'Datenbank-Backup');
            return;
        }

        throw new \RuntimeException('Für den Datenbanktreiber '.$driver.' ist kein automatisches Backup verfügbar.');
    }

    /** @param list<string> $command */
    private function addOption(array &$command, string $option, mixed $value): void
    {
        if ($value !== null && (string) $value !== '') {
            $command[] = $option.'='.(string) $value;
        }
    }

    /** @param list<string> $command @param array<string,string> $environment */
    private function run(array $command, array $environment, string $label): void
    {
        $process = new Process($command, $this->projectDir, $environment);
        $process->setTimeout(600);
        $process->run();
        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            if ($error === '') {
                $error = 'Das benötigte Kommando ist nicht verfügbar oder konnte nicht ausgeführt werden.';
            }
            throw new \RuntimeException($label.' fehlgeschlagen: '.mb_substr($error, 0, 500));
        }
    }
}
