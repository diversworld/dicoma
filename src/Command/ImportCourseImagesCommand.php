<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\{Club, ClubEventDocument, Courses, MediaFile, MediaFolder, Schedule};
use App\Service\{ImageUploadValidator, MembershipRoleContinuity, SingleClubMode};
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:files:import-course-images', description: 'Übernimmt den bisherigen Kursbildbestand in die gemeinsame Dateiverwaltung.')]
final class ImportCourseImagesCommand extends Command
{
    private const FOLDER = 'Kursbilder (Altbestand)';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SingleClubMode $singleClub,
        private readonly MembershipRoleContinuity $continuity,
        #[Autowire('%kernel.project_dir%')] private readonly string $root,
        #[Autowire('%env(bool:CLUB_FUNCTION_MEMBERSHIP_ENABLED)%')] private readonly bool $enabled,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Prüft Bestand und Referenzen ohne Änderungen.');
        $this->addOption('confirm', null, InputOption::VALUE_NONE, 'Bestätigt Backup, Restore und eine durchgehende Schreibpause.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        if ($dryRun === (bool) $input->getOption('confirm')) {
            $output->writeln('<error>Genau eine der Optionen --dry-run oder --confirm ist erforderlich.</error>');
            return Command::FAILURE;
        }
        if ($this->enabled) {
            $output->writeln('<error>Die Übernahme muss vor Aktivierung des Funktionsmodells stattfinden.</error>');
            return Command::FAILURE;
        }
        $connection = $this->em->getConnection();
        if ($connection->isTransactionActive() || !$connection->isAutoCommit()) {
            throw new \LogicException('Die Bildübernahme benötigt eine eigene Transaktion.');
        }
        if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            foreach (['club', 'courses', 'schedule', 'media_folder', 'media_file', 'club_event_document'] as $table) {
                $engine = $connection->fetchOne('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
                if (!is_string($engine) || strcasecmp($engine, 'InnoDB') !== 0) {
                    throw new \RuntimeException(sprintf('Die Bildübernahme benötigt InnoDB für Tabelle %s.', $table));
                }
            }
        }
        $club = $this->singleClub->club();
        if ($dryRun) {
            [$entries, $references] = $this->prepare($club);
            $this->report($output, $entries, $references);
            $output->writeln('Keine Daten oder Dateien geändert. Für die Übernahme sind Backup, Restore und Schreibpause erforderlich.');
            return Command::SUCCESS;
        }

