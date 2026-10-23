<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Read-only regression test for cross-club booking details and edit forms. */
final class Phase2BookingIsolationHttpTest extends WebTestCase
{
    public function testForeignBookingShowAndEditAreForbidden(): void
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1') {
            self::markTestSkipped('APP_ENV=test and explicit HTTP test DB confirmation required.');
        }

        $expected = trim((string) getenv('DICOMA_TEST_EXPECTED_DATABASE'));
        
        self::assertSame('db_test', $expected, 'Only the isolated DDEV db_test is permitted.');
        
        $client = static::createClient();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        
        self::assertSame('db_test', (string) $db->fetchOne('SELECT DATABASE()'));
        self::assertSame('db_test', $db->getParams()['host'] ?? null);

        $users = [];
        
        foreach (['a', 'b'] as $key) {
            $email = trim((string) getenv('DICOMA_TEST_CLUB_'.strtoupper($key).'_ADMIN_EMAIL'));
        
            self::assertNotSame('', $email);
        
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        
            self::assertInstanceOf(User::class, $user);
            self::assertContains('ROLE_ADMIN', $user->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $user->getRoles());
            self::assertSame($key === 'a' ? 1 : 2, $user->getMember()?->getClub()?->getId());
            $users[$key] = $user;
        }

        $rows = $db->fetchAllAssociative("SELECT b.id, b.bookingnumber, m.club_id AS member_club, c.club_id AS course_club FROM booking b JOIN member m ON m.id = b.students_id JOIN schedule s ON s.id = b.schedule_id JOIN courses c ON c.id = s.courses_id WHERE b.bookingnumber IN ('PHASE2-TEST-A', 'PHASE2-TEST-B')");
        
        self::assertCount(2, $rows, 'Expected exactly two prepared fixture bookings.');
        $ids = [];
        
        foreach ($rows as $row) {
            $key = $row['bookingnumber'] === 'PHASE2-TEST-A' ? 'a' : ($row['bookingnumber'] === 'PHASE2-TEST-B' ? 'b' : null);
        
            self::assertNotNull($key);
            self::assertSame($key === 'a' ? 1 : 2, (int) $row['member_club']);
            self::assertSame($key === 'a' ? 1 : 2, (int) $row['course_club']);
        
            $ids[$key] = (int) $row['id'];
        }

        foreach (['a' => 'b', 'b' => 'a'] as $actor => $foreign) {
            $client->loginUser($users[$actor]);

            foreach (['/booking/%d', '/booking/%d/edit'] as $pattern) {
                $client->request('GET', sprintf($pattern, $ids[$foreign]));
            
                self::assertSame(403, $client->getResponse()->getStatusCode(), sprintf('%s admin must not read booking of club %s at %s', $actor, $foreign, $pattern));
            }
        }
    }
}
