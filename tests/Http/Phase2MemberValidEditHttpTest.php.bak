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
 * Potentially mutating E2E test. Requires dedicated disposable members.
 * Never run against real membership records or production.
 */
final class Phase2MemberValidEditHttpTest extends WebTestCase
{
    public function testForeignMemberRejectsValidFormSubmission(): void
    {
        $config = $this->configuration();
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('h20292_dicoma_test', (string) $em->getConnection()->fetchOne('SELECT DATABASE()'));
        self::assertSame($config['DICOMA_TEST_EXPECTED_DATABASE'], (string) $em->getConnection()->fetchOne('SELECT DATABASE()'));

        $users = [];
        foreach (['a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL', 'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL', 'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL'] as $key => $env) {
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => $config[$env]]);
            self::assertInstanceOf(User::class, $users[$key]);
        }
        $ids = ['a' => (int) $config['DICOMA_TEST_DISPOSABLE_MEMBER_A_ID'], 'b' => (int) $config['DICOMA_TEST_DISPOSABLE_MEMBER_B_ID']];
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
            // Obtain an authentic, valid Symfony form (including CSRF token)
            // as a permitted super-admin in the same browser session.
            $client->loginUser($users['super']);
            $crawler = $client->request('GET', $this->url(Crud::PAGE_EDIT, $id));
            self::assertResponseIsSuccessful();
            $forms = $crawler->filter('form[name="Member"]');
            self::assertCount(1, $forms, 'Expected EasyAdmin Member edit form. No write request was sent.');
            $form = $forms->form();
            $formName = $forms->attr('name');
            self::assertSame('Member', $formName);
            $values = $form->getPhpValues();
            self::assertArrayHasKey('Member', $values);
            self::assertNotEmpty($values['Member']['_token'] ?? null, 'No CSRF token: refusing to submit.');
            $values['Member']['firstname'] = 'PHASE2_UNAUTHORIZED_EDIT_'.strtoupper($actor);

            // Change identity, retain the session token: this is a valid-CSRF
            // cross-club attempt, not a CSRF-negative probe.
            $client->loginUser($users[$actor]);
            $client->request('POST', $this->url(Crud::PAGE_EDIT, $id), $values);
            self::assertResponseStatusCodeSame(403, 'Foreign member edit must be denied.');
            $em->clear();
            self::assertSame($before, $this->snapshot($em, $id), 'Foreign member was modified. Restore test DB backup.');
        }
    }

    private function snapshot(EntityManagerInterface $em, int $id): array
    {
        $row = $em->getConnection()->fetchAssociative('SELECT * FROM member WHERE id = :id', ['id' => $id]);
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
        $keys = ['DICOMA_TEST_EXPECTED_DATABASE', 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'DICOMA_TEST_CLUB_B_ADMIN_EMAIL', 'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'DICOMA_TEST_DISPOSABLE_MEMBER_A_ID', 'DICOMA_TEST_DISPOSABLE_MEMBER_B_ID'];
        $config = [];
        foreach ($keys as $key) {
            $config[$key] = trim((string) getenv($key));
            if ($config[$key] === '') {
                self::markTestSkipped('Missing '.$key);
            }
        }
        self::assertSame('h20292_dicoma_test', $config['DICOMA_TEST_EXPECTED_DATABASE']);
        foreach (['DICOMA_TEST_DISPOSABLE_MEMBER_A_ID', 'DICOMA_TEST_DISPOSABLE_MEMBER_B_ID'] as $key) {
            self::assertTrue(ctype_digit($config[$key]) && (int) $config[$key] > 0, 'Invalid disposable ID: '.$key);
        }
        return $config;
    }
}
