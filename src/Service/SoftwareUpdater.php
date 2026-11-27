<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class SoftwareUpdater
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire(env: 'DICOMA_UPDATE_REPOSITORY')] private readonly string $repository,
        private readonly SoftwareBackup $backup,
    ) {}

    /** @return array{backup:string,backupSize:int,version:string} */
    public function install(string $version): array
    {
        $normalized = GitVersionChecker::normalize($version);
        if ($normalized === null) {
            throw new \DomainException('Die Zielversion ist ungültig.');
        }

        $lock = fopen($this->projectDir.'/var/.dicoma-update.lock', 'c');
        if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new \RuntimeException('Es läuft bereits ein Software-Update.');
        }

        $backupResult = null;
        try {
            $origin = trim($this->run(['git', 'config', '--get', 'remote.origin.url'], 'Repository prüfen'));
            if (!self::repositoryMatches($origin, $this->repository)) {
                throw new \RuntimeException('Der lokale Git-Ursprung stimmt nicht mit DICOMA_UPDATE_REPOSITORY überein.');
            }
            if (trim($this->run(['git', 'status', '--porcelain', '--untracked-files=all'], 'Arbeitsverzeichnis prüfen')) !== '') {
                throw new \RuntimeException('Das Arbeitsverzeichnis enthält Änderungen. Diese müssen vor dem Update committed oder gesichert werden.');
            }

            $this->run(['git', 'fetch', 'origin', '--tags', '--force'], 'Versions-Tag abrufen', 180);
            $tag = $this->resolveTag($normalized);
            $tagVersion = GitVersionChecker::normalize($this->run(
                ['git', 'show', $tag.':VERSION'],
                'Versionsdatei des Release-Tags prüfen',
            ));
            if ($tagVersion !== $normalized) {
                throw new \RuntimeException(sprintf(
                    'Der Release-Tag %s enthält eine widersprüchliche Versionsdatei (%s). Das Update wurde nicht gestartet.',
                    $tag,
                    $tagVersion ?? 'ungültig oder fehlend',
                ));
            }
            $this->run(['composer', '--version'], 'Composer prüfen');
            $this->run([PHP_BINARY, 'bin/console', 'about'], 'PHP-Laufzeit prüfen');

            $backupResult = $this->backup->create();

            $steps = [
                [['git', 'checkout', $tag], 'Version '.$tag.' bereitstellen', 120],
                [['composer', 'install', '--no-dev', '--no-interaction', '--no-progress', '--prefer-dist', '--optimize-autoloader'], 'Abhängigkeiten installieren', 900],
                [[PHP_BINARY, 'bin/console', 'doctrine:migrations:migrate', '--no-interaction'], 'Datenbank aktualisieren', 600],
                [[PHP_BINARY, 'bin/console', 'app:installation:features', '--apply'], 'Kernfunktionen ergänzen', 300],
                [[PHP_BINARY, 'bin/console', 'cache:clear', '--env=prod', '--no-debug'], 'Produktionscache erneuern', 300],
                [[PHP_BINARY, 'bin/console', 'lint:container', '--env=prod', '--no-debug'], 'Dienstcontainer prüfen', 300],
            ];
            foreach ($steps as [$command, $label, $timeout]) {
                $this->run($command, $label, $timeout);
            }

            return [
                'backup' => $backupResult['path'],
                'backupSize' => $backupResult['size'],
                'version' => $normalized,
            ];
        } catch (\Throwable $exception) {
            if ($backupResult !== null) {
                throw new \RuntimeException(
                    sprintf(
                        'Update nicht vollständig installiert. Sicherheits-Backup: %s. %s',
                        basename($backupResult['path']),
                        $exception->getMessage(),
                    ),
                    0,
                    $exception,
                );
            }
            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function repositoryMatches(string $origin, string $repository): bool
    {
        if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
            return false;
        }
        $origin = rtrim($origin, '/');
        if (str_ends_with($origin, '.git')) {
            $origin = substr($origin, 0, -4);
        }

        return preg_match('#(?:https://github\.com/|git@github\.com:|ssh://git@github\.com/)'.preg_quote($repository, '#').'$#i', $origin) === 1;
    }

    private function resolveTag(string $version): string
    {
        foreach (array_values(array_unique([$version, 'v'.$version])) as $candidate) {
            $process = new Process(['git', 'rev-parse', '--verify', '--quiet', 'refs/tags/'.$candidate], $this->projectDir);
            $process->run();
            if ($process->isSuccessful()) {
                return $candidate;
            }
        }
        throw new \RuntimeException('Der Versions-Tag '.$version.' wurde im lokalen Repository nicht gefunden.');
    }

    /** @param list<string> $command */
    private function run(array $command, string $label, int $timeout = 120): string
    {
        $process = new Process($command, $this->projectDir, [
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
        ]);
        $process->setTimeout($timeout);
        $process->run();
        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput());
            if ($error === '') {
                $error = trim($process->getOutput());
            }
            throw new \RuntimeException($label.' fehlgeschlagen'.($error !== '' ? ': '.mb_substr($error, 0, 1000) : '.'));
        }

        return $process->getOutput();
    }
}
