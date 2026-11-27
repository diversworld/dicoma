<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Service\SoftwareBackup;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class SoftwareBackupTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/dicoma-backup-'.bin2hex(random_bytes(6));
        (new Filesystem())->mkdir([$this->projectDir.'/private', $this->projectDir.'/vendor', $this->projectDir.'/var/cache']);
        file_put_contents($this->projectDir.'/.env.local', 'APP_SECRET=test');
        file_put_contents($this->projectDir.'/private/document.txt', 'private');
        file_put_contents($this->projectDir.'/vendor/autoload.php', 'vendor');
        file_put_contents($this->projectDir.'/var/cache/result', 'cache');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testBackupContainsDatabaseAndProjectButExcludesGeneratedFiles(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $this->projectDir.'/database.sqlite',
        ]);
        $connection->executeStatement('CREATE TABLE sample (id INTEGER PRIMARY KEY)');
        $connection->executeStatement('INSERT INTO sample (id) VALUES (42)');

        $result = (new SoftwareBackup($this->projectDir, $connection))->create();

        self::assertFileExists($result['path']);
        self::assertSame(0600, fileperms($result['path']) & 0777);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($result['path']));
        self::assertNotFalse($zip->locateName('_database/database.dump'));
        self::assertNotFalse($zip->locateName('.env.local'));
        self::assertNotFalse($zip->locateName('private/document.txt'));
        self::assertFalse($zip->locateName('vendor/autoload.php'));
        self::assertFalse($zip->locateName('var/cache/result'));
        $extractedDb = $this->projectDir.'/restored.sqlite';
        self::assertTrue($zip->getFromName('_database/database.dump') !== false);
        file_put_contents($extractedDb, (string) $zip->getFromName('_database/database.dump'));
        $restored = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $extractedDb]);
        self::assertSame('42', (string) $restored->fetchOne('SELECT id FROM sample'));
        $zip->close();
    }
}
