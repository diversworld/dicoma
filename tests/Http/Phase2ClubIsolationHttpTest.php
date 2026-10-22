<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\Courses;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Read-only HTTP authorization checks against explicitly provisioned staging fixtures.
 * Never creates, changes, or deletes application records.
 */
final class Phase2ClubIsolationHttpTest extends WebTestCase
{
    private function fixtures(): array
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1') {
            self::markTestSkipped('Requires APP_ENV=test and explicit staging database confirmation.');
        }

        $keys = [
            'DICOMA_TEST_EXPECTED_DATABASE',
            'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'DICOMA_TEST_ROLE_USER_EMAIL',
            'DICOMA_TEST_CLUB_A_COURSE_ID',
            'DICOMA_TEST_CLUB_B_COURSE_ID',
        ];
        $values = [];
        foreach ($keys as $key) {
            $value = trim((string) getenv($key));
            if ($value === '') {
                self::markTestSkipped('Missing staging fixture configuration: '.$key);
            }
            $values[$key] = $value;
        }
        foreach (['DICOMA_TEST_CLUB_A_COURSE_ID', 'DICOMA_TEST_CLUB_B_COURSE_ID'] as $key) {
            if (!ctype_digit($values[$key]) || (int) $values[$key] < 1) {
                self::fail('Invalid fixture course ID: '.$key);
            }
        }
        return $values;
    }

    public function testClubAdministratorsCannotReadOtherClubCourseHistory(): void
    {
        [$client, $users, $courses] = $this->stagingContext();
        $client->loginUser($users['a']);
        $client->request('GET', '/admin/course/'.$courses['b']->getId().'/history');
        self::assertResponseStatusCodeSame(403, 'Club A admin must not access club B course history.');

        $client->loginUser($users['b']);
        $client->request('GET', '/admin/course/'.$courses['a']->getId().'/history');
        self::assertResponseStatusCodeSame(403, 'Club B admin must not access club A course history.');
    }

    public function testClubAdministratorsCanReadTheirOwnCourseHistory(): void
    {
        [$client, $users, $courses] = $this->stagingContext();
        $client->loginUser($users['a']);
        $client->request('GET', '/admin/course/'.$courses['a']->getId().'/history');
        self::assertResponseIsSuccessful();

        $client->loginUser($users['b']);
        $client->request('GET', '/admin/course/'.$courses['b']->getId().'/history');
        self::assertResponseIsSuccessful();
    }

    public function testSuperAdminCanReadBothAndOrdinaryUserCannot(): void
    {
        [$client, $users, $courses] = $this->stagingContext();
        foreach ($courses as $course) {
            $client->loginUser($users['super']);
            $client->request('GET', '/admin/course/'.$course->getId().'/history');
            self::assertResponseIsSuccessful();

            $client->loginUser($users['user']);
            $client->request('GET', '/admin/course/'.$course->getId().'/history');
            self::assertResponseStatusCodeSame(403);
        }
    }

    /** @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: array<string, User>, 2: array<string, Courses>} */
    private function stagingContext(): array
    {
        $config = $this->fixtures();
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $actualDatabase = (string) $em->getConnection()->fetchOne('SELECT DATABASE()');
        self::assertSame($config['DICOMA_TEST_EXPECTED_DATABASE'], $actualDatabase, 'Wrong test database.');
        self::assertNotSame('h20292_dicoma', $actualDatabase, 'Production database is forbidden.');
        $userRepository = $em->getRepository(User::class);
        $users = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL', 'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL', 'user' => 'DICOMA_TEST_ROLE_USER_EMAIL'] as $key => $env) {
            $users[$key] = $userRepository->findOneBy(['email' => $config[$env]]);
            self::assertInstanceOf(User::class, $users[$key], 'Missing fixture user: '.$env);
        }
        $courses = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_COURSE_ID', 'b' => 'DICOMA_TEST_CLUB_B_COURSE_ID'] as $key => $env) {
            $courses[$key] = $em->getRepository(Courses::class)->find((int) $config[$env]);
            self::assertInstanceOf(Courses::class, $courses[$key], 'Missing fixture course: '.$env);
        }
        $clubA = $users['a']->getMember()?->getClub();
        $clubB = $users['b']->getMember()?->getClub();
        self::assertNotNull($clubA, 'Club A admin must have a club.');
        self::assertNotNull($clubB, 'Club B admin must have a club.');
        self::assertNotNull($clubA->getId());
        self::assertNotNull($clubB->getId());
        self::assertNotSame($clubA->getId(), $clubB->getId(), 'Fixtures must represent two different clubs.');
        self::assertSame($clubA->getId(), $courses['a']->getClub()?->getId());
        self::assertSame($clubB->getId(), $courses['b']->getClub()?->getId());
        foreach (['a', 'b'] as $key) {
            self::assertContains('ROLE_ADMIN', $users[$key]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$key]->getRoles());
        }
        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        self::assertNotContains('ROLE_ADMIN', $users['user']->getRoles());
        self::assertNotContains('ROLE_SUPER_ADMIN', $users['user']->getRoles());
        return [$client, $users, $courses];
    }
}
