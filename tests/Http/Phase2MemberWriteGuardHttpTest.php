<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Controller\Admin\MemberCrudController;
use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Non-destructive regression probes. Never sends a valid edit/delete form.
 * A successful run does NOT establish authorization of valid write requests.
 */
final class Phase2MemberWriteGuardHttpTest extends WebTestCase
{
    public function testForeignMemberCannotBeDeletedWithInvalidCsrfToken(): void
    {
        [$client, $users, $members, $em] = $this->context();
        foreach ([['a', 'b'], ['b', 'a']] as [$actor, $target]) {
            $memberId = $members[$target]->getId();
            $before = $this->snapshot($em, $memberId);
            $client->loginUser($users[$actor]);
            $client->request('POST', $this->url(Action::DELETE, $memberId), [
                'token' => 'invalid-phase2-csrf-token',
            ]);
            self::assertNotSame(500, $client->getResponse()->getStatusCode(), 'Unexpected server error.');
            $em->clear();
            self::assertSame($before, $this->snapshot($em, $memberId), 'Foreign member changed after invalid-CSRF delete request.');
        }
    }

    public function testForeignMemberCannotBeChangedWithInvalidEditPayload(): void
    {
        [$client, $users, $members, $em] = $this->context();
        foreach ([['a', 'b'], ['b', 'a']] as [$actor, $target]) {
            $memberId = $members[$target]->getId();
            $before = $this->snapshot($em, $memberId);
            $client->loginUser($users[$actor]);
            // Deliberately NOT a valid EasyAdmin form submission: no valid CSRF token.
            $client->request('POST', $this->url(Crud::PAGE_EDIT, $memberId), [
                'Member' => ['club' => (string) $members[$actor]->getClub()->getId()],
            ]);
            self::assertNotSame(500, $client->getResponse()->getStatusCode(), 'Unexpected server error.');
            $em->clear();
            self::assertSame($before, $this->snapshot($em, $memberId), 'Foreign member changed after invalid edit request.');
        }
    }

    private function snapshot(EntityManagerInterface $em, int $id): array
    {
        $row = $em->getConnection()->fetchAssociative(
            'SELECT m.*, p.firstname, p.lastname, p.birthday, p.email, p.mobile, p.phone, p.street, p.postal_code, p.city FROM member m JOIN person p ON p.id = m.person_id WHERE m.id = :id',
            ['id' => $id]
        );
        self::assertIsArray($row, 'Fixture member unexpectedly missing.');
        return $row;
    }

    private function url(string $action, int $id): string
    {
        $generator = static::getContainer()->get(AdminUrlGenerator::class);
        return (clone $generator)->unsetAll()->setController(MemberCrudController::class)
            ->setAction($action)->setEntityId($id)->generateUrl();
    }

    private function context(): array
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1') {
            self::markTestSkipped('APP_ENV=test and explicit test DB confirmation required.');
        }
        $required = [
            'DICOMA_TEST_EXPECTED_DATABASE',
            'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
        ];
        $config = [];
        foreach ($required as $key) {
            $config[$key] = trim((string) getenv($key));
            if ($config[$key] === '') {
                self::markTestSkipped('Missing '.$key);
            }
        }
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $db = (string) $em->getConnection()->fetchOne('SELECT DATABASE()');
        self::assertSame($config['DICOMA_TEST_EXPECTED_DATABASE'], $db);
        self::assertSame('db_test', $db, 'This probe is restricted to the named test DB.');
        $users = [];
        $members = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL', 'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL'] as $key => $env) {
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => $config[$env]]);
            self::assertInstanceOf(User::class, $users[$key]);
            $members[$key] = $users[$key]->getMember();
            self::assertInstanceOf(Member::class, $members[$key]);
            self::assertNotNull($members[$key]->getId());
            self::assertNotNull($members[$key]->getClub()?->getId());
            self::assertContains('ROLE_ADMIN', $users[$key]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$key]->getRoles());
        }
        self::assertNotSame($members['a']->getClub()->getId(), $members['b']->getClub()->getId());
        return [$client, $users, $members, $em];
    }
}
