<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Entity\Member;
use App\Entity\User;
use App\Security\Voter\MemberManagementVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Checks the authorization used by write handlers without submitting a mutation.
 * This does not replace valid-CSRF end-to-end POST tests on disposable fixtures.
 */
final class Phase2MemberWriteAuthorizationTest extends WebTestCase
{
    public function testClubAdminsMayManageOwnButNotForeignMembers(): void
    {
        [$client, $users, $members] = $this->fixtures();
        foreach (['a' => 'b', 'b' => 'a'] as $own => $foreign) {
            $client->loginUser($users[$own]);
            $client->request('GET', '/admin/course/999999999/history');
            $checker = static::getContainer()->get(AuthorizationCheckerInterface::class);
            self::assertTrue($checker->isGranted(MemberManagementVoter::MANAGE, $members[$own]));
            self::assertFalse($checker->isGranted(MemberManagementVoter::MANAGE, $members[$foreign]));
        }
    }

    public function testSuperAdminMayManageBothAndNormalUserNeither(): void
    {
        [$client, $users, $members] = $this->fixtures();
        foreach (['super' => true, 'user' => false] as $actor => $expected) {
            $client->loginUser($users[$actor]);
            $client->request('GET', '/admin/course/999999999/history');
            $checker = static::getContainer()->get(AuthorizationCheckerInterface::class);
            foreach ($members as $member) {
                self::assertSame($expected, $checker->isGranted(MemberManagementVoter::MANAGE, $member));
            }
        }
    }

    private function fixtures(): array
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'test'
            || getenv('DICOMA_PHASE2_HTTP_TEST_DB_CONFIRMED') !== '1') {
            self::markTestSkipped('Requires APP_ENV=test and explicit test database confirmation.');
        }
        $keys = [
            'a' => 'DICOMA_TEST_CLUB_A_ADMIN_EMAIL',
            'b' => 'DICOMA_TEST_CLUB_B_ADMIN_EMAIL',
            'super' => 'DICOMA_TEST_SUPER_ADMIN_EMAIL',
            'user' => 'DICOMA_TEST_ROLE_USER_EMAIL',
        ];
        foreach ($keys as $env) {
            if (trim((string) getenv($env)) === '') {
                self::markTestSkipped('Missing '.$env);
            }
        }
        $expectedDb = trim((string) getenv('DICOMA_TEST_EXPECTED_DATABASE'));
        if ($expectedDb !== 'h20292_dicoma_test') {
            self::markTestSkipped('Explicit named test database required.');
        }
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($expectedDb, (string) $em->getConnection()->fetchOne('SELECT DATABASE()'));
        $users = [];
        foreach ($keys as $key => $env) {
            $users[$key] = $em->getRepository(User::class)->findOneBy(['email' => getenv($env)]);
            self::assertInstanceOf(User::class, $users[$key]);
        }
        $members = ['a' => $users['a']->getMember(), 'b' => $users['b']->getMember()];
        foreach ($members as $member) {
            self::assertInstanceOf(Member::class, $member);
            self::assertNotNull($member->getClub()?->getId());
        }
        self::assertNotSame($members['a']->getClub()->getId(), $members['b']->getClub()->getId());
        foreach (['a', 'b'] as $key) {
            self::assertContains('ROLE_ADMIN', $users[$key]->getRoles());
            self::assertNotContains('ROLE_SUPER_ADMIN', $users[$key]->getRoles());
        }
        self::assertContains('ROLE_SUPER_ADMIN', $users['super']->getRoles());
        self::assertNotContains('ROLE_ADMIN', $users['user']->getRoles());
        return [$client, $users, $members];
    }
}
