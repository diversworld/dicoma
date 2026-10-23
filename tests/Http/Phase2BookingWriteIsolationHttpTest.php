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
    public function testForeignEditPostAndValidCsrfDeleteCannotModifyOtherClub(): void
    {
        $this->requireSafeEnvironmentFlags();
        $client = static::createClient();
        $this->assertSafeDatabase();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $users = $this->loadAdmins($em);
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
            self::assertSame(403, $client->getResponse()->getStatusCode(), 'Foreign edit POST must be forbidden.');
            self::assertSame($before, $this->snapshot($db), 'Foreign edit POST changed bookings.');

            // Der Token wird in derselben Session erzeugt, die der Testclient benutzt.
            // Ein gueltiger CSRF-Token darf die Vereinspruefung nicht umgehen.
            $token = $this->validDeleteCsrfToken($client, $foreignId);
            $client->request('POST', '/booking/'.$foreignId, ['_token' => $token]);
            self::assertSame(403, $client->getResponse()->getStatusCode(), 'Foreign delete with valid CSRF must be forbidden.');
            self::assertSame($before, $this->snapshot($db), 'Foreign delete changed bookings.');
        }
    }

    public function testForgedAssociationsOnOwnEditAndNewCannotBePersisted(): void
    {
        $this->requireSafeEnvironmentFlags();
        $client = static::createClient();
        $this->assertSafeDatabase();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $users = $this->loadAdmins($em);
        $ids = $this->fixtureIds($db);

        foreach (['a' => 'b', 'b' => 'a'] as $actor => $foreign) {
            $client->loginUser($users[$actor]);
            $before = $this->snapshot($db);
            $foreignSchedule = $foreign === 'a' ? 1 : 2;
            $foreignMember = $foreign === 'a' ? 1 : 2;

            foreach (['/booking/'.$ids[$actor].'/edit', '/booking/new'] as $path) {
                $crawler = $client->request('GET', $path);
                self::assertSame(200, $client->getResponse()->getStatusCode(), 'Expected accessible own form: '.$path);

                $forms = $crawler->filter('form[name="booking"]');
                if ($forms->count() === 0) {
                    $forms = $crawler->filter('form')->reduce(
                        static fn ($node) => $node->filter('[name="booking[schedule]"]')->count() > 0
                    );
                }

                self::assertGreaterThan(0, $forms->count(), 'Booking form missing: '.$path);
                $data = $forms->first()->form()->getPhpValues();
                self::assertArrayHasKey('booking', $data);

                // Vorhandene Formularwerte inklusive CSRF erhalten; nur Beziehungen manipulieren.
                $data['booking']['schedule'] = (string) $foreignSchedule;
                $data['booking']['member'] = (string) $foreignMember;
                $client->request('POST', $path, $data);

                $status = $client->getResponse()->getStatusCode();
                self::assertContains($status, [200, 403, 422], 'Forged association unexpectedly redirected or failed: '.$path.' ('.$status.')');
                self::assertSame($before, $this->snapshot($db), 'Forged association changed booking rows: '.$path);
            }
        }
    }

    private function validDeleteCsrfToken(KernelBrowser $client, int $bookingId): string
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
        $request = Request::create('/booking/'.$bookingId);
        $request->setSession($session);
        $stack->push($request);

        try {
            $csrf = $container->get(CsrfTokenManagerInterface::class);
            $token = $csrf->getToken('delete'.$bookingId)->getValue();
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
