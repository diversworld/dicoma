<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\CheckFileMigrationCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class CheckFileMigrationCommandTest extends TestCase
{
    private Connection $connection;
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/dicoma-file-audit-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/public/images/kurse', 0700, true);
        mkdir($this->root.'/var/private/website_media', 0700, true);
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement('CREATE TABLE courses (id INTEGER PRIMARY KEY, image TEXT)');
        $this->connection->executeStatement('CREATE TABLE schedule (id INTEGER PRIMARY KEY, image TEXT)');
        $this->connection->executeStatement('CREATE TABLE media_file (id INTEGER PRIMARY KEY, storage_name TEXT, size INTEGER)');
        $this->connection->executeStatement('CREATE TABLE club_event_document (id INTEGER PRIMARY KEY, storage_name TEXT, size INTEGER)');
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        (new Filesystem())->remove($this->root);
    }

    public function testValidInventoryIncludesEventDocumentsAndPreservesUnreferencedFiles(): void
    {
        $course = $this->root.'/public/images/kurse';
        $media = $this->root.'/var/private/website_media';
        $mediaName = str_repeat('a', 40).'.pdf';
        $eventName = str_repeat('b', 40).'.jpg';
        file_put_contents($course.'/image.jpg', 'image');
        file_put_contents($course.'/unused.png', 'unused');
        file_put_contents($media.'/'.$mediaName, 'media');
        file_put_contents($media.'/'.$eventName, 'event');
        file_put_contents($media.'/orphan', 'orphan');
        $this->connection->insert('courses', ['id' => 1, 'image' => 'image.jpg']);
        $this->connection->insert('schedule', ['id' => 1, 'image' => 'image.jpg']);
        $this->connection->insert('media_file', ['id' => 1, 'storage_name' => $mediaName, 'size' => 5]);
        $this->connection->insert('club_event_document', ['id' => 1, 'storage_name' => $eventName, 'size' => 5]);

        $tester = new CommandTester(new CheckFileMigrationCommand($this->connection, $this->root));
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Kursbilddateien: 2', $tester->getDisplay());
        self::assertStringContainsString('Kursbildreferenzen: 2', $tester->getDisplay());
        self::assertStringContainsString('Mehrfach referenzierte Kursbilder: 1', $tester->getDisplay());
        self::assertStringContainsString('Nicht referenzierte Kursbilddateien: 1', $tester->getDisplay());
        self::assertStringContainsString('Mediatheksdateien einschließlich Veranstaltungsdokumente: 3', $tester->getDisplay());
        self::assertStringContainsString('Mediathek- und Dokumentreferenzen: 2', $tester->getDisplay());
        self::assertStringContainsString('Nicht referenzierte Mediatheksdateien: 1', $tester->getDisplay());
        self::assertFileExists($course.'/unused.png');
        self::assertFileExists($media.'/orphan');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM club_event_document'));
    }

    public function testMissingUnsafeAndConflictingReferencesBlockMigration(): void
    {
        $course = $this->root.'/public/images/kurse';
        $media = $this->root.'/var/private/website_media';
        $shared = str_repeat('c', 40).'.pdf';
        file_put_contents($media.'/'.$shared, 'abc');
        symlink(sys_get_temp_dir(), $course.'/outside');
        symlink(sys_get_temp_dir(), $this->root.'/files');
        $this->connection->insert('courses', ['id' => 1, 'image' => '../private.jpg']);
        $this->connection->insert('courses', ['id' => 2, 'image' => 'missing.jpg']);
        $this->connection->insert('media_file', ['id' => 1, 'storage_name' => $shared, 'size' => 99]);
        $this->connection->insert('club_event_document', ['id' => 1, 'storage_name' => $shared, 'size' => 3]);
        $this->connection->insert('club_event_document', ['id' => 2, 'storage_name' => '../private.pdf', 'size' => 1]);

        $tester = new CommandTester(new CheckFileMigrationCommand($this->connection, $this->root));
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Kurs-ID 1: Ungültiger Bildpfad.', $tester->getDisplay());
        self::assertStringContainsString('Kurs-ID 2: Referenziertes Bild fehlt', $tester->getDisplay());
        self::assertStringContainsString('Mediathek-ID 1: Gespeicherte Dateigröße stimmt nicht überein.', $tester->getDisplay());
        self::assertStringContainsString('Veranstaltungsdokument-ID 2: Ungültiger Speichername.', $tester->getDisplay());
        self::assertStringContainsString('Mehrere Mediathek- oder Veranstaltungsdatensätze', $tester->getDisplay());
        self::assertStringContainsString('Symbolischer Link im Quellverzeichnis.', $tester->getDisplay());
        self::assertStringContainsString('Das Ziel files ist ein symbolischer Link', $tester->getDisplay());
        self::assertStringNotContainsString('private.jpg', $tester->getDisplay());
        self::assertFileExists($media.'/'.$shared);
    }
}
