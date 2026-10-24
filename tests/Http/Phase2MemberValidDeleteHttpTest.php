<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Controller\Admin\MemberCrudController;
use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Potentially mutating E2E test. Requires dedicated disposable members.
 * Never run against real membership records or production.
 */
final class Phase2MemberValidDeleteHttpTest extends WebTestCase
{
    public function testForeignMemberRejectsValidDeleteWithCsrfToken(): void
    {
        $config = $this->configuration();
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = (string) $em->getConnection()->fetchOne('SELECT DATABASE()');
        self::assertSame('db_test', $db);
        self::assertSame($config['DICOMA_TEST_EXPECTED_DATABASE'], $db);

        $users = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL', 'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL', 'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL'] as $key => $env) {
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => $config[$env]]);
            self::assertInstanceOf(User::class, $users[$key]);
        }

        $ids = [
            'a' => (int) $config['DICOMA_TEST_DISPOSABLE_MEMBER_A_ID'],
            'b' => (int) $config['DICOMA_TEST_DISPOSABLE_MEMBER_B_ID'],
        ];
        self::assertNotSame($ids['a'], $ids['b']);

        $members = [];
        foreach ($ids as $key => $id) {
            $members[$key] = $em->getRepository(Member::class)->find($id);
            self::assertInstanceOf(Member::class, $members[$key]);
            self::assertSame($users[$key]->getMember()?->getClub()?->getId(), $members[$key]->getClub()?->getId());
            self::assertNull($members[$key]->getUser(), 'Disposable member must not have a user account.');
            foreach ($users as $user) {
                self::assertNotSame($user->getMember()?->getId(), $id, 'Never target a fixture login member.');
            }
        }
        self::assertNotSame($members['a']->getClub()?->getId(), $members['b']->getClub()?->getId());
        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        foreach (['a', 'b'] as $key) {
            self::assertContains('ROLE_ADMIN', $users[$key]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$key]->getRoles());
        }

        foreach (['a' => 'b', 'b' => 'a'] as $actor => $target) {
            $id = $ids[$target];
            $before = $this->snapshot($em, $id);
            $client->loginUser($users[$actor]);

            // loginUser() creates a session and puts its cookie in the browser.
            // The CSRF manager, however, needs that same session on a current Request.
            $token = $this->csrfTokenForBrowserSession($client);
            self::assertNotSame('', $token);

            $client->request('POST', $this->url(Action::DELETE, $id), ['token' => $token]);
            self::assertResponseStatusCodeSame(403, 'Cross-club deletion must be forbidden.');
            $em->clear();
            self::assertSame($before, $this->snapshot($em, $id), 'Disposable member changed/deleted. Restore test DB backup.');
        }
    }

    private function csrfTokenForBrowserSession(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $container = static::getContainer();
        $session = $container->get('session.factory')->createSession();
        $cookie = $client->getCookieJar()->get($session->getName());
        self::assertNotNull($cookie, 'loginUser() did not create the expected session cookie.');
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

    private function snapshot(EntityManagerInterface $em, int $id): array
    {
        $row = $em->getConnection()->fetchAssociative(
            'SELECT m.*, p.firstname, p.lastname, p.birthday, p.email, p.mobile, p.phone, p.street, p.postal_code, p.city FROM member m JOIN person p ON p.id = m.person_id WHERE m.id = :id',
            ['id' => $id]
        );
        self::assertIsArray($row);

        return $row;
    }

    private function url(string $action, int $id): string
    {
        $generator = static::getContainer()->get(AdminUrlGenerator::class);

        return (clone $generator)->unsetAll()->setController(MemberCrudController::class)
            ->setAction($action)->setEntityId($id)->generateUrl();
    }

    /** @return array<string, string> */
    private function configuration(): array
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1'
            || getenv('DICOMA_PHASE2_DESTRUCTIVE_TEST_CONFIRMED') !== '1') {
            self::markTestSkipped('Requires APP_ENV=test, confirmed test DB and explicit destructive-test opt-in.');
        }

        $keys = [
            'DICOMA_TEST_EXPECTED_DATABASE',
            'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'DICOMA_TEST_DISPOSABLE_MEMBER_A_ID',
            'DICOMA_TEST_DISPOSABLE_MEMBER_B_ID',
        ];
        $config = [];
        foreach ($keys as $key) {
            $config[$key] = trim((string) getenv($key));
            if ($config[$key] === '') {
                self::markTestSkipped('Missing '.$key);
            }
        }
        self::assertSame('db_test', $config['DICOMA_TEST_EXPECTED_DATABASE']);
        foreach (['DICOMA_TEST_DISPOSABLE_MEMBER_A_ID', 'DICOMA_TEST_DISPOSABLE_MEMBER_B_ID'] as $key) {
            self::assertTrue(ctype_digit($config[$key]) && (int) $config[$key] > 0, 'Invalid disposable ID: '.$key);
        }

        return $config;
    }
}
