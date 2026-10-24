<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\Admin\DashboardController;
use App\Controller\Admin\MemberCrudController;
use App\Controller\Admin\PersonCrudController;
use App\Entity\Club;
use App\Entity\Member;
use App\Entity\MemberQualification;
use App\Entity\Person;
use App\Entity\Qualification;
use App\Entity\User;
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase2FoundationHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for isolated HTTP tests.');
        }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    public function testGuestRegistrationVerificationAndLoginDoNotGrantMembership(): void
    {
        $crawler = $this->client->request('GET', '/register');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="registration_form"]')->form(), [
            'registration_form[user]' => 'guest-registration',
            'registration_form[email]' => 'guest@example.invalid',
            'registration_form[plainPassword]' => 'Test-Password-Only-2026!',
            'registration_form[firstname]' => 'Guest',
            'registration_form[lastname]' => 'Diver',
            'registration_form[birthday]' => '2000-01-01',
            'registration_form[mobile]' => '0123456',
            'registration_form[phone]' => '0654321',
            'registration_form[street]' => 'Test Street 1',
            'registration_form[postal]' => '00123',
            'registration_form[city]' => 'Test City',
            'registration_form[agreeTerms]' => '1',
        ]);
        self::assertResponseRedirects('/login');
        $user = $this->em->getRepository(User::class)->findOneBy(['user' => 'guest-registration']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('00123', $user->getPerson()?->getPostalCode());
        self::assertNull($user->getMember());
        self::assertFalse($user->isVerified());
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM member'));

        $signature = static::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature('app_verify_email', (string) $user->getId(), $user->getEmail(), ['id' => $user->getId()]);
        $this->client->request('GET', $signature->getSignedUrl());
        self::assertResponseRedirects('/register');
        $this->em->clear();
        $user = $this->em->find(User::class, $user->getId());
        self::assertTrue($user->isVerified());

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(), [
            '_username' => 'guest-registration',
            '_password' => 'Test-Password-Only-2026!',
        ]);
        self::assertResponseRedirects('/courses/');
        $this->client->request('GET', '/login');
        self::assertResponseRedirects('/courses/');
        $this->client->request('GET', '/my');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/de');
        self::assertResponseStatusCodeSame(403);
    }

    public function testRegistrationWithoutValidCsrfCannotCreateIdentity(): void
    {
        $this->client->request('POST', '/register', [
            'registration_form' => ['user' => 'forged', 'email' => 'forged@example.invalid', '_token' => 'invalid'],
        ]);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM person'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user'));
    }

    public function testClubAdminListsAndSearchRemainScopedAfterPersonExtraction(): void
    {
        $a = $this->admin('club-a', 'OwnIdentity', User::ROLE_ADMIN);
        $b = $this->admin('club-b', 'ForeignIdentity', User::ROLE_ADMIN);
        $this->client->loginUser($a);
        $url = $this->adminUrl(MemberCrudController::class);
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'OwnIdentity');
        self::assertSelectorTextNotContains('body', 'ForeignIdentity');
        $this->client->request('GET', $this->adminUrl(MemberCrudController::class, Crud::PAGE_INDEX, null, 'ForeignIdentity'));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'ForeignIdentity Diver');
        $this->client->request('GET', $this->adminUrl(MemberCrudController::class, Crud::PAGE_EDIT, $b->getMember()->getId()));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', $this->adminUrl(PersonCrudController::class));
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanManageGuestPersonAndActiveMemberRetainsPortalAccess(): void
    {
        $member = $this->admin('member', 'ActiveIdentity', User::ROLE_MEMBER);
        $super = $this->admin('super', 'SuperIdentity', User::ROLE_SUPER_ADMIN);
        $guest = (new Person())->setFirstname('GuestIdentity')->setLastname('WithoutMembership');
        $this->em->persist($guest);
        $this->em->flush();
        $this->client->loginUser($member);
        $this->client->request('GET', '/my');
        self::assertResponseIsSuccessful();
        $this->client->loginUser($super);
        $this->client->request('GET', $this->adminUrl(PersonCrudController::class));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'GuestIdentity');
        $this->client->request('GET', $this->adminUrl(PersonCrudController::class, Crud::PAGE_EDIT, $guest->getId()));
        self::assertResponseIsSuccessful();
    }

    public function testGuestMembershipCreationAndEditingReuseTheOriginalIdentityAndAccount(): void
    {
        $super = $this->admin('super', 'SuperIdentity', User::ROLE_SUPER_ADMIN);
        $person = (new Person())->setFirstname('Guest')->setLastname('Diver')->setBirthday(new \DateTime('2000-01-01'));
        $guest = (new User())->setUser('joining-guest')->setEmail('joining@example.invalid')
            ->setPassword('unused')->setPerson($person);
        $this->em->persist($guest);
        $this->em->flush();
        $personId = $person->getId();
        $guestId = $guest->getId();
        $this->client->loginUser($super);
        $url = $this->adminUrl(MemberCrudController::class, Crud::PAGE_NEW, personId: $personId);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM member'));
        $this->client->submit($crawler->filter('form[name="Member"]')->form(), [
            'Member[club]' => (string) $super->getMember()->getClub()->getId(),
        ]);
        self::assertResponseRedirects();
        $this->em->clear();
        $loaded = $this->em->find(User::class, $guestId);
        self::assertNotNull($loaded->getMember());
        self::assertSame($personId, $loaded->getMember()->getPerson()->getId());
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM person'));

        $crawler = $this->client->request('GET', $this->adminUrl(MemberCrudController::class, Crud::PAGE_EDIT, $loaded->getMember()->getId()));
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form[name="Member"]')->form(), ['Member[firstname]' => 'Joined']);
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertSame('Joined', $this->em->find(Person::class, $personId)->getFirstname());

        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->filter('form[name="Member"]')->form(), [
            'Member[club]' => (string) $super->getMember()->getClub()->getId(),
        ]);
        self::assertSelectorTextContains('body', 'Diese Person hat bereits eine Mitgliedschaft in diesem Verein.');
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM member'));
    }

    public function testDashboardQualificationDataAndGlobalEquipmentActionsRespectClubScope(): void
    {
        $a = $this->admin('a', 'OwnDashboardIdentity', User::ROLE_ADMIN);
        $b = $this->admin('b', 'ForeignDashboardIdentity', User::ROLE_ADMIN);
        $qualification = (new Qualification())->setName('Dashboard test');
        $this->em->persist($qualification);
        foreach ([$a, $b] as $user) {
            $this->em->persist((new MemberQualification())->setMember($user->getMember())
                ->setQualification($qualification)->setValidUntil(new \DateTime('yesterday')));
        }
        $this->em->flush();
        $this->client->loginUser($a);
        $this->client->request('GET', '/admin/de');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'OwnDashboardIdentity');
        self::assertSelectorTextNotContains('body', 'ForeignDashboardIdentity');
        foreach (['/admin/de/equipment-center', '/admin/de/equipment-booking-basket', '/member/', '/instructor/', '/student/'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('GET', $this->adminUrl(MemberCrudController::class, Crud::PAGE_NEW, personId: $b->getPerson()->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    public function testInactiveInstructorKeepsFunctionalAccessButNotMemberPrivileges(): void
    {
        $instructor = $this->admin('instructor', 'InactiveInstructor', User::ROLE_INSTRUCTOR);
        $instructor->getMember()->setMembershipStatus(MembershipStatus::SUSPENDED);
        $this->em->flush();
        $this->client->loginUser($instructor);
        $this->client->request('GET', '/my');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/instructor-portal');
        self::assertResponseIsSuccessful();
    }

    public function testGuestPasswordResetIsPersistentAndTokenCannotBeReused(): void
    {
        $guest = (new User())->setUser('reset-guest')->setEmail('reset@example.invalid')
            ->setPassword('old-hash')->setIsVerified(true)
            ->setPerson((new Person())->setFirstname('Reset')->setLastname('Guest'));
        $this->em->persist($guest);
        $this->em->flush();
        $token = static::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($guest);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM reset_password_request'));
        $url = '/reset-password/reset/' . $token->getToken();
        $this->client->request('GET', $url);
        self::assertResponseRedirects('/reset-password/reset');
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form')->form(), [
            'change_password_form[plainPassword][first]' => 'New-Test-Password-2026!',
            'change_password_form[plainPassword][second]' => 'New-Test-Password-2026!',
        ]);
        self::assertResponseRedirects('/login');
        $this->em->clear();
        $loaded = $this->em->find(User::class, $guest->getId());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($loaded, 'New-Test-Password-2026!'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM reset_password_request'));
        $this->client->request('GET', $url);
        $this->client->followRedirect();
        self::assertResponseRedirects('/reset-password');
    }

    public function testLogoutRequiresPostAndValidCsrf(): void
    {
        $user = $this->admin('logout-member', 'LogoutIdentity', User::ROLE_MEMBER);
        $this->client->loginUser($user);
        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(405);
        $this->client->request('POST', '/logout', ['_csrf_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/my');
        self::assertResponseIsSuccessful();
        $crawler = $this->client->request('GET', '/my');
        $this->client->submit($crawler->filter('form[action="/logout"]')->form());
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/my');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testExpiredPasswordResetDoesNotChangeTheAccount(): void
    {
        $guest = (new User())->setUser('expired-reset')->setEmail('expired@example.invalid')->setPassword('unchanged');
        $this->em->persist($guest);
        $this->em->flush();
        $token = static::getContainer()->get(ResetPasswordHelperInterface::class)->generateResetToken($guest);
        $this->em->getConnection()->executeStatement("UPDATE reset_password_request SET expires_at = '2000-01-01 00:00:00'");
        $this->client->request('GET', '/reset-password/reset/' . $token->getToken());
        $this->client->followRedirect();
        self::assertResponseRedirects('/reset-password');
        $this->em->clear();
        self::assertSame('unchanged', $this->em->find(User::class, $guest->getId())->getPassword());
    }

    public function testModifiedEmailConfirmationCannotVerifyAnAccount(): void
    {
        $guest = (new User())->setUser('invalid-confirmation')->setEmail('invalid@example.invalid')->setPassword('unused');
        $this->em->persist($guest);
        $this->em->flush();
        $signature = static::getContainer()->get(VerifyEmailHelperInterface::class)
            ->generateSignature('app_verify_email', (string) $guest->getId(), $guest->getEmail(), ['id' => $guest->getId()]);
        $this->client->request('GET', $signature->getSignedUrl() . '&unexpected=1');
        self::assertResponseRedirects('/register');
        $this->em->clear();
        self::assertFalse($this->em->find(User::class, $guest->getId())->isVerified());
    }

    private function admin(string $login, string $firstname, string $role): User
    {
        $club = (new Club())->setName($login);
        $member = (new Member())->setClub($club)->setFirstname($firstname)->setLastname('Diver')
            ->setBirthday(new \DateTime('2000-01-01'));
        $user = (new User())->setUser($login)->setEmail($login . '@example.invalid')
            ->setPassword('unused-loginUser-fixture')->setIsVerified(true)->setRoles([$role])->setMember($member);
        $this->em->persist($club);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function adminUrl(string $controller, string $action = Crud::PAGE_INDEX, ?int $id = null, ?string $query = null, ?int $personId = null): string
    {
        $url = (clone static::getContainer()->get(AdminUrlGenerator::class))->unsetAll()
            ->setDashboard(DashboardController::class)->setController($controller)->setAction($action)->set('_locale', 'de');
        if ($id !== null) {
            $url->setEntityId($id);
        }
        if ($query !== null) {
            $url->set('query', $query);
        }
        if ($personId !== null) {
            $url->set('personId', $personId);
        }
        return $url->generateUrl();
    }
}
