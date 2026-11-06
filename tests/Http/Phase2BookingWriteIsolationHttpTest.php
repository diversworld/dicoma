<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** Destructive HTTP security probes: ONLY against the isolated DDEV db_test. */
final class Phase2BookingWriteIsolationHttpTest extends WebTestCase
{
    public function testRetiredAndCanonicalWritesCannotModifyForeignBooking(): void
    {
        $this->requireSafeEnvironmentFlags();
        $client = static::createClient();
        $this->assertSafeDatabase();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $users = $this->loadAdmins($em);
        $super = $this->loadSuperAdmin($em);
        $ids = $this->fixtureIds($db);

        foreach (['a' => 'b', 'b' => 'a'] as $actor => $foreign) {
            $client->loginUser($users[$actor]);

            $client->request('GET', '/booking/'.$ids[$actor]);
            self::assertSame(200, $client->getResponse()->getStatusCode(), 'Eigene Buchung muss erreichbar sein.');

            $before = $this->snapshot($db);
            $foreignId = $ids[$foreign];

            $client->request('POST', '/booking/'.$foreignId.'/edit', [
                'booking' => ['notes' => 'PHASE2 FORGED FOREIGN EDIT'],
            ]);
            self::assertSame(410, $client->getResponse()->getStatusCode(), 'Legacy edit must be retired.');
            self::assertSame($before, $this->snapshot($db), 'Foreign edit POST changed bookings.');

            $token = $this->validDeleteCsrfToken($client, 'delete'.$foreignId);
            $client->request('POST', '/booking/'.$foreignId, ['_token' => $token]);
            self::assertSame(410, $client->getResponse()->getStatusCode(), 'Legacy delete must be retired.');
            self::assertSame($before, $this->snapshot($db), 'Legacy delete changed bookings.');

            $client->loginUser($super);
            $crawler = $client->request('GET', '/admin/de/booking/'.$foreignId.'/edit');
            self::assertResponseIsSuccessful();
            $data = $this->bookingFormValues($crawler);
            $data['Booking']['notes'] = 'PHASE2 FORGED FOREIGN EDIT';
            $client->loginUser($users[$actor]);
            $client->request('POST', '/admin/de/booking/'.$foreignId.'/edit', $data);
            self::assertResponseStatusCodeSame(403);
            self::assertSame($before, $this->snapshot($db), 'Canonical foreign edit changed bookings.');

            $token = $this->validDeleteCsrfToken($client, 'ea-delete');
            $client->request('POST', '/admin/de/booking/'.$foreignId.'/delete', ['token' => $token]);
            self::assertResponseStatusCodeSame(403);
            self::assertSame($before, $this->snapshot($db), 'Canonical foreign delete changed bookings.');
        }
    }

    public function testClubAdminsCannotSubmitCanonicalFormsWithForgedAssociations(): void
    {
        $this->requireSafeEnvironmentFlags();
        $client = static::createClient();
        $this->assertSafeDatabase();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $users = $this->loadAdmins($em);
        $super = $this->loadSuperAdmin($em);
        $ids = $this->fixtureIds($db);

        foreach (['a' => 'b', 'b' => 'a'] as $actor => $foreign) {
            $before = $this->snapshot($db);
            $foreignSchedule = $foreign === 'a' ? 1 : 2;
            $foreignMember = $foreign === 'a' ? 1 : 2;

            foreach (['/admin/de/booking/'.$ids[$actor].'/edit', '/admin/de/booking/new'] as $path) {
                $client->loginUser($super);
                $crawler = $client->request('GET', $path);
                self::assertResponseIsSuccessful();
                $data = $this->bookingFormValues($crawler);
                $data['Booking']['schedule'] = (string) $foreignSchedule;
                $data['Booking']['member'] = (string) $foreignMember;
                $client->loginUser($users[$actor]);
                $client->request('POST', $path, $data);
                self::assertResponseStatusCodeSame(403);
                self::assertSame($before, $this->snapshot($db), 'Forged association changed booking rows: '.$path);
            }
        }
    }

    private function bookingFormValues(\Symfony\Component\DomCrawler\Crawler $crawler): array
    {
        $forms = $crawler->filter('form[name="Booking"]');
        self::assertCount(1, $forms);
        $values = $forms->form()->getPhpValues();
        self::assertNotEmpty($values['Booking']['_token'] ?? null, 'A real form CSRF token is required.');
        return $values;
    }

