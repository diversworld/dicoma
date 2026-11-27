<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\GitVersionChecker;
use App\Service\SoftwareBackup;
use App\Service\SoftwareReleaseArchive;
use App\Service\SoftwareUpdater;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem;

final class SoftwareUpdaterTest extends TestCase
{
    private string $root;
    private string $oldPath;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/dicoma-update-test-'.bin2hex(random_bytes(6));
        $fs = new Filesystem();
        $fs->mkdir([$this->root.'/project/var', $this->root.'/project/bin', $this->root.'/project/src', $this->root.'/commands']);
        file_put_contents($this->root.'/project/VERSION', "1.0.3\n");
        file_put_contents($this->root.'/project/.env', 'DATABASE_URL=local');
        file_put_contents($this->root.'/project/.env.local', 'APP_SECRET=local');
        file_put_contents($this->root.'/project/src/app.php', 'old code');
        file_put_contents($this->root.'/project/bin/console', "<?php exit(0);\n");
        file_put_contents($this->root.'/commands/composer', "#!/bin/sh\nif [ -f fail-composer ] && [ \"\$1\" != '--version' ]; then exit 1; fi\nexit 0\n");
        chmod($this->root.'/commands/composer', 0755);
        $this->oldPath = (string) getenv('PATH');
        putenv('PATH='.$this->root.'/commands:'.$this->oldPath);
    }

    protected function tearDown(): void
    {
        putenv('PATH='.$this->oldPath);
        (new Filesystem())->remove($this->root);
    }

    public function testInstallsArchiveWithoutGitAfterCreatingBackup(): void
    {
        $this->archive([
            'VERSION' => "1.0.3\n",
            '.env' => 'DATABASE_URL=release',
            '.env.local' => 'APP_SECRET=release',
            'src/app.php' => 'new code',
            'files/private.txt' => 'release file',
        ]);
        mkdir($this->root.'/project/files');
        file_put_contents($this->root.'/project/files/private.txt', 'private');
        file_put_contents($this->root.'/project/custom.txt', 'local file');

        $result = $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));

        self::assertSame('1.0.4', trim(file_get_contents($this->root.'/project/VERSION')));
        self::assertSame('new code', file_get_contents($this->root.'/project/src/app.php'));
        self::assertSame('DATABASE_URL=local', file_get_contents($this->root.'/project/.env'));
        self::assertSame('APP_SECRET=local', file_get_contents($this->root.'/project/.env.local'));
        self::assertSame('private', file_get_contents($this->root.'/project/files/private.txt'));
        self::assertSame('local file', file_get_contents($this->root.'/project/custom.txt'));
        self::assertFileDoesNotExist($this->root.'/project/var/.dicoma-update-failed');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($result['backup']));
        self::assertSame('old code', $zip->getFromName('src/app.php'));
        self::assertNotFalse($zip->getFromName('_database/database.dump'));
        $zip->close();
    }

    public function testFailedInstallationKeepsOldVersionAndBlocksRetry(): void
    {
        $this->archive(['VERSION' => '1.0.4', 'src/app.php' => 'new code']);
        file_put_contents($this->root.'/project/fail-composer', '');
        try {
            $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));
            self::fail('A failed Composer run must fail the update.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Sicherheits-Backup:', $exception->getMessage());
        }
        self::assertSame('1.0.3', trim(file_get_contents($this->root.'/project/VERSION')));
        self::assertFileExists($this->root.'/project/var/.dicoma-update-failed');
        $this->expectExceptionMessage('vorheriges Update wurde nicht abgeschlossen');
        $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));
    }

    public function testUnsafeArchiveIsRejectedBeforeBackupOrInstallation(): void
    {
        $this->archive(['VERSION' => '1.0.4', '../outside.php' => 'unsafe']);
        try {
            $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));
            self::fail('An unsafe archive must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('ungültigen Pfad', $exception->getMessage());
        }
        self::assertSame('old code', file_get_contents($this->root.'/project/src/app.php'));
        self::assertSame('1.0.3', trim(file_get_contents($this->root.'/project/VERSION')));
        self::assertFileDoesNotExist($this->root.'/outside.php');
        self::assertFileDoesNotExist($this->root.'/project/var/.dicoma-update-failed');
    }

    public function testOlderReleaseCannotReplaceTheArchiveUpdater(): void
    {
        $this->archive(['VERSION' => '1.0.4'], false);
        $this->expectExceptionMessage('Release enthält noch nicht den archivbasierten Updater');
        $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));
    }

    public function testReleaseVersionNewerThanTagIsRejectedBeforeInstallation(): void
    {
        $this->archive(['VERSION' => '1.0.5', 'src/app.php' => 'new code']);
        try {
            $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));
            self::fail('A release newer than its tag must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Versionsdatei im Release-Archiv', $exception->getMessage());
        }
        self::assertSame('old code', file_get_contents($this->root.'/project/src/app.php'));
        self::assertSame('1.0.3', trim(file_get_contents($this->root.'/project/VERSION')));
        self::assertFileDoesNotExist($this->root.'/project/var/.dicoma-update-failed');
    }

    public function testSymlinkedTargetIsRejectedWithoutModifyingIt(): void
    {
        $this->archive(['VERSION' => '1.0.4', 'src/app.php' => 'new code']);
        unlink($this->root.'/project/src/app.php');
        file_put_contents($this->root.'/outside.php', 'outside');
        symlink($this->root.'/outside.php', $this->root.'/project/src/app.php');
        try {
            $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));
            self::fail('A symlinked destination must be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('symbolischen Link', $exception->getMessage());
        }
        self::assertSame('outside', file_get_contents($this->root.'/outside.php'));
        self::assertSame('1.0.3', trim(file_get_contents($this->root.'/project/VERSION')));
    }

    public function testIncompleteBackupStopsUpdateBeforeFilesAreChanged(): void
    {
        $this->archive(['VERSION' => '1.0.4', 'src/app.php' => 'new code']);
        file_put_contents($this->root.'/outside.txt', 'private');
        symlink($this->root.'/outside.txt', $this->root.'/project/linked-data');
        try {
            $this->updater()->install('1.0.4', '1.0.4', str_repeat('a', 40));
            self::fail('An incomplete backup must stop the update.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('nicht automatisch gesichert', $exception->getMessage());
        }
        self::assertSame('old code', file_get_contents($this->root.'/project/src/app.php'));
        self::assertSame('1.0.3', trim(file_get_contents($this->root.'/project/VERSION')));
        self::assertFileDoesNotExist($this->root.'/project/var/.dicoma-update-failed');
    }

    public function testGithubReleaseDownloadWhenExplicitlyEnabled(): void
    {
        $commit = getenv('DICOMA_TEST_RELEASE_COMMIT');
        if (!is_string($commit) || $commit === '') {
            self::markTestSkipped('DICOMA_TEST_RELEASE_COMMIT is not set.');
        }
        $directory = $this->root.'/github';
        mkdir($directory);
        $paths = (new SoftwareReleaseArchive('diversworld/dicoma'))->prepare($commit, $directory);
        self::assertContains('VERSION', $paths);
        self::assertContains('bin/console', $paths);
        self::assertFileExists($directory.'/release/composer.lock');
        $status = (new GitVersionChecker($this->root.'/project', new ArrayAdapter(), 'diversworld/dicoma'))->check(true);
        self::assertTrue($status['checked'], $status['error'] ?? '');
        self::assertNotNull($status['tag']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $status['commit']);
    }

    /** @param array<string,string> $entries */
    private function archive(array $entries, bool $includeUpdater = true): void
    {
        $entries += [
            'VERSION' => '1.0.4',
            'composer.json' => '{}',
            'composer.lock' => '{}',
            'bin/console' => "<?php exit(0);\n",
        ];
        if ($includeUpdater) {
            $entries['src/Service/SoftwareReleaseArchive.php'] = '<?php';
        }
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->root.'/release.zip', \ZipArchive::CREATE));
        foreach ($entries as $path => $content) {
            self::assertTrue($zip->addFromString('dicoma-release/'.$path, $content));
        }
        self::assertTrue($zip->close());
    }

    private function updater(): SoftwareUpdater
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $this->root.'/project/var/database.sqlite']);
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS example (id INTEGER PRIMARY KEY)');
        $archive = new class($this->root.'/release.zip') extends SoftwareReleaseArchive {
            public function __construct(private readonly string $fixture) { parent::__construct('diversworld/dicoma'); }
            protected function download(string $commit, string $destination): void
            {
                if (!copy($this->fixture, $destination)) { throw new \RuntimeException('Fixture copy failed.'); }
            }
        };
        return new SoftwareUpdater($this->root.'/project', new SoftwareBackup($this->root.'/project', $connection), $archive);
    }
}
