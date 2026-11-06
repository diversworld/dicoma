<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\Schedule;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Enum\MembershipStatus;
use App\Service\CourseImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase0SecurityHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $ownClub;
    private Club $otherClub;
    private ?string $imageDirectory = null;
    /** @var list<string> */
    private array $temporaryFiles = [];

    private function initialize(bool $singleClub): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'DICOMA_SINGLE_CLUB_MODE' => $singleClub ? '1' : '0',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->ownClub = (new Club())->setName('Own club');
        $this->otherClub = (new Club())->setName('Other club');
        $this->em->persist($this->ownClub);
        $this->em->persist($this->otherClub);
        $this->em->flush();
        self::assertSame(1, $this->ownClub->getId());
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        if ($this->imageDirectory !== null && is_dir($this->imageDirectory)) {
            foreach (glob($this->imageDirectory.'/*') as $path) {
                unlink($path);
            }
            rmdir($this->imageDirectory);
        }
        parent::tearDown();
    }

    public static function clubModes(): iterable
    {
        yield 'multiple clubs' => [false];
        yield 'single club' => [true];
    }

    #[DataProvider('clubModes')]
    public function testSchedulePagesFilterClubAndStatusAndSanitizeNotes(bool $singleClub): void
    {
        $this->initialize($singleClub);
        $member = $this->account('member', $this->ownClub);
        $open = $this->schedule($this->ownClub, CourseStatus::OPEN, 'Own open');
        $open->setNotes('<strong>Safe note</strong><script data-probe="x">alert(1)</script><img src=x onerror=alert(1)>');
        $running = $this->schedule($this->ownClub, CourseStatus::RUNNING, 'Own running');
        $completed = $this->schedule($this->ownClub, CourseStatus::COMPLETED, 'Own completed');
        $draft = $this->schedule($this->ownClub, CourseStatus::DRAFT, 'Own draft');
        $cancelled = $this->schedule($this->ownClub, CourseStatus::CANCELLED, 'Own cancelled');
        $foreign = $this->schedule($this->otherClub, CourseStatus::OPEN, 'Foreign schedule');
        $unassigned = (new Schedule())->setTitle('Unassigned schedule')
            ->setStartDate(new \DateTime('today'))->setStartTime(new \DateTime('12:00'));
        $this->em->persist($unassigned);
        $this->em->flush();

        $this->client->request('GET', '/schedule/'.$open->getId());
        self::assertResponseRedirects('/login');

        $guest = (new User())->setUser('guest')->setEmail('guest@example.invalid')->setPassword('unused')
            ->setIsVerified(true)->setPerson((new Person())->setFirstname('Guest')->setLastname('Test'));
        $this->em->persist($guest);
        $this->em->flush();
        $this->client->loginUser($guest);
        $this->client->request('GET', '/schedule/'.$open->getId());
        self::assertContains($this->client->getResponse()->getStatusCode(), [302, 403]);
        $this->client->loginUser($member);
        foreach (['/schedule/', '/schedule/calendar'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            foreach (['Own open', 'Own running', 'Own completed'] as $visible) {
                self::assertSelectorTextContains('body', $visible);
            }
            foreach (['Own draft', 'Own cancelled', 'Foreign schedule', 'Unassigned schedule'] as $hidden) {
                self::assertSelectorTextNotContains('body', $hidden);
            }
        }
        foreach ([$open, $running, $completed] as $visible) {
            $this->client->request('GET', '/schedule/'.$visible->getId());
            self::assertResponseIsSuccessful();
        }
        foreach ([$draft, $cancelled, $foreign, $unassigned] as $hidden) {
            $this->client->request('GET', '/schedule/'.$hidden->getId());
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/schedule/'.$open->getId());
        self::assertSelectorTextContains('strong', 'Safe note');
        self::assertSelectorNotExists('script[data-probe]');
        self::assertSelectorNotExists('img[onerror]');

        $member->getMember()->setMembershipStatus(MembershipStatus::FORMER);
        $this->em->flush();
        $this->client->loginUser($member);
        $this->client->request('GET', '/schedule/'.$open->getId());
        self::assertResponseRedirects('/login');
    }

    public function testCourseImageUploadEnforcesAuthorizationCsrfAndImageValidation(): void
    {
        $this->initialize(false);
        $this->imageDirectory = sys_get_temp_dir().'/dicoma-course-image-'.bin2hex(random_bytes(8));
        mkdir($this->imageDirectory, 0700);
        static::getContainer()->set(CourseImageStorage::class, new CourseImageStorage($this->imageDirectory));

        $member = $this->account('member', $this->ownClub);
        $admin = $this->account('admin', $this->ownClub, [User::ROLE_ADMIN]);
        $super = $this->account('super', $this->ownClub, [User::ROLE_SUPER_ADMIN]);
        $own = $this->course($this->ownClub, CourseStatus::OPEN, 'Own course');
        $foreign = $this->course($this->otherClub, CourseStatus::OPEN, 'Foreign course');
        $this->em->flush();

        $this->client->request('POST', '/courses/upload');
        self::assertResponseRedirects();
        $this->client->loginUser($member);
        $this->client->request('POST', '/courses/upload');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($admin);
        $this->client->request('POST', '/courses/upload', ['course_id' => $own->getId(), '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);

        $foreignToken = $this->token('course_image_upload_'.$foreign->getId());
        $this->client->request('POST', '/courses/upload', ['course_id' => $foreign->getId(), '_token' => $foreignToken]);
        self::assertResponseStatusCodeSame(403);
        $token = $this->token('course_image_upload_'.$own->getId());
        $this->client->request('POST', '/courses/upload', ['course_id' => $own->getId(), '_token' => $token]);
        self::assertResponseStatusCodeSame(400);

        foreach ([
            ['payload.html', '<!doctype html><html><body>test</body></html>'],
            ['payload.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'],
            ['broken.png', "\x89PNG\r\n\x1a\n"],
        ] as [$name, $content]) {
            $this->postUpload($own->getId(), $token, $name, $content);
            self::assertResponseStatusCodeSame(422);
        }
        $this->postUpload($own->getId(), $token, 'oversized.png', str_repeat('x', 10 * 1024 * 1024 + 1));
        self::assertResponseStatusCodeSame(422);

        $images = [
            'png' => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC',
            'jpg' => '/9j/4AAQSkZJRgABAQEAYABgAAD//gA+Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBkZWZhdWx0IHF1YWxpdHkK/9sAQwAIBgYHBgUIBwcHCQkICgwUDQwLCwwZEhMPFB0aHx4dGhwcICQuJyAiLCMcHCg3KSwwMTQ0NB8nOT04MjwuMzQy/9sAQwEJCQkMCwwYDQ0YMiEcITIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIy/8AAEQgAAQABAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8A+f6KKKAP/9k=',
            'webp' => 'UklGRiQAAABXRUJQVlA4IBgAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v02aAA=',
        ];
        foreach ($images as $extension => $encoded) {
            $content = base64_decode($encoded, true);
            self::assertIsString($content);
            $this->postUpload($own->getId(), $token, 'misleading.php', $content);
            self::assertResponseIsSuccessful();
            $response = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            self::assertMatchesRegularExpression('/^[a-f0-9]{40}\.'.$extension.'$/D', $response['file']);
            self::assertFileExists($this->imageDirectory.'/'.$response['file']);
            self::assertArrayNotHasKey('allFiles', $response);
        }
        $this->client->loginUser($super);
        $crawler = $this->client->request('GET', '/admin/settings');
        $this->client->submit($crawler->filter('form[name="admin_club"]')->first()->form(), [
            'admin_club[club]' => (string) $this->otherClub->getId(),
        ]);
        self::assertResponseRedirects();
        $ownToken = $this->token('course_image_upload_'.$own->getId());
        $this->client->request('POST', '/courses/upload', ['course_id' => $own->getId(), '_token' => $ownToken]);
        self::assertResponseStatusCodeSame(403);
        $foreignToken = $this->token('course_image_upload_'.$foreign->getId());
        $this->client->request('POST', '/courses/upload', ['course_id' => $foreign->getId(), '_token' => $foreignToken]);
        self::assertResponseStatusCodeSame(400);
    }

    private function schedule(Club $club, CourseStatus $status, string $title): Schedule
    {
        $schedule = (new Schedule())->setCourses($this->course($club, $status, $title.' course'))
            ->setTitle($title)->setStartDate(new \DateTime('today'))->setStartTime(new \DateTime('12:00'));
        $this->em->persist($schedule);
        return $schedule;
    }

    private function course(Club $club, CourseStatus $status, string $title): Courses
    {
        $course = (new Courses())->setClub($club)->setTitle($title)->setCategory('beginner')->setStatus($status);
        $this->em->persist($course);
        return $course;
    }

    private function account(string $name, Club $club, array $roles = []): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Test');
        $member = (new Member())->setPerson($person)->setClub($club);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setIsVerified(true)->setRoles($roles)->setMember($member);
        $this->em->persist($user);
        return $user;
    }

    private function token(string $name): string
    {
        $this->client->request('GET', '/admin');
        $stack = static::getContainer()->get(RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken($name)->getValue();
            $this->client->getRequest()->getSession()->save();
            return $token;
        } finally {
            $stack->pop();
        }
    }

    private function postUpload(int $courseId, string $token, string $name, string $content): void
    {
        $path = tempnam(sys_get_temp_dir(), 'course-image-');
        self::assertIsString($path);
        file_put_contents($path, $content);
        $this->temporaryFiles[] = $path;
        $this->client->request('POST', '/courses/upload', ['course_id' => $courseId, '_token' => $token], [
            'file' => new UploadedFile($path, $name, null, null, true),
        ]);
    }
}
