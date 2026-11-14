<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:files:stage-migration', description: 'Kopiert den geprüften Dateibestand nach files/, ohne Quellen zu entfernen oder Zugriffe umzustellen.')]
final class StageFileMigrationCommand extends Command
{
    public function __construct(
        private readonly CheckFileMigrationCommand $check,
        #[Autowire('%kernel.project_dir%')] private readonly string $root,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('courses-only', null, InputOption::VALUE_NONE, 'Nur Kurs- und Terminbilder übernehmen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $coursesOnly = (bool) $input->getOption('courses-only');
        if (file_exists($this->root.'/files/.course-images-active') || is_link($this->root.'/files/.course-images-active')) {
            $output->writeln('<error>Die Kursbilder wurden bereits umgestellt; eine erneute Übernahme ist nicht zulässig.</error>');
            return Command::FAILURE;
        }
        if (!$coursesOnly && (file_exists($this->root.'/files/.website-media-active') || is_link($this->root.'/files/.website-media-active'))) {
            $output->writeln('<error>Die Mediathek wurde bereits umgestellt; eine erneute Übernahme könnte gelöschte Dateien wiederherstellen.</error>');
            return Command::FAILURE;
        }
        if ($this->check->run(new ArrayInput($coursesOnly ? ['--courses-only' => true] : []), $output) !== Command::SUCCESS) {
            $output->writeln('<error>Die Dateivorprüfung ist fehlgeschlagen; es wurde nichts kopiert.</error>');
            return Command::FAILURE;
        }

        $targetRoot = $this->root.'/files';
        $this->ensureDirectory($targetRoot);
        $copied = 0;
        $existing = 0;
        $sources = [$this->root.'/public/images/kurse' => $targetRoot.'/kurse'];
        if (!$coursesOnly) {
            $sources[$this->root.'/var/private/website_media'] = $targetRoot.'/website_media';
        }
        foreach ($sources as $source => $target) {
            $this->assertNoSourceSymlink($source);
            if (!file_exists($source)) {
                continue;
            }
            $this->ensureDirectory($target);
            $before = $this->sourceEntries($source);
            foreach ($before as $relative => $directory) {
                $destination = $target.'/'.$relative;
                if ($directory) {
                    $this->ensureDirectory($destination);
                    continue;
                }
                if ($this->copyChecked($source.'/'.$relative, $destination)) {
                    ++$copied;
                } else {
                    ++$existing;
                }
            }
            if ($before !== $this->sourceEntries($source)) {
                throw new \RuntimeException('Der Quellbestand wurde während des Kopierens geändert. Bitte erneut prüfen.');
            }
        }

        $output->writeln(sprintf('Geprüft kopiert: %d; bereits identisch vorhanden: %d.', $copied, $existing));
        $output->writeln('Quellen und Anwendungspfade bleiben unverändert. Dies ist noch keine Umstellung oder Freigabe.');
        return Command::SUCCESS;
    }

    private function assertNoSourceSymlink(string $source): void
    {
        $path = $this->root;
        foreach (explode('/', substr($source, strlen($this->root) + 1)) as $part) {
            $path .= '/'.$part;
            if (is_link($path)) {
                throw new \RuntimeException('Ein Quellpfad enthält einen symbolischen Link.');
            }
        }
    }

    /**
     * @return array<string, bool> Relative path => is directory.
     */
    private function sourceEntries(string $source): array
    {
        if (is_link($source) || !is_dir($source) || !is_readable($source)) {
            throw new \RuntimeException('Ein Quellverzeichnis ist nicht verfügbar oder ein symbolischer Link.');
        }
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink() || (!$entry->isDir() && !$entry->isFile())) {
                throw new \RuntimeException('Ein Quelleintrag ist kein regulärer Ordner oder keine reguläre Datei.');
            }
            $entries[substr($entry->getPathname(), strlen($source) + 1)] = $entry->isDir();
        }
        ksort($entries);
        return $entries;
    }

    private function ensureDirectory(string $path): void
    {
        if (is_link($path)) {
            throw new \RuntimeException('Ein Zielordner ist ein symbolischer Link.');
        }
        if (!is_dir($path) && !mkdir($path, 0700) && !is_dir($path)) {
            throw new \RuntimeException('Ein Zielordner konnte nicht angelegt werden.');
        }
        $permissions = fileperms($path);
        if ($permissions === false || !is_writable($path) || ($permissions & 0077) !== 0) {
            throw new \RuntimeException('Ein Zielordner ist nicht schreibbar oder nicht privat.');
        }
    }

    private function copyChecked(string $source, string $target): bool
    {
        if (is_link($source) || !is_file($source) || !is_readable($source)) {
            throw new \RuntimeException('Eine Quelldatei ist nicht lesbar oder ein symbolischer Link.');
        }
        $sourceHash = hash_file('sha256', $source);
        if ($sourceHash === false) {
            throw new \RuntimeException('Eine Quelldatei konnte nicht geprüft werden.');
        }
        if (is_link($target)) {
            throw new \RuntimeException('Eine Zieldatei ist ein symbolischer Link.');
        }
        if (file_exists($target)) {
            if (!is_file($target) || hash_file('sha256', $target) !== $sourceHash) {
                throw new \RuntimeException('Eine Zieldatei existiert bereits mit abweichendem Inhalt.');
            }
            if (hash_file('sha256', $source) !== $sourceHash) {
                throw new \RuntimeException('Eine Quelldatei wurde während der Prüfung geändert.');
            }
            return false;
        }

        $input = fopen($source, 'rb');
        if ($input === false) {
            throw new \RuntimeException('Eine Quelldatei konnte nicht geöffnet werden.');
        }
        $output = fopen($target, 'xb');
        if ($output === false) {
            fclose($input);
            throw new \RuntimeException('Eine Zieldatei konnte nicht exklusiv angelegt werden.');
        }
        $complete = false;
        try {
            if (!chmod($target, 0600) || stream_copy_to_stream($input, $output) === false || !fflush($output)) {
                throw new \RuntimeException('Eine Datei konnte nicht vollständig kopiert werden.');
            }
            if (hash_file('sha256', $target) !== $sourceHash || hash_file('sha256', $source) !== $sourceHash) {
                throw new \RuntimeException('Dateiinhalt oder Quelle haben sich beim Kopieren geändert.');
            }
            $complete = true;
        } finally {
            fclose($input);
            fclose($output);
            if (!$complete && !unlink($target)) {
                throw new \RuntimeException('Eine unvollständige Zieldatei konnte nicht entfernt werden.');
            }
        }
        return true;
    }
}
