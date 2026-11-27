<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

final class SoftwareUpdater
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        private readonly SoftwareBackup $backup,
        private readonly SoftwareReleaseArchive $release,
    ) {}

    /** @return array{backup:string,backupSize:int,version:string} */
    public function install(string $version, string $tag, string $commit): array
    {
        $normalized = GitVersionChecker::normalize($version);
        if ($normalized === null || GitVersionChecker::normalize($tag) !== $normalized
            || !preg_match('/^[a-f0-9]{40}$/D', $commit)) {
            throw new \DomainException('Die Zielversion oder der Release-Commit ist ungültig.');
        }

        $lock = fopen($this->projectDir.'/var/.dicoma-update.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) { fclose($lock); }
            throw new \RuntimeException('Die Update-Sperre ist nicht verfügbar oder ein Update läuft bereits.');
        }

        $filesystem = new Filesystem();
        $backupDir = $this->projectDir.'/var/backups';
        $workDir = $backupDir.'/.dicoma-release-'.bin2hex(random_bytes(8));
        $marker = $this->projectDir.'/var/.dicoma-update-failed';
        $backupResult = null;
        try {
            if (file_exists($marker)) {
                throw new \RuntimeException('Ein vorheriges Update wurde nicht abgeschlossen. Bitte Backup wiederherstellen und die Update-Sperrdatei gemäß Installationsanleitung entfernen.');
            }
            if (is_link($backupDir)) {
                throw new \RuntimeException('Das Backup-Verzeichnis darf kein symbolischer Link sein.');
            }
            $filesystem->mkdir($backupDir, 0700);
            if (!chmod($backupDir, 0700)) {
                throw new \RuntimeException('Das Backup-Verzeichnis konnte nicht geschützt werden.');
            }
            $filesystem->mkdir($workDir, 0700);
            $paths = $this->release->prepare($commit, $workDir);
            if (!in_array('src/Service/SoftwareReleaseArchive.php', $paths, true)) {
                throw new \RuntimeException('Das Release enthält noch nicht den archivbasierten Updater. Bitte eine neuere Version mit dieser Funktion veröffentlichen.');
            }
            $releaseVersion = GitVersionChecker::normalize((string) file_get_contents($workDir.'/release/VERSION'));
            if ($releaseVersion === null || version_compare($releaseVersion, $normalized, '>')) {
                throw new \RuntimeException('Die Versionsdatei im Release-Archiv ist ungültig oder neuer als der Release-Tag.');
            }
            $php = PHP_BINDIR.'/php';
            if (!is_executable($php)) {
                throw new \RuntimeException('Die PHP-CLI ist im PHP-Installationsverzeichnis nicht verfügbar.');
            }
            $this->run(['composer', '--version'], 'Composer prüfen');
            $this->run([$php, 'bin/console', 'about'], 'PHP-CLI prüfen');
            $this->validateDestinations($paths);
            $backupResult = $this->backup->create();
            $filesystem->dumpFile($marker, basename($backupResult['path'])."\n");
            $this->installFiles($workDir.'/release', $paths);

            $steps = [
                [['composer', 'install', '--no-dev', '--no-interaction', '--no-progress', '--prefer-dist', '--optimize-autoloader'], 'Abhängigkeiten installieren', 900],
                [[$php, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction'], 'Datenbank aktualisieren', 600],
                [[$php, 'bin/console', 'app:installation:features', '--apply'], 'Kernfunktionen ergänzen', 300],
                [[$php, 'bin/console', 'cache:clear', '--env=prod', '--no-debug'], 'Produktionscache erneuern', 300],
                [[$php, 'bin/console', 'lint:container', '--env=prod', '--no-debug'], 'Dienstcontainer prüfen', 300],
            ];
            foreach ($steps as [$command, $label, $timeout]) {
                $this->run($command, $label, $timeout);
            }
            $filesystem->dumpFile($this->projectDir.'/VERSION', $normalized."\n");
            $filesystem->remove($marker);

            return ['backup' => $backupResult['path'], 'backupSize' => $backupResult['size'], 'version' => $normalized];
        } catch (\Throwable $exception) {
            if ($backupResult !== null) {
                throw new \RuntimeException(
                    sprintf('Update nicht vollständig installiert. Sicherheits-Backup: %s. %s', basename($backupResult['path']), $exception->getMessage()),
                    0,
                    $exception,
                );
            }
            throw $exception;
        } finally {
            $filesystem->remove($workDir);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @param list<string> $paths */
    private function validateDestinations(array $paths): void
    {
        if (is_link($this->projectDir.'/VERSION')) {
            throw new \RuntimeException('Die Versionsdatei darf kein symbolischer Link sein.');
        }
        foreach ($paths as $path) {
            if ($this->isProtected($path)) { continue; }
            $current = $this->projectDir;
            foreach (explode('/', $path) as $part) {
                $current .= '/'.$part;
                if (is_link($current)) {
                    throw new \RuntimeException('Ein Update-Ziel enthält einen symbolischen Link: '.$path);
                }
            }
        }
    }

    /** @param list<string> $paths */
    private function installFiles(string $stage, array $paths): void
    {
        $filesystem = new Filesystem();
        foreach ($paths as $path) {
            if ($this->isProtected($path)) { continue; }
            $target = $this->projectDir.'/'.$path;
            $filesystem->mkdir(dirname($target));
            $source = fopen($stage.'/'.$path, 'rb');
            if ($source === false) {
                throw new \RuntimeException('Release-Datei konnte nicht gelesen werden: '.$path);
            }
            try {
                $filesystem->dumpFile($target, $source);
                $filesystem->chmod($target, fileperms($stage.'/'.$path) & 0777);
            } finally {
                fclose($source);
            }
        }
    }

    private function isProtected(string $path): bool
    {
        if ($path === 'VERSION' || $path === '.env.local.php'
            || str_starts_with($path, '.env.') && (str_ends_with($path, '.local') || file_exists($this->projectDir.'/'.$path))
            || $path === 'config/secrets/prod/prod.decrypt.private.php') {
            return true;
        }
        if ($path === '.env' && file_exists($this->projectDir.'/.env')) {
            return true;
        }
        foreach (['.git', '.ddev', 'files', 'var', 'vendor', 'public/uploads', 'public/images/kurse'] as $directory) {
            if ($path === $directory || str_starts_with($path, $directory.'/')) {
                return true;
            }
        }
        return false;
    }

    /** @param list<string> $command */
    private function run(array $command, string $label, int $timeout = 120): string
    {
        $process = new Process($command, $this->projectDir, ['APP_ENV' => 'prod', 'APP_DEBUG' => '0']);
        $process->setTimeout($timeout);
        $process->run();
        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            if ($error === '') { $error = trim($process->getOutput()); }
            throw new \RuntimeException($label.' fehlgeschlagen'.($error !== '' ? ': '.mb_substr($error, 0, 1000) : '.'));
        }
        return $process->getOutput();
    }
}
