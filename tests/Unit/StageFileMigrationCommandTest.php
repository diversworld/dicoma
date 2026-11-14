<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\CheckFileMigrationCommand;
use App\Command\StageFileMigrationCommand;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

final class StageFileMigrationCommandTest extends TestCase
{
    private Connection $connection;
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/dicoma-file-stage-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/public/images/kurse/nested', 0700, true);
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

    public function testCopiesReferencedAndUnreferencedFilesAndIsRepeatable(): void
    {
        $name = str_repeat('a', 40).'.pdf';
        file_put_contents($this->root.'/public/images/kurse/course.jpg', 'image');
        file_put_contents($this->root.'/public/images/kurse/nested/unused.txt', 'unused');
        file_put_contents($this->root.'/var/private/website_media/'.$name, 'document');
        $this->connection->insert('courses', ['id' => 1, 'image' => 'course.jpg']);
        $this->connection->insert('club_event_document', ['id' => 1, 'storage_name' => $name, 'size' => 8]);
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Geprüft kopiert: 3; bereits identisch vorhanden: 0.', $tester->getDisplay());
        self::assertSame('image', file_get_contents($this->root.'/files/kurse/course.jpg'));
        self::assertSame('unused', file_get_contents($this->root.'/files/kurse/nested/unused.txt'));
        self::assertSame('document', file_get_contents($this->root.'/files/website_media/'.$name));
        self::assertSame(0, fileperms($this->root.'/files') & 0077);
        self::assertSame(0, fileperms($this->root.'/files/kurse/course.jpg') & 0077);
        self::assertFileExists($this->root.'/public/images/kurse/course.jpg');
        self::assertFileExists($this->root.'/var/private/website_media/'.$name);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Geprüft kopiert: 0; bereits identisch vorhanden: 3.', $tester->getDisplay());
    }

    public function testInvalidSourceStopsBeforeCreatingDestination(): void
    {
        $this->connection->insert('courses', ['id' => 1, 'image' => 'missing.jpg']);
        $tester = $this->tester();
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('es wurde nichts kopiert', $tester->getDisplay());
        self::assertDirectoryDoesNotExist($this->root.'/files');
    }

    public function testDifferentExistingTargetIsNotOverwritten(): void
    {
        file_put_contents($this->root.'/public/images/kurse/course.jpg', 'original');
        mkdir($this->root.'/files/kurse', 0700, true);
        file_put_contents($this->root.'/files/kurse/course.jpg', 'different');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('abweichendem Inhalt');
        try {
            $this->tester()->execute([]);
        } finally {
            self::assertSame('original', file_get_contents($this->root.'/public/images/kurse/course.jpg'));
            self::assertSame('different', file_get_contents($this->root.'/files/kurse/course.jpg'));
        }
    }

    public function testTargetSymlinkIsRejected(): void
    {
        file_put_contents($this->root.'/public/images/kurse/course.jpg', 'original');
        mkdir($this->root.'/files', 0700);
        symlink($this->root.'/public/images/kurse', $this->root.'/files/kurse');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('symbolischer Link');
        $this->tester()->execute([]);
    }

    public function testSymlinkInSourceAncestorsIsRejected(): void
    {
        rename($this->root.'/public/images', $this->root.'/images');
        symlink($this->root.'/images', $this->root.'/public/images');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Quellpfad enthält einen symbolischen Link');
        $this->tester()->execute([]);
    }

    public function testActiveMediaStorageCannotBeStagedAgain(): void
    {
        mkdir($this->root.'/files', 0700);
        file_put_contents($this->root.'/files/.website-media-active', '');
        $tester = $this->tester();
        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('bereits umgestellt', $tester->getDisplay());
    }

    public function testCourseOnlyStageWorksAfterMediaCutoverButNotAfterCourseCutover(): void
    {
        mkdir($this->root.'/files', 0700);
        file_put_contents($this->root.'/files/.website-media-active', '');
        file_put_contents($this->root.'/public/images/kurse/image.jpg', 'image');
        $this->connection->insert('courses', ['id' => 1, 'image' => 'image.jpg']);
        $this->connection->insert('media_file', ['id' => 1, 'storage_name' => 'invalid', 'size' => 1]);
        $tester = $this->tester();
        self::assertSame(Command::SUCCESS, $tester->execute(['--courses-only' => true]));
        self::assertSame('image', file_get_contents($this->root.'/files/kurse/image.jpg'));
        file_put_contents($this->root.'/files/.course-images-active', '');
        self::assertSame(Command::FAILURE, $tester->execute(['--courses-only' => true]));
        self::assertStringContainsString('bereits umgestellt', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new StageFileMigrationCommand(new CheckFileMigrationCommand($this->connection, $this->root), $this->root));
    }
}
