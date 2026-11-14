<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\CourseImageStorage;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

final class CourseImageStorageTest extends TestCase
{
    public function testCutoverRequiresMatchingLegacyImages(): void
    {
        $root = sys_get_temp_dir().'/dicoma-course-cutover-'.bin2hex(random_bytes(8));
        mkdir($root.'/public/images/kurse', 0700, true);
        mkdir($root.'/files/kurse', 0700, true);
        file_put_contents($root.'/public/images/kurse/image.jpg', 'original');
        file_put_contents($root.'/files/kurse/image.jpg', 'different');
        $storage = new CourseImageStorage($root.'/files/kurse', $root);
        try {
            try {
                $storage->directory();
                self::fail('Inconsistent files must block cutover.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('vollständig und unverändert', $exception->getMessage());
            }
            self::assertFileDoesNotExist($root.'/files/.course-images-active');
            file_put_contents($root.'/files/kurse/image.jpg', 'original');
            self::assertSame($root.'/files/kurse', $storage->directory());
            self::assertFileExists($root.'/files/.course-images-active');
        } finally {
            (new Filesystem())->remove($root);
        }

    }

    public function testMissingReferencedImageBlocksCutoverWithoutLegacyDirectory(): void
    {
        $root = sys_get_temp_dir().'/dicoma-course-reference-'.bin2hex(random_bytes(8));
        mkdir($root.'/files/kurse', 0700, true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE courses (image TEXT)');
        $connection->executeStatement('CREATE TABLE schedule (image TEXT)');
        $connection->insert('courses', ['image' => 'missing.jpg']);
        try {
            try {
                (new CourseImageStorage($root.'/files/kurse', $root, $connection))->directory();
                self::fail('A missing reference must block cutover.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Referenzierte Kursbilder', $exception->getMessage());
            }
            self::assertFileDoesNotExist($root.'/files/.course-images-active');
        } finally {
            $connection->close();
            (new Filesystem())->remove($root);
        }
    }
}
