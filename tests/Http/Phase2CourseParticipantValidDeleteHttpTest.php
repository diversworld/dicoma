<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Controller\Admin\CourseParticipantCrudController;
use App\Entity\CourseParticipant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Sicherheitsnachweis: fremde Kursteilnehmer bleiben trotz gueltigem
 * EasyAdmin-Loesch-CSRF-Token erhalten. Ausschliesslich isolierte Test-DB.
 */
final class Phase2CourseParticipantValidDeleteHttpTest extends WebTestCase
{
    public function testForeignParticipantCannotBeDeletedWithValidCsrf(): void
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1'
            || getenv('DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED') !== '1') {
            self::markTestSkipped('Requires APP_ENV=test and both explicit database/destructive test confirmations.');
        }

        $expected = trim((string) getenv('DICOMA_TEST_EXPECTED_DATABASE'));
        self::assertContains($expected, ['db_test', 'h20292_dicoma_test']);
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertSame($expected, (string) $connection->fetchOne('SELECT DATABASE()'));
        if ($expected === 'db_test') {
            $params = $connection->getParams();
            self::assertSame('db_test', $params['host'] ?? null, 'DDEV must use the isolated db_test server.');
        }

        $users = [];
        $participants = [];
        foreach (['a', 'b'] as $key) {
            $email = trim((string) getenv('DICOMA_TEST_CLUB_'.strtoupper($key).'_ADMIN_EMAIL'));
            $rawId = trim((string) getenv('DICOMA_TEST_CLUB_'.strtoupper($key).'_PARTICIPANT_ID'));
            self::assertNotSame('', $email);
            self::assertTrue(ctype_digit($rawId) && (int) $rawId > 0);
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => $email]);
            $participants[$key] = $em->getRepository(CourseParticipant::class)->find((int) $rawId);
            self::assertInstanceOf(User::class, $users[$key]);
            self::assertInstanceOf(CourseParticipant::class, $participants[$key]);
            $clubId = $users[$key]->getMember()?->getClub()?->getId();
            self::assertNotNull($clubId);
            self::assertSame($clubId, $participants[$key]->getCourse()?->getClub()?->getId());
            self::assertSame($clubId, $participants[$key]->getMember()?->getClub()?->getId());
            self::assertContains('ROLE_ADMIN', $users[$key]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$key]->getRoles());
        }
        self::assertNotSame($participants['a']->getId(), $participants['b']->getId());
        self::assertNotSame($users['a']->getMember()?->getClub()?->getId(), $users['b']->getMember()?->getClub()?->getId());

        foreach (['a' => 'b', 'b' => 'a'] as $actor => $target) {
            $id = $participants[$target]->getId();
            self::assertNotNull($id);
            $before = $this->snapshot($em, $id);
            $client->loginUser($users[$actor]);
            $token = $this->validDeleteToken($client);
            self::assertNotSame('', $token);
            $generator = static::getContainer()->get(AdminUrlGenerator::class);
            $url = (clone $generator)->unsetAll()
                ->setController(CourseParticipantCrudController::class)
                ->setAction(Action::DELETE)->setEntityId($id)->generateUrl();
            $client->request('POST', $url, ['token' => $token]);
            self::assertResponseStatusCodeSame(403);
            $em->clear();
            self::assertSame($before, $this->snapshot($em, $id), 'Foreign participant row must remain unchanged.');
        }
    }

    private function validDeleteToken(KernelBrowser $client): string
    {
        $container = static::getContainer();
        $session = $container->get('session.factory')->createSession();
        $cookie = $client->getCookieJar()->get($session->getName());
        self::assertNotNull($cookie, 'loginUser() must create a session cookie.');
        $session->setId($cookie->getValue());
        $request = Request::create('/');
        $request->setSession($session);
        $stack = $container->get(RequestStack::class);
        $stack->push($request);
        try {
            $token = (string) $container->get(CsrfTokenManagerInterface::class)->getToken('ea-delete');
            $session->save();
            return $token;
        } finally {
            $stack->pop();
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(EntityManagerInterface $em, int $id): array
    {
        $row = $em->getConnection()->fetchAssociative('SELECT * FROM course_participant WHERE id = :id', ['id' => $id]);
        self::assertIsArray($row);
        return $row;
    }
}