    private function validDeleteCsrfToken(KernelBrowser $client, string $tokenId): string
    {
        $container = static::getContainer();
        $session = $container->get('session.factory')->createSession();

        // loginUser() legt die Authentifizierung bereits in der Client-Session ab.
        // Daher nicht unbemerkt eine neue, leere Session verwenden.
        $cookie = $client->getCookieJar()->get($session->getName());
        self::assertNotNull($cookie, 'Session-Cookie nach loginUser() fehlt.');
        $session->setId($cookie->getValue());
        $session->start();

        $stack = $container->get(RequestStack::class);
        $request = Request::create('/');
        $request->setSession($session);
        $stack->push($request);

        try {
            $csrf = $container->get(CsrfTokenManagerInterface::class);
            $token = $csrf->getToken($tokenId)->getValue();
        } finally {
            $stack->pop();
            $session->save();
        }

        self::assertIsString($token);
        self::assertNotSame('', $token);
        return $token;
    }

    private function requireSafeEnvironmentFlags(): void
    {
        $env = (string) ($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV'));
        if ($env !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1'
            || getenv('DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED') !== '1') {
            self::markTestSkipped('APP_ENV=test and BOTH explicit DB/destructive confirmations required.');
        }
        self::assertSame('db_test', getenv('DICOMA_TEST_EXPECTED_DATABASE'));
    }

    private function assertSafeDatabase(): void
    {
        $db = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame('db_test', $db->getParams()['host'] ?? null);
        self::assertSame('db_test', $db->fetchOne('SELECT DATABASE()'));
    }

    /** @return array{a:User,b:User} */
    private function loadAdmins(EntityManagerInterface $em): array
    {
        $result = [];
        foreach (['a' => 1, 'b' => 2] as $key => $clubId) {
            $email = trim((string) getenv('DICOMA_TEST_CLUB_'.strtoupper($key).'_ADMIN_EMAIL'));
            self::assertNotSame('', $email);
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            self::assertInstanceOf(User::class, $user);
            self::assertContains('ROLE_ADMIN', $user->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles());
            self::assertSame($clubId, $user->getMember()?->getClub()?->getId());
            $result[$key] = $user;
        }
        return $result;
    }

    private function loadSuperAdmin(EntityManagerInterface $em): User
    {
        $email = trim((string) getenv('DICOMA_TEST_SUPER_ADMIN_EMAIL'));
        self::assertNotSame('', $email);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        self::assertContains('ROLE_SUPER_ADMIN', $user->getRoles());
        return $user;
    }

    /** @return array{a:int,b:int} */
    private function fixtureIds(Connection $db): array
    {
        $rows = $db->fetchAllAssociative("SELECT b.id, b.bookingnumber, b.schedule_id, b.students_id, m.club_id AS member_club, c.club_id AS course_club FROM booking b JOIN member m ON m.id = b.students_id JOIN schedule s ON s.id = b.schedule_id JOIN courses c ON c.id = s.courses_id WHERE b.bookingnumber IN ('PHASE2-TEST-A', 'PHASE2-TEST-B')");
        self::assertCount(2, $rows);
        $ids = [];
        foreach ($rows as $row) {
            $key = match ($row['bookingnumber']) {
                'PHASE2-TEST-A' => 'a',
                'PHASE2-TEST-B' => 'b',
                default => null,
            };
            self::assertNotNull($key);
            $expected = $key === 'a' ? 1 : 2;
            self::assertSame($expected, (int) $row['member_club']);
            self::assertSame($expected, (int) $row['course_club']);
            self::assertSame($expected, (int) $row['schedule_id']);
            self::assertSame($expected, (int) $row['students_id']);
            $ids[$key] = (int) $row['id'];
        }
        self::assertArrayHasKey('a', $ids);
        self::assertArrayHasKey('b', $ids);
        return $ids;
    }

    /** Snapshot ALL bookings, so inserts, deletes and updates are detectable. */
    private function snapshot(Connection $db): array
    {
        return $db->fetchAllAssociative('SELECT * FROM booking ORDER BY id');
    }
}
