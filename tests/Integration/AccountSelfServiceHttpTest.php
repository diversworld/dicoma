<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class AccountSelfServiceHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'DICOMA_SINGLE_CLUB_MODE' => '0', 'PUBLIC_SITE_CLUB_ID' => '1'] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Test club');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    #[DataProvider('backendRoles')]
    public function testBackendMenusAndAccountPagesForRoles(array $roles, string $url): void
    {
        $user = $this->user('backend', $roles, true);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('form.dicoma-admin-logout-form[action="/logout"]'));
        self::assertCount(0, $crawler->filter('a[href="/logout"]'));
        self::assertCount(2, $crawler->filter('a[href="/admin/account"]'));
        self::assertCount(2, $crawler->filter('a[href="/admin/account/password"]'));
        self::assertCount(2, $crawler->filter('.user-menu-wrapper'));
        $crawler->filter('.user-menu-wrapper')->each(static function ($menu): void {
            self::assertCount(1, $menu->filter('form.dicoma-admin-logout-form'));
            self::assertCount(0, $menu->filter('a[href="/logout"]'));
        });
        $this->client->click($crawler->filter('a[href="/admin/account"]')->first()->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorExists('form[name="person_contact"]');
        $this->client->request('GET', '/admin/account/password');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[name="account_password"]');
    }

    public static function backendRoles(): iterable
    {
        yield 'superadmin' => [[User::ROLE_SUPER_ADMIN], '/admin'];
        yield 'admin' => [[User::ROLE_ADMIN], '/admin'];
        yield 'instructor' => [[User::ROLE_INSTRUCTOR], '/admin/training'];
        yield 'course admin' => [[User::ROLE_COURSE_ADMIN], '/admin/training'];
        yield 'bar manager' => [[User::ROLE_BAR_MANAGER], '/admin/bar-duty'];
        yield 'press officer' => [[User::ROLE_PRESS_OFFICER], '/admin/de/editorial'];
    }

    public function testGuestAndUnlinkedAccountHaveExplicitOwnAccountAccess(): void
    {
        $guest = $this->user('guest', [], false);
        $this->client->loginUser($guest);
        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#frontendNavbar a[href="/account/password"]');
        self::assertSelectorExists('form[name="person_contact"]');
        self::assertSelectorTextContains('body', 'Notfalldaten');

        $orphan = $this->user('orphan', [], false, false);
        $this->client->loginUser($orphan);
        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'keine Person zugeordnet');
        self::assertSelectorNotExists('form[name="person_contact"]');
        $this->client->request('POST', '/account', ['person_contact' => ['email' => 'forged@example.invalid']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/account/password');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberChangesOnlyOwnContactAndPreservesAccountData(): void
    {
        $user = $this->user('owner', [User::ROLE_ADMIN], true);
        $other = $this->user('other', [], true);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/admin/account');
        $this->client->submit($crawler->filter('form[name="person_contact"]')->form(), [
            'person_contact[email]' => 'new-contact@example.invalid',
            'person_contact[mobile]' => '123456',
            'person_contact[emergencyContactName]' => 'Emergency',
        ]);
        self::assertResponseRedirects('/admin/account', 303);
        $this->em->clear();
        $owner = $this->em->getRepository(User::class)->findOneBy(['user' => 'owner']);
        $other = $this->em->getRepository(User::class)->findOneBy(['user' => 'other']);
        self::assertSame('new-contact@example.invalid', $owner->getPerson()->getEmail());
        self::assertSame('123456', $owner->getPerson()->getMobile());
        self::assertSame('Emergency', $owner->getMember()->getEmergencyContactName());
        self::assertSame('owner@example.invalid', $owner->getEmail());
        self::assertSame([User::ROLE_ADMIN], $owner->getStoredRoles());
        self::assertSame('other-contact@example.invalid', $other->getPerson()->getEmail());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM membership_event WHERE kind = 'contact_updated'"));
        self::assertNull($owner->getPerson()->getBirthday());
    }

    public function testGuestContactValidationCsrfAndForgedFields(): void
    {
        $user = $this->user('guest', [], false);
        $other = $this->user('other', [], false);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/account');
        $token = $crawler->filter('input[name="person_contact[_token]"]')->attr('value');
        $this->client->request('POST', '/account', ['person_contact' => ['email' => 'forged@example.invalid', '_token' => 'invalid']]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/account', ['person_contact' => ['email' => 'not-an-email', '_token' => $token]]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', '/account', ['person_contact' => [
            'email' => 'forged@example.invalid', 'roles' => ['ROLE_SUPER_ADMIN'],
            'id' => $other->getPerson()->getId(), '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        $this->em->clear();
        $guest = $this->em->getRepository(User::class)->findOneBy(['user' => 'guest']);
        self::assertSame('guest-contact@example.invalid', $guest->getPerson()->getEmail());
        self::assertSame([], $guest->getStoredRoles());
        $crawler = $this->client->request('GET', '/account');
        $this->client->submit($crawler->filter('form[name="person_contact"]')->form(), ['person_contact[city]' => 'Test City']);
        self::assertResponseRedirects('/account', 303);
        $this->em->clear();
        self::assertSame('Test City', $this->em->getRepository(User::class)->findOneBy(['user' => 'guest'])->getPerson()->getCity());
    }

    public function testPasswordValidationSessionRevocationAndLogin(): void
    {
        $user = $this->user('password-user', [], false);
        $other = $this->user('other-password-user', [], false);
        $reset = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $reset->generateResetToken($user);
        $reset->generateResetToken($other);
        $otherBrowser = new KernelBrowser(self::$kernel);
        $otherBrowser->disableReboot();
        $otherBrowser->loginUser($user);
        $otherBrowser->request('GET', '/account');
        self::assertSame(200, $otherBrowser->getResponse()->getStatusCode());
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/account/password');
        $token = $crawler->filter('input[name="account_password[_token]"]')->attr('value');
        $cases = [
            ['wrong', 'Long-new-password!', 'Long-new-password!', $token],
            ['Original-password!', 'short', 'short', $token],
            ['Original-password!', 'Long-new-password!', 'different', $token],
            ['Original-password!', 'Long-new-password!', 'Long-new-password!', 'invalid'],
        ];
        foreach ($cases as [$current, $first, $second, $csrf]) {
            $this->client->request('POST', '/account/password', ['account_password' => [
                'currentPassword' => $current,
                'plainPassword' => ['first' => $first, 'second' => $second],
                '_token' => $csrf,
            ]]);
            self::assertResponseStatusCodeSame(422);
        }
        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['user' => 'password-user']);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'Original-password!'));

        $crawler = $this->client->request('GET', '/account/password');
        $this->client->submit($crawler->filter('form[name="account_password"]')->form(), [
            'account_password[currentPassword]' => 'Original-password!',
            'account_password[plainPassword][first]' => 'Long-new-password!',
            'account_password[plainPassword][second]' => 'Long-new-password!',
        ]);
        self::assertResponseRedirects('/login', 303);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM reset_password_request WHERE user_id = ?', [$other->getId()]));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM reset_password_request WHERE user_id = ?', [$user->getId()]));
        $otherBrowser->request('GET', '/account');
        self::assertTrue($otherBrowser->getResponse()->isRedirect('http://localhost/login'));
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Kennwort geändert');
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/login');
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(), ['_username' => 'password-user', '_password' => 'Original-password!']);
        $this->client->request('GET', '/account');
        self::assertResponseRedirects('/login');
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(), ['_username' => 'password-user', '_password' => 'Long-new-password!']);
        $this->client->request('GET', '/account');
        self::assertResponseIsSuccessful();
        $this->em->clear();
        $other = $this->em->find(User::class, $other->getId());
        self::assertTrue($hasher->isPasswordValid($other, 'Original-password!'));
    }

    public function testBackendLogoutRequiresCsrf(): void
    {
        $user = $this->user('logout', [User::ROLE_SUPER_ADMIN], true);
        $this->client->loginUser($user);
        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/logout', ['_csrf_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/logout');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form.dicoma-admin-logout-form')->first()->form());
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/admin/account');
        self::assertResponseRedirects('/login');
    }

    private function user(string $name, array $roles, bool $member, bool $person = true): User
    {
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setRoles($roles)->setIsVerified(true);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'Original-password!'));
        if ($person) {
            $identity = (new Person())->setFirstname($name)->setLastname('Test')->setEmail($name.'-contact@example.invalid');
            $user->setPerson($identity);
            if ($member) {
                $membership = (new Member())->setPerson($identity)->setClub($this->club)->setUser($user);
                $this->em->persist($membership);
            }
        }
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
}