        $completed = false;
        try {
            [$entries, $references] = $connection->transactional(function () use ($club, $connection): array {
                $this->continuity->lockClub($club);
                $clubId = $club->getId();
                if ($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                    $connection->fetchFirstColumn("SELECT id FROM courses WHERE image IS NOT NULL AND image <> '' ORDER BY id FOR UPDATE");
                    $connection->fetchFirstColumn("SELECT id FROM schedule WHERE image IS NOT NULL AND image <> '' ORDER BY id FOR UPDATE");
                }
                $this->em->clear();
                $club = $this->em->find(Club::class, $clubId) ?? throw new \LogicException('Zielverein nicht vorhanden.');
                [$entries, $references] = $this->prepare($club);
                $root = new MediaFolder($club, self::FOLDER);
                $this->em->persist($root);
                $folders = ['' => $root];
                $images = [];
                foreach ($entries as $relative => $entry) {
                    $parentPath = dirname($relative);
                    $parent = $folders[$parentPath === '.' ? '' : $parentPath] ?? throw new \LogicException('Übergeordneter Ordner fehlt.');
                    if ($entry['directory']) {
                        $folder = new MediaFolder($club, basename($relative), $parent);
                        $this->em->persist($folder);
                        $folders[$relative] = $folder;
                        continue;
                    }
                    $name = $this->storageName($club, $relative, $entry);
                    $this->assertStorageAvailable($name, $entry['hash']);
                    $this->copyImage($relative, $name, $entry['hash']);
                    $image = new MediaFile($club, $name, basename($relative), $entry['mime'], $entry['size'], '');
                    $image->setFolder($parent);
                    $this->em->persist($image);
                    $images[$relative] = $image;
                }
                foreach ($references as [$entity, $path]) {
                    $entity->setMediaImage($images[$path]);
                }
                if ($entries !== $this->inventory()) {
                    throw new \RuntimeException('Der Kursbildbestand wurde während der Übernahme geändert.');
                }
                $this->em->flush();
                return [$entries, $references];
            });
            $completed = true;
        } finally {
            if (!$completed) {
                $this->em->clear();
                $output->writeln('<error>Übernahme abgebrochen. Bereits kopierte, nicht zugeordnete Dateien bleiben geschützt; ein erneuter Versuch prüft sie auf identischen Inhalt.</error>');
            }
        }
        $this->report($output, $entries, $references);
        $output->writeln('Die bisherigen Dateien und Bildpfade bleiben unverändert. Ordnerrechte und den Bestand vor Aktivierung prüfen.');
        return Command::SUCCESS;
    }

    /** @return array{array<string, array{directory: bool, hash: string, mime: string, size: int}>, list<array{Courses|Schedule, string}>} */
    private function prepare(Club $club): array
    {
        $entries = $this->inventory();
        if ($this->em->getRepository(MediaFolder::class)->count(['club' => $club, 'parent' => null, 'name' => self::FOLDER]) !== 0) {
            throw new \DomainException('Der Zielordner existiert bereits. Eine erneute Übernahme ist nicht zulässig.');
        }
        $references = [];
        foreach ([Courses::class, Schedule::class] as $class) {
            $entities = $this->em->getRepository($class)->createQueryBuilder('entity')
                ->where("entity.image IS NOT NULL AND entity.image <> ''")->getQuery()->getResult();
            foreach ($entities as $entity) {
                $path = $entity->getImage();
                $owner = $entity instanceof Schedule ? $entity->getCourses()?->getClub() : $entity->getClub();
                if ($owner?->getId() !== $club->getId() || $entity->getMediaImage() !== null
                    || !is_string($path) || !isset($entries[$path]) || $entries[$path]['directory']) {
                    throw new \DomainException(sprintf('Ungültige, fremde, fehlende oder bereits übernommene Bildreferenz bei %s #%d.', $class === Courses::class ? 'Kurs' : 'Termin', $entity->getId()));
                }
                $references[] = [$entity, $path];
            }
        }
        $names = [];
        foreach ($entries as $relative => $entry) {
            if (!$entry['directory']) {
                $name = $this->storageName($club, $relative, $entry);
                if (isset($names[$name])) {
                    throw new \RuntimeException('Mehrere Kursbilder würden denselben Zielspeichernamen erhalten.');
                }
                $names[$name] = true;
                $this->assertStorageAvailable($name, $entry['hash']);
            }
        }
        return [$entries, $references];
    }

    /** @return array<string, array{directory: bool, hash: string, mime: string, size: int}> */
    private function inventory(): array
    {
        $files = $this->root.'/files';
        foreach ([$files, $files.'/kurse', $files.'/website_media'] as $dir) {
            if (is_link($dir) || !is_dir($dir) || !is_readable($dir)) {
                throw new \RuntimeException('Eine private Dateiablage fehlt, ist nicht lesbar oder ein symbolischer Link.');
            }
            $permissions = fileperms($files.'/website_media');
            if ($permissions === false || ($permissions & 0077) !== 0 || !is_writable($files.'/website_media')) {
                throw new \RuntimeException('Der gemeinsame Dateispeicher ist nicht privat oder nicht schreibbar.');
            }
        }
        $storageOwner = fileowner($files.'/website_media');
        if (!function_exists('posix_geteuid') || $storageOwner === false || posix_geteuid() !== $storageOwner) {
            throw new \RuntimeException('Die Bildübernahme muss als Eigentümer des gemeinsamen Dateispeichers ausgeführt werden; dieser muss auch der Webprozess sein.');
        }
        foreach (['.course-images-active', '.website-media-active'] as $marker) {
            if (is_link($files.'/'.$marker) || !is_file($files.'/'.$marker)) {
                throw new \RuntimeException('Die private Dateiablage muss vor der Bildübernahme aktiviert sein.');
            }
        }
        $source = $files.'/kurse';
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            if ($item->isLink() || (!$item->isFile() && !$item->isDir())) {
                throw new \RuntimeException('Der Kursbildbestand enthält einen symbolischen Link oder ungültigen Eintrag.');
            }
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $name = $item->getFilename();
            $directory = $item->isDir();
            if (!mb_check_encoding($name, 'UTF-8') || trim($name) !== $name || $name === '.' || $name === '..'
                || mb_strlen($name) > ($directory ? 120 : 255) || preg_match('/[\/\\\\\x00-\x1f\x7f]/u', $name) !== 0) {
                throw new \RuntimeException('Ein Kursbildname oder Ordnername kann nicht unverändert übernommen werden.');
            }
            $hash = ''; $mime = ''; $size = 0;
            if (!$directory) {
                $size = $item->getSize();
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($item->getPathname());
                if (!$item->isReadable() || $size < 1 || $size > ImageUploadValidator::MAX_BYTES || !isset(ImageUploadValidator::EXTENSIONS[$mime])) {
                    throw new \RuntimeException('Ein Kursbild ist nicht lesbar, zu groß oder kein unterstütztes Bild.');
                }
                ImageUploadValidator::validate($item->getPathname(), $mime);
                $hash = hash_file('sha256', $item->getPathname());
                if ($hash === false) {
                    throw new \RuntimeException('Ein Kursbild konnte nicht geprüft werden.');
                }
            }
            $entries[$relative] = ['directory' => $directory, 'hash' => $hash, 'mime' => $mime, 'size' => $size];
        }
        ksort($entries);
        return $entries;
    }

    /** @param array{directory: bool, hash: string, mime: string, size: int} $entry */
    private function storageName(Club $club, string $relative, array $entry): string
    {
        return substr(hash('sha256', "course-image-import\0".$club->getId()."\0".$relative."\0".$entry['hash']), 0, 40)
            .'.'.ImageUploadValidator::EXTENSIONS[$entry['mime']];
    }

    private function assertStorageAvailable(string $name, string $hash): void
    {
        if ($this->em->getRepository(MediaFile::class)->count(['storageName' => $name]) !== 0
            || $this->em->getRepository(ClubEventDocument::class)->count(['storageName' => $name]) !== 0) {
            throw new \RuntimeException('Ein Zielspeichername wird bereits verwendet.');
        }
        $path = $this->root.'/files/website_media/'.$name;
        if (is_link($path) || (file_exists($path) && (!is_file($path) || !is_readable($path) || hash_file('sha256', $path) !== $hash))) {
            throw new \RuntimeException('Eine Zieldatei existiert mit abweichendem Inhalt oder ist ein symbolischer Link.');
        }
        if (is_file($path)) {
            $permissions = fileperms($path);
            if (fileowner($path) !== posix_geteuid() || $permissions === false || ($permissions & 0077) !== 0) {
                throw new \RuntimeException('Eine bereits vorhandene Zieldatei hat einen falschen Eigentümer oder ist nicht privat.');
            }
        }
    }

    private function copyImage(string $relative, string $name, string $hash): void
    {
        $source = $this->root.'/files/kurse/'.$relative;
        $target = $this->root.'/files/website_media/'.$name;
        if (is_link($source) || !is_file($source) || hash_file('sha256', $source) !== $hash) {
            throw new \RuntimeException('Eine Kursbildquelle wurde geändert.');
        }
        if (is_link($target)) {
            throw new \RuntimeException('Eine Kursbild-Zieldatei ist ein symbolischer Link.');
        }
        if (is_file($target)) {
            if (hash_file('sha256', $target) !== $hash) {
                throw new \RuntimeException('Eine Kursbild-Zieldatei wurde geändert.');
            }
            return;
        }
        $temp = $target.'.'.bin2hex(random_bytes(8)).'.tmp';
        $input = fopen($source, 'rb');
        if ($input === false) {
            throw new \RuntimeException('Eine Kursbildquelle konnte nicht geöffnet werden.');
        }
        $output = fopen($temp, 'xb');
        if ($output === false) {
            fclose($input);
            throw new \RuntimeException('Eine temporäre Zieldatei konnte nicht exklusiv angelegt werden.');
        }
        try {
            if (!chmod($temp, 0600) || stream_copy_to_stream($input, $output) === false || !fflush($output)
                || hash_file('sha256', $temp) !== $hash || hash_file('sha256', $source) !== $hash) {
                throw new \RuntimeException('Ein Kursbild wurde beim Kopieren geändert oder nicht vollständig kopiert.');
            }
            if (!link($temp, $target)) {
                throw new \RuntimeException('Eine Kursbild-Zieldatei konnte nicht exklusiv angelegt werden.');
            }
        } finally {
            fclose($input);
            fclose($output);
            if (!unlink($temp)) {
                throw new \RuntimeException('Eine temporäre Zieldatei konnte nicht entfernt werden.');
            }
        }
    }

    private function report(OutputInterface $output, array $entries, array $references): void
    {
        $files = count(array_filter($entries, static fn (array $entry): bool => !$entry['directory']));
        $output->writeln(sprintf('Kursbilddateien: %d; Ordner einschließlich leerer Ordner: %d; Referenzen: %d.', $files, count($entries) - $files, count($references)));
    }
}
