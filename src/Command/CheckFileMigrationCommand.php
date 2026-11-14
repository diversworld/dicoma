<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:files:check-migration', description: 'Prüft Dateien und Referenzen vor der Zusammenführung, ohne Daten zu ändern.')]
final class CheckFileMigrationCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%kernel.project_dir%')] private readonly string $root,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('courses-only', null, InputOption::VALUE_NONE, 'Nur Kurs- und Terminbilder prüfen.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $issues = [];
        $coursesOnly = (bool) $input->getOption('courses-only');
        $courseFiles = $this->inventory($this->root.'/public/images/kurse', 'Kursbilder', $issues);
        $mediaFiles = $coursesOnly ? [] : $this->inventory($this->root.'/var/private/website_media', 'Mediathek', $issues);
        $courseReferences = [];
        $mediaReferences = [];

        foreach (['courses' => 'Kurs', 'schedule' => 'Termin'] as $table => $label) {
            $rows = $this->connection->executeQuery("SELECT id, image FROM $table WHERE image IS NOT NULL AND image <> ''");
            while ($row = $rows->fetchAssociative()) {
                $name = $row['image'];
                if (!is_string($name) || !$this->isSafeRelativePath($name)) {
                    $issues[] = sprintf('%s-ID %d: Ungültiger Bildpfad.', $label, (int) $row['id']);
                    continue;
                }
                $courseReferences[$name] = ($courseReferences[$name] ?? 0) + 1;
                if (!isset($courseFiles[$name])) {
                    $issues[] = sprintf('%s-ID %d: Referenziertes Bild fehlt oder ist nicht regulär.', $label, (int) $row['id']);
                }
            }
        }

        foreach ($coursesOnly ? [] : ['media_file' => 'Mediathek', 'club_event_document' => 'Veranstaltungsdokument'] as $table => $label) {
            $rows = $this->connection->executeQuery("SELECT id, storage_name, size FROM $table");
            while ($row = $rows->fetchAssociative()) {
                $name = $row['storage_name'];
                if (!is_string($name) || !preg_match('/^[a-f0-9]{40}\.(jpg|png|webp|pdf|docx|xlsx|pptx)$/D', $name)) {
                    $issues[] = sprintf('%s-ID %d: Ungültiger Speichername.', $label, (int) $row['id']);
                    continue;
                }
                $mediaReferences[$name] = ($mediaReferences[$name] ?? 0) + 1;
                if (!isset($mediaFiles[$name])) {
                    $issues[] = sprintf('%s-ID %d: Datei fehlt oder ist nicht regulär.', $label, (int) $row['id']);
                } elseif ($mediaFiles[$name] !== (int) $row['size']) {
                    $issues[] = sprintf('%s-ID %d: Gespeicherte Dateigröße stimmt nicht überein.', $label, (int) $row['id']);
                }
            }
        }

        foreach ($mediaReferences as $count) {
            if ($count > 1) {
                $issues[] = 'Mehrere Mediathek- oder Veranstaltungsdatensätze verweisen auf dieselbe Datei.';
            }
        }
        $target = $this->root.'/files';
        if (is_link($target) || (file_exists($target) && !is_dir($target))) {
            $issues[] = 'Das Ziel files ist ein symbolischer Link oder kein Verzeichnis.';
        }

        $output->writeln(sprintf('Kursbilddateien: %d', count($courseFiles)));
        $output->writeln(sprintf('Kursbildreferenzen: %d', array_sum($courseReferences)));
        $output->writeln(sprintf('Mehrfach referenzierte Kursbilder: %d', count(array_filter($courseReferences, static fn (int $count): bool => $count > 1))));
        $output->writeln(sprintf('Nicht referenzierte Kursbilddateien: %d', count(array_diff_key($courseFiles, $courseReferences))));
        if (!$coursesOnly) {
            $output->writeln(sprintf('Mediatheksdateien einschließlich Veranstaltungsdokumente: %d', count($mediaFiles)));
            $output->writeln(sprintf('Mediathek- und Dokumentreferenzen: %d', array_sum($mediaReferences)));
            $output->writeln(sprintf('Nicht referenzierte Mediatheksdateien: %d', count(array_diff_key($mediaFiles, $mediaReferences))));
        }
        foreach ($issues as $issue) {
            $output->writeln('<error>'.$issue.'</error>');
        }
        $output->writeln('Keine Dateien oder Daten geändert. Paralleländerungen, Dateiinhalt und Wiederherstellung sind gesondert zu prüfen.');
        return $issues === [] ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<string> $issues
     * @return array<string, int>
     */
    private function inventory(string $path, string $label, array &$issues): array
    {
        if (is_link($path)) {
            $issues[] = $label.': Quellverzeichnis ist ein symbolischer Link.';
            return [];
        }
        if (!file_exists($path)) {
            return [];
        }
        if (!is_dir($path) || !is_readable($path)) {
            throw new \RuntimeException($label.': Quellverzeichnis ist nicht lesbar.');
        }
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            if ($entry->isLink()) {
                $issues[] = $label.': Symbolischer Link im Quellverzeichnis.';
            } elseif ($entry->isFile()) {
                if (!$entry->isReadable()) {
                    $issues[] = $label.': Nicht lesbare Datei im Quellverzeichnis.';
                    continue;
                }
                $relative = substr($entry->getPathname(), strlen($path) + 1);
                $files[$relative] = $entry->getSize();
            } elseif (!$entry->isDir()) {
                $issues[] = $label.': Nicht regulärer Eintrag im Quellverzeichnis.';
            }
        }
        return $files;
    }

    private function isSafeRelativePath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0")) {
            return false;
        }
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return true;
    }
}
