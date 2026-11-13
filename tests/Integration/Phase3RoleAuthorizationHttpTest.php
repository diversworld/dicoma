<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\TankCheck;
use App\Entity\User;
use App\Enum\MembershipStatus;
use App\Security\DelegatedRoles;
use App\Service\MembershipService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase3RoleAuthorizationHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->em->getConnection()->executeStatement('CREATE TABLE equipment_cash_correction (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INT NOT NULL, source_type VARCHAR(20), source_id INT, direction VARCHAR(10), amount_cents INT, reason VARCHAR(500), created_by VARCHAR(180), created_at DATETIME)');
        $this->club = (new Club())->setName('Role Test Club');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testDelegationIsClubScopedAndRejectsEscalationCsrfAndStaleForms(): void
    {
        $admin = $this->account('role-admin', [User::ROLE_ADMIN], $this->club);
        $target = $this->account('role-target', [User::ROLE_USER, User::ROLE_MEMBER], $this->club);
        $protected = $this->account('role-protected', [User::ROLE_BOARD], $this->club);
        $unknown = $this->account('role-unknown', ['ROLE_CUSTOM'], $this->club);
        $inactive = $this->account('role-inactive', [User::ROLE_USER], $this->club, false);
        $ordinary = $this->account('role-ordinary', [User::ROLE_USER], $this->club);
        $foreignClub = (new Club())->setName('Foreign Role Club');
        $this->em->persist($foreignClub);
        $this->em->flush();
        $foreign = $this->account('role-foreign', [User::ROLE_USER], $foreignClub);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/settings');
        self::assertSelectorExists('a[href="/admin/roles"]');
        $this->client->request('GET', '/admin/roles');
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorTextContains('body', 'role-target');
        foreach (['role-admin', 'role-protected', 'role-unknown', 'role-inactive', 'role-foreign'] as $name) {
            self::assertSelectorTextNotContains('table', $name);
        }
        $this->client->request('GET', '/admin/de/delegated-user', ['query' => 'role-protected']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('table', 'role-protected');
        $this->client->request('GET', '/admin/de/delegated-user', ['query' => 'role-target']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'role-target');
        foreach ([
            '/admin/de/delegated-user/new',
            '/admin/de/delegated-user/'.$target->getId(),
            '/admin/de/delegated-user/'.$target->getId().'/edit',
            '/admin/de/delegated-user/'.$protected->getId(),
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/admin/de/delegated-user/'.$target->getId().'/delete');
        self::assertResponseStatusCodeSame(403);
        foreach ([$admin, $protected, $unknown, $inactive, $foreign] as $account) {
            $this->client->request('GET', '/admin/roles/'.$account->getId().'/edit');
            self::assertResponseStatusCodeSame(403);
        }
        $url = '/admin/roles/'.$target->getId().'/edit';
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="form[_token]"]')->attr('value');
        $snapshot = $crawler->filter('input[name="form[snapshot]"]')->attr('value');
        $validity = $this->emptyValidity(DelegatedRoles::ALLOWED);
        $this->client->request('POST', $url, ['form' => [
            'roles' => [User::ROLE_SUPER_ADMIN], 'validity' => $validity, 'snapshot' => $snapshot, '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, ['form' => [
            'roles' => [User::ROLE_EQUIPMENT_CASH], 'validity' => $validity, 'snapshot' => $snapshot, '_token' => 'invalid',
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame([User::ROLE_USER, User::ROLE_MEMBER], $this->em->find(User::class, $target->getId())->getStoredRoles());

        $this->client->request('POST', $url, ['form' => [
            'roles' => [User::ROLE_EQUIPMENT_MANAGER, User::ROLE_EQUIPMENT_CASH],
            'validity' => $validity, 'snapshot' => $snapshot, '_token' => $token,
        ]]);
        self::assertResponseRedirects('/admin/roles', 303);
        $updated = $this->em->find(User::class, $target->getId());
        self::assertEqualsCanonicalizing([User::ROLE_USER, User::ROLE_MEMBER, User::ROLE_EQUIPMENT_MANAGER, User::ROLE_EQUIPMENT_CASH], $updated->getStoredRoles());

        $this->client->request('POST', $url, ['form' => [
            'roles' => [User::ROLE_INSTRUCTOR], 'validity' => $validity, 'snapshot' => $snapshot, '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(409);
        self::assertContains(User::ROLE_EQUIPMENT_CASH, $this->em->find(User::class, $target->getId())->getStoredRoles());

        $crawler = $this->client->request('GET', $url);
        $this->client->request('POST', $url, ['form' => [
            'roles' => [], 'validity' => $validity, 'snapshot' => $crawler->filter('input[name="form[snapshot]"]')->attr('value'),
            '_token' => $crawler->filter('input[name="form[_token]"]')->attr('value'),
        ]]);
        self::assertResponseRedirects('/admin/roles', 303);
        self::assertSame([User::ROLE_USER, User::ROLE_MEMBER], $this->em->find(User::class, $target->getId())->getStoredRoles());

        $this->client->loginUser($ordinary);
        $this->client->request('GET', '/admin/roles');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/de/delegated-user');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/de/user');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEquipmentCashRequiresBothExplicitRoles(): void
    {
        $manager = $this->account('equipment-only', [User::ROLE_EQUIPMENT_MANAGER], $this->club);
        $cashOnly = $this->account('cash-only', [User::ROLE_EQUIPMENT_CASH], $this->club);
        $cashManager = $this->account('cash-manager', [User::ROLE_EQUIPMENT_MANAGER, User::ROLE_EQUIPMENT_CASH], $this->club);
        $hierarchy = static::getContainer()->get(RoleHierarchyInterface::class);
        self::assertNotContains(User::ROLE_TREASURER, $hierarchy->getReachableRoleNames([User::ROLE_EQUIPMENT_MANAGER]));
        self::assertNotContains(User::ROLE_EQUIPMENT_CASH, $hierarchy->getReachableRoleNames([User::ROLE_EQUIPMENT_MANAGER]));

        $this->client->loginUser($manager);
        $this->client->request('GET', '/equipment-manager');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/equipment-manager/cashbook"]');
        foreach ([
            '/equipment-manager/cashbook', '/equipment-manager/cashbook/day', '/equipment-manager/cashbook/day.csv',
            '/equipment-manager/cashbook/payouts', '/equipment-manager/cashbook/corrections',
            '/equipment-manager/cashbook/closing', '/equipment-manager/fill-billing',
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        foreach ([
            '/equipment-manager/loans/1/collect-fee', '/equipment-manager/tuv/booking/1/collect-payment',
            '/equipment-manager/fill-billing/1/collect', '/equipment-manager/cashbook/payouts',
            '/equipment-manager/cashbook/corrections', '/equipment-manager/cashbook/closing',
        ] as $url) {
            $this->client->request('POST', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->loginUser($cashOnly);
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($cashManager);
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/equipment-manager/fill-billing');
        self::assertResponseIsSuccessful();
    }

    public function testTargetPrivilegesAndMembershipAreRecheckedOnSubmit(): void
    {
        $admin = $this->account('recheck-admin', [User::ROLE_ADMIN], $this->club);
        $target = $this->account('recheck-target', [User::ROLE_USER], $this->club);
        $this->client->loginUser($admin);
        $url = '/admin/roles/'.$target->getId().'/edit';
        $crawler = $this->client->request('GET', $url);
        $token = $crawler->filter('input[name="form[_token]"]')->attr('value');
        $snapshot = $crawler->filter('input[name="form[snapshot]"]')->attr('value');
        $this->em->find(User::class, $target->getId())->setRoles([User::ROLE_BOARD]);
        $this->em->flush();
        $this->client->request('POST', $url, ['form' => [
            'roles' => [User::ROLE_INSTRUCTOR], 'snapshot' => $snapshot, '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([User::ROLE_BOARD], $this->em->find(User::class, $target->getId())->getStoredRoles());

        $this->em->find(User::class, $target->getId())->setRoles([User::ROLE_USER]);
        $this->em->flush();
        $crawler = $this->client->request('GET', $url);
        $token = $crawler->filter('input[name="form[_token]"]')->attr('value');
        $snapshot = $crawler->filter('input[name="form[snapshot]"]')->attr('value');
        $this->em->find(Member::class, $target->getMember()->getId())->setMembershipStatus(MembershipStatus::FORMER);
        $this->em->flush();
        $this->client->request('POST', $url, ['form' => [
            'roles' => [User::ROLE_INSTRUCTOR], 'snapshot' => $snapshot, '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([User::ROLE_USER], $this->em->find(User::class, $target->getId())->getStoredRoles());
    }

    public function testRevokedRoleInvalidatesExistingSession(): void
    {
        $manager = $this->account('revoked-manager', [User::ROLE_EQUIPMENT_MANAGER, User::ROLE_EQUIPMENT_CASH], $this->club);
        $this->client->loginUser($manager);
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseIsSuccessful();
        $manager->setRoles([User::ROLE_EQUIPMENT_MANAGER]);
        $this->em->flush();
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseRedirects('/login');
        $this->client->loginUser($manager);
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRoleValidityEditingRejectsInvalidOrOmittedWindowsAndInvalidatesSessions(): void
    {
        $admin = $this->account('validity-admin', [User::ROLE_ADMIN], $this->club);
        $target = $this->account('validity-target', [User::ROLE_EQUIPMENT_MANAGER, User::ROLE_EQUIPMENT_CASH], $this->club);
        $this->client->loginUser($admin);
        $url = '/admin/roles/'.$target->getId().'/edit';
        $crawler = $this->client->request('GET', $url);
        $token = $crawler->filter('input[name="form[_token]"]')->attr('value');
        $snapshot = $crawler->filter('input[name="form[snapshot]"]')->attr('value');
        $roles = [User::ROLE_EQUIPMENT_MANAGER, User::ROLE_EQUIPMENT_CASH];
        $validity = $this->emptyValidity(DelegatedRoles::ALLOWED);
        $validity[User::ROLE_EQUIPMENT_CASH] = ['startsAt' => '2027-02-02', 'endsAt' => '2027-02-01'];
        $this->client->request('POST', $url, ['form' => compact('roles', 'validity', 'snapshot') + ['_token' => $token]]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, ['form' => compact('roles', 'snapshot') + ['_token' => $token]]);
        self::assertResponseStatusCodeSame(422);
        $validity[User::ROLE_EQUIPMENT_CASH] = ['startsAt' => '', 'endsAt' => (new \DateTimeImmutable('yesterday'))->format('Y-m-d')];
        $this->client->request('POST', $url, ['form' => compact('roles', 'validity', 'snapshot') + ['_token' => $token]]);
        self::assertResponseRedirects('/admin/roles', 303);
        $updated = $this->em->find(User::class, $target->getId());
        self::assertContains(User::ROLE_EQUIPMENT_CASH, $updated->getStoredRoles());
        self::assertNotContains(User::ROLE_EQUIPMENT_CASH, $updated->getRoles());
        self::assertContains(User::ROLE_EQUIPMENT_MANAGER, $updated->getRoles());
        $validity[User::ROLE_EQUIPMENT_CASH] = ['startsAt' => '', 'endsAt' => ''];
        $this->client->request('POST', $url, ['form' => compact('roles', 'validity', 'snapshot') + ['_token' => $token]]);
        self::assertResponseStatusCodeSame(409);
        self::assertNotContains(User::ROLE_EQUIPMENT_CASH, $this->em->find(User::class, $target->getId())->getRoles());
        $this->client->loginUser($updated);
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanSetProtectedRoleDatesButClubAdminCannot(): void
    {
        $admin = $this->account('validity-club-admin', [User::ROLE_ADMIN], $this->club);
        $super = $this->account('validity-super-admin', [User::ROLE_SUPER_ADMIN], $this->club);
        $target = $this->account('validity-board', [User::ROLE_BOARD, User::ROLE_ADMIN], $this->club);
        $url = '/admin/roles/'.$target->getId().'/validity';
        $this->client->loginUser($admin);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($super);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        $validity = $this->emptyValidity([User::ROLE_BOARD, User::ROLE_ADMIN]);
        $validity[User::ROLE_BOARD]['endsAt'] = (new \DateTimeImmutable('yesterday'))->format('Y-m-d');
        $this->client->request('POST', $url, ['form' => [
            'validity' => $validity,
            'snapshot' => $crawler->filter('input[name="form[snapshot]"]')->attr('value'),
            '_token' => $crawler->filter('input[name="form[_token]"]')->attr('value'),
        ]]);
        self::assertResponseRedirects($url, 303);
        $updated = $this->em->find(User::class, $target->getId());
        self::assertNotContains(User::ROLE_BOARD, $updated->getRoles());
        self::assertContains(User::ROLE_ADMIN, $updated->getRoles());
        $crawler = $this->client->request('GET', '/admin/de/user/'.$target->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="User[roles][]"] option[value="ROLE_BOARD"][selected]');
        $form = $crawler->filter('form[name="User"]')->form();
        $this->client->submit($form, ['User[email]' => 'updated-validity@example.invalid']);
        self::assertResponseRedirects();
        $updated = $this->em->find(User::class, $target->getId());
        self::assertContains(User::ROLE_BOARD, $updated->getStoredRoles());
        self::assertNotContains(User::ROLE_BOARD, $updated->getRoles());
    }

    public function testChangedRoleValidityInvalidatesActiveSession(): void
    {
        $manager = $this->account('validity-session', [User::ROLE_EQUIPMENT_MANAGER, User::ROLE_EQUIPMENT_CASH], $this->club);
        $this->client->loginUser($manager);
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseIsSuccessful();
        $manager = $this->em->find(User::class, $manager->getId());
        $manager->setRoleValidity([
            User::ROLE_EQUIPMENT_CASH => ['startsAt' => null, 'endsAt' => (new \DateTimeImmutable('yesterday'))->format('Y-m-d')],
        ]);
        $this->em->flush();
        $this->client->request('GET', '/equipment-manager/cashbook');
        self::assertResponseRedirects('/login');
    }

    public function testStatusRevocationInvalidatesSessionAndRejectsStaleSuperAdminForm(): void
    {
        $super = $this->account('status-super', [User::ROLE_SUPER_ADMIN], $this->club);
        $target = $this->account('status-target', [User::ROLE_EQUIPMENT_MANAGER], $this->club);
        $targetId = $target->getId();
        $this->account('status-successor', [User::ROLE_EQUIPMENT_MANAGER], $this->club);
        $this->client->loginUser($super);
        $crawler = $this->client->request('GET', '/admin/de/user/'.$target->getId().'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="User"]')->form();
        static::getContainer()->get(MembershipService::class)->changeStatus($target->getMember(), MembershipStatus::SUSPENDED, 'admin');
        $this->client->submit($form, ['User[email]' => 'stale-status@example.invalid']);
        self::assertResponseStatusCodeSame(409);
        $target = $this->em->find(User::class, $targetId);
        $this->em->refresh($target);
        self::assertNotContains(User::ROLE_EQUIPMENT_MANAGER, $target->getStoredRoles());

        static::getContainer()->get(MembershipService::class)->changeStatus($target->getMember(), MembershipStatus::ACTIVE, 'admin');
        $target->addRole(User::ROLE_EQUIPMENT_MANAGER);
        $this->em->flush();
        $this->client->loginUser($target);
        $this->client->request('GET', '/equipment-manager');
        self::assertResponseIsSuccessful();
        $target = $this->em->find(User::class, $targetId);
        static::getContainer()->get(MembershipService::class)->changeStatus($target->getMember(), MembershipStatus::SUSPENDED, 'admin');
        $this->client->request('GET', '/equipment-manager');
        self::assertResponseRedirects('/login');
    }

    public function testSuperAdminCannotDetachMemberWithFunctionRoles(): void
    {
        $super = $this->account('association-super', [User::ROLE_SUPER_ADMIN], $this->club);
        $target = $this->account('association-target', [User::ROLE_INSTRUCTOR], $this->club);
        $target->getMember()->setBirthday(new \DateTime('2000-01-01'));
        $this->em->flush();
        $memberId = $target->getMember()->getId();
        $targetId = $target->getId();
        $this->client->loginUser($super);
        $crawler = $this->client->request('GET', '/admin/de/member/'.$memberId.'/edit');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="Member"]')->form();
        $this->client->submit($form, ['Member[user]' => '']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame($targetId, (int) $this->em->getConnection()->fetchOne('SELECT user_id FROM member WHERE id = ?', [$memberId]));
    }

    public function testLegacyInspectionBookingCannotBypassMemberScopedWorkflow(): void
    {
        $member = $this->account('legacy-check-member', [User::ROLE_USER], $this->club);
        $this->client->loginUser($member);
        $before = $this->em->getRepository(TankCheck::class)->count([]);
        $this->client->request('GET', '/check/book');
        self::assertResponseRedirects('/my/equipment', 303);
        $this->client->request('POST', '/check/book', ['book_check' => ['tank' => '1']]);
        self::assertResponseStatusCodeSame(405);
        self::assertSame($before, $this->em->getRepository(TankCheck::class)->count([]));
    }

    /** @param list<string> $roles
     *  @return array<string, array{startsAt: string, endsAt: string}>
     */
    private function emptyValidity(array $roles): array
    {
        return array_fill_keys($roles, ['startsAt' => '', 'endsAt' => '']);
    }

    /** @param list<string> $roles */
    private function account(string $name, array $roles, Club $club, bool $active = true): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Test')->setEmail($name.'@example.invalid');
        $member = (new Member())->setPerson($person)->setClub($club)
            ->setMembershipStatus($active ? MembershipStatus::ACTIVE : MembershipStatus::FORMER);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setIsVerified(true)->setRoles($roles)->setMember($member);
        $this->em->persist($user);
        $this->em->persist($member);
        $this->em->flush();
        return $user;
    }
}
