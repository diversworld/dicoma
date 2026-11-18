<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\ImportCourseImagesCommand;
use App\Entity\{Club, Courses, MediaFile, MediaFolder, Schedule};
use App\Service\{MediaFileStorage, MembershipRoleContinuity, SingleClubMode};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ImportCourseImagesCommandTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Club $club;
    private string $root;
    private string $png;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1'] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value; putenv($name.'='.$value);
        }
        self::bootKernel(['environment' => 'test']);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Import club');
        $this->em->persist($this->club);
        $this->em->flush();
        $this->root = sys_get_temp_dir().'/dicoma-course-import-'.bin2hex(random_bytes(8));
        mkdir($this->root.'/files/kurse/nested/empty', 0700, true);
        mkdir($this->root.'/files/website_media', 0700);
        file_put_contents($this->root.'/files/.course-images-active', '');
        file_put_contents($this->root.'/files/.website-media-active', '');
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l7sAAAAASUVORK5CYII=', true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
        parent::tearDown();
    }

    public function testImportsAllFilesFoldersAndSharedReferencesWithoutRemovingSources(): void
    {
        file_put_contents($this->root.'/files/kurse/nested/shared.png', $this->png);
        file_put_contents($this->root.'/files/kurse/unused.png', $this->png);
        $first = (new Courses())->setClub($this->club)->setTitle('First')->setCategory('training')->setImage('nested/shared.png');
        $second = (new Courses())->setClub($this->club)->setTitle('Second')->setCategory('training')->setImage('nested/shared.png');
        $schedule = (new Schedule())->setCourses($first)->setTitle('Session')->setImage('nested/shared.png');
        foreach ([$first, $second, $schedule] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $ids = [$first->getId(), $second->getId(), $schedule->getId()];
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));
        self::assertStringContainsString('Kursbilddateien: 2; Ordner einschließlich leerer Ordner: 2; Referenzen: 3.', $tester->getDisplay());
        self::assertSame(0, $this->em->getRepository(MediaFile::class)->count([]));
        self::assertSame(0, $this->em->getRepository(MediaFolder::class)->count([]));
        self::assertSame([], array_values(array_diff(scandir($this->root.'/files/website_media'), ['.', '..'])));

        self::assertSame(Command::SUCCESS, $tester->execute(['--confirm' => true]));
        $this->em->clear();
        $first = $this->em->find(Courses::class, $ids[0]);
        $second = $this->em->find(Courses::class, $ids[1]);
        $schedule = $this->em->find(Schedule::class, $ids[2]);
        $image = $first->getMediaImage();
        self::assertNotNull($image);
        self::assertSame($image->getId(), $second->getMediaImage()?->getId());
        self::assertSame($image->getId(), $schedule->getMediaImage()?->getId());
        self::assertSame('nested/shared.png', $first->getImage());
        self::assertSame('Kursbilder (Altbestand) / nested', $image->getFolder()->getPath());
        self::assertSame($this->png, file_get_contents((new MediaFileStorage($this->root))->path($image)));
        self::assertSame($this->png, file_get_contents($this->root.'/files/kurse/nested/shared.png'));
        self::assertSame($this->png, file_get_contents($this->root.'/files/kurse/unused.png'));
        self::assertSame(2, $this->em->getRepository(MediaFile::class)->count([]));
        self::assertSame(3, $this->em->getRepository(MediaFolder::class)->count([]));
        self::assertNotNull($this->em->getRepository(MediaFolder::class)->findOneBy(['name' => 'empty']));

        try {
            $tester->execute(['--confirm' => true]);
            self::fail('A completed import must not run again.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('Zielordner existiert bereits', $exception->getMessage());
        }
        self::assertSame(2, $this->em->getRepository(MediaFile::class)->count([]));
    }

    public function testMissingOrForeignReferenceBlocksTheEntireImport(): void
    {
        file_put_contents($this->root.'/files/kurse/existing.png', $this->png);
        $course = (new Courses())->setClub($this->club)->setTitle('Missing')->setCategory('training')->setImage('missing.png');
        $this->em->persist($course); $this->em->flush();
        try {
            $this->tester()->execute(['--confirm' => true]);
            self::fail('A missing reference must stop the import.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('Bildreferenz', $exception->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(MediaFile::class)->count([]));
        self::assertSame(0, $this->em->getRepository(MediaFolder::class)->count([]));
        self::assertSame([], array_values(array_diff(scandir($this->root.'/files/website_media'), ['.', '..'])));

        $course = $this->em->find(Courses::class, $course->getId());
        $other = (new Club())->setName('Foreign club');
        $this->em->persist($other);
        $course->setClub($other)->setImage('existing.png');
        $this->em->flush();
        $this->expectException(\DomainException::class);
        $this->tester()->execute(['--dry-run' => true]);
    }

    public function testRetryReusesOnlyAnIdenticalUnreferencedTarget(): void
    {
        $path = 'orphan.png';
        file_put_contents($this->root.'/files/kurse/'.$path, $this->png);
        $name = substr(hash('sha256', "course-image-import\0".$this->club->getId()."\0".$path."\0".hash('sha256', $this->png)), 0, 40).'.png';
        $target = $this->root.'/files/website_media/'.$name;
        file_put_contents($target, 'different');
        try {
            $this->tester()->execute(['--confirm' => true]);
            self::fail('A conflicting target must not be overwritten.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('abweichendem Inhalt', $exception->getMessage());
        }
        self::assertSame('different', file_get_contents($target));
        self::assertSame(0, $this->em->getRepository(MediaFile::class)->count([]));
        file_put_contents($target, $this->png);
        chmod($target, 0644);
        clearstatcache(true, $target);
        try {
            $this->tester()->execute(['--confirm' => true]);
            self::fail('A target that is not private must not be reused.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('nicht privat', $exception->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(MediaFile::class)->count([]));
        chmod($target, 0600);
        clearstatcache(true, $target);
        self::assertSame(Command::SUCCESS, $this->tester()->execute(['--confirm' => true]));
        self::assertSame($this->png, file_get_contents($target));
        self::assertSame(1, $this->em->getRepository(MediaFile::class)->count([]));
    }

    public function testRejectsExecutionAsAnotherStorageOwner(): void
    {
        if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) {
            self::markTestSkipped('Changing a directory owner requires root.');
        }
        $directory = $this->root.'/files/website_media';
        self::assertTrue(chown($directory, 65534));
        clearstatcache(true, $directory);
        try {
            $this->tester()->execute(['--dry-run' => true]);
            self::fail('A different storage owner must block the import.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Eigentümer des gemeinsamen Dateispeichers', $exception->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(MediaFile::class)->count([]));

        self::assertTrue(chown($directory, 0));
        clearstatcache(true, $directory);
        $relative = 'orphan.png';
        file_put_contents($this->root.'/files/kurse/'.$relative, $this->png);
        $name = substr(hash('sha256', "course-image-import\0".$this->club->getId()."\0".$relative."\0".hash('sha256', $this->png)), 0, 40).'.png';
        $target = $directory.'/'.$name;
        file_put_contents($target, $this->png);
        chmod($target, 0600);
        self::assertTrue(chown($target, 65534));
        clearstatcache(true, $target);
        try {
            $this->tester()->execute(['--confirm' => true]);
            self::fail('A target owned by another user must not be reused.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('falschen Eigentümer', $exception->getMessage());
        }
        self::assertSame(0, $this->em->getRepository(MediaFile::class)->count([]));
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new ImportCourseImagesCommand(
            $this->em, self::getContainer()->get(SingleClubMode::class),
            self::getContainer()->get(MembershipRoleContinuity::class), $this->root, false,
        ));
    }
}
