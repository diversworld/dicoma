<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Club;
use App\Entity\MediaFile;
use App\Service\MediaFileStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class MediaFileStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/dicoma-media-migration-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/var/private/website_media', 0700, true);
        mkdir($this->root.'/files/website_media', 0700, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testIncompleteMigrationBlocksCutoverUntilContentsMatch(): void
    {
        $name = str_repeat('a', 40).'.pdf';
        $old = $this->root.'/var/private/website_media/'.$name;
        $new = $this->root.'/files/website_media/'.$name;
        file_put_contents($old, 'original');
        file_put_contents($new, 'different');
        $storage = new MediaFileStorage($this->root);
        $file = new MediaFile(new Club(), $name, 'test.pdf', 'application/pdf', 8, '');

        try {
            $storage->path($file);
            self::fail('Inconsistent files must block cutover.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('vollständig und unverändert', $exception->getMessage());
        }
        self::assertFileDoesNotExist($this->root.'/files/.website-media-active');
        file_put_contents($new, 'original');
        self::assertSame($new, $storage->path($file));
        self::assertFileExists($this->root.'/files/.website-media-active');

        file_put_contents($old, 'old storage is no longer authoritative');
        self::assertSame($new, $storage->path($file));
        self::assertSame('original', file_get_contents($new));
    }

    public function testSymlinkInTargetBlocksCutover(): void
    {
        $name = str_repeat('b', 40).'.pdf';
        file_put_contents($this->root.'/var/private/website_media/'.$name, 'document');
        symlink($this->root.'/var/private/website_media/'.$name, $this->root.'/files/website_media/'.$name);
        $file = new MediaFile(new Club(), $name, 'test.pdf', 'application/pdf', 8, '');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Symbolischer Link');
        (new MediaFileStorage($this->root))->path($file);
    }
}
