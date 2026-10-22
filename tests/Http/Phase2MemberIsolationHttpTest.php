<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Controller\Admin\MemberCrudController;
use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Read-only HTTP checks against the existing Phase-2 staging fixtures.
 * Does not submit edit or delete forms and never writes application records.
 */
final class Phase2MemberIsolationHttpTest extends WebTestCase
{
    public function testClubAdminsCannotReadOrOpenEditFormForForeignMembers(): void
    {
        [$client, $users, $members] = $this->context();
        foreach ([['a', 'b'], ['b', 'a']] as [$actor, $target]) {
            $client->loginUser($users[$actor]);
            foreach ([Crud::PAGE_DETAIL, Crud::PAGE_EDIT] as $action) {
                $client->request('GET', $this->url($action, $members[$target]->getId()));
                self::assertResponseStatusCodeSame(403, sprintf('%s must not access %s member %s.', $actor, $action, $target));
            }
        }
    }

    public function testClubAdminsCanReadAndOpenEditFormForOwnMembers(): void
    {
        [$client, $users, $members] = $this->context();
        foreach (['a', 'b'] as $actor) {
            $client->loginUser($users[$actor]);
            foreach ([Crud::PAGE_DETAIL, Crud::PAGE_EDIT] as $action) {
                $client->request('GET', $this->url($action, $members[$actor]->getId()));
                self::assertResponseIsSuccessful();
            }
        }
    }

    public function testSuperAdminCanReadBothAndOrdinaryUserCannot(): void
    {
        [$client, $users, $members] = $this->context();
        foreach ($members as $member) {
            foreach ([Crud::PAGE_DETAIL, Crud::PAGE_EDIT] as $action) {
                $client->loginUser($users['super']);
                $client->request('GET', $this->url($action, $member->getId()));
                self::assertResponseIsSuccessful();

                $client->loginUser($users['user']);
                $client->request('GET', $this->url($action, $member->getId()));
                self::assertResponseStatusCodeSame(403);
            }
        }
    }

    private function url(string $action, int $memberId): string
    {
        /** @var AdminUrlGenerator $generator */
        $generator = static::getContainer()->get(AdminUrlGenerator::class);

        return (clone $generator)->unsetAll()
            ->setController(MemberCrudController::class)
            ->setAction($action)
            ->setEntityId($memberId)
            ->generateUrl();
    }

    /** @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: array<string, User>, 2: array<string, Member>} */
    private function context(): array
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1') {
            self::markTestSkipped('Requires APP_ENV=test and explicit staging database confirmation.');
        }

        $config = [];
        foreach ([
            'DICOMA_TEST_EXPECTED_DATABASE',
            'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'DICOMA_TEST_ROLE_USER_EMAIL',
        ] as $key) {
            $config[$key] = trim((string) getenv($key));
            if ($config[$key] === '') {
                self::markTestSkipped('Missing fixture configuration: '.$key);
            }
        }

        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $database = (string) $em->getConnection()->fetchOne('SELECT DATABASE()');
        self::assertSame($config['DICOMA_TEST_EXPECTED_DATABASE'], $database);
        self::assertNotSame('h20292_dicoma', $database, 'Production database is forbidden.');

        $users = [];
        foreach ([
            'a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'user' => 'DICOMA_TEST_ROLE_USER_EMAIL',
        ] as $name => $key) {
            $users[$name] = $em->getRepository(User::class)->findOneBy(['email' => $config[$key]]);
            self::assertInstanceOf(User::class, $users[$name], 'Missing fixture: '.$key);
        }

        $members = [];
        foreach (['a', 'b'] as $name) {
            $members[$name] = $users[$name]->getMember();
            self::assertInstanceOf(Member::class, $members[$name]);
            self::assertNotNull($members[$name]->getId());
            self::assertNotNull($members[$name]->getClub()?->getId());
            self::assertContains('ROLE_ADMIN', $users[$name]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$name]->getRoles());
        }
        self::assertNotSame($members['a']->getClub()->getId(), $members['b']->getClub()->getId());
        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        self::assertNotContains('ROLE_ADMIN', $users['user']->getRoles());
        self::assertNotContains('ROLE_SUPER_ADMIN', $users['user']->getRoles());

        return [$client, $users, $members];
    }
}
