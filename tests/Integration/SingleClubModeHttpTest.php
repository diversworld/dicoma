<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\MembershipPolicy;
use App\Entity\Person;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Service\FrontendCourseRegistrationService;
use App\Service\MembershipAutomation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SingleClubModeHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private Club $other;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'DICOMA_SINGLE_CLUB_MODE' => '1',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Configured club');
        $this->other = (new Club())->setName('Other club');
        $this->em->persist($this->club);
        $this->em->persist($this->other);
        $this->em->flush();
        self::assertSame(1, $this->club->getId());
    }

    public function testSuperadminCannotSwitchCreateOrEditAnotherClub(): void
    {
        $this->client->loginUser($this->account('super', $this->club, [User::ROLE_SUPER_ADMIN]));
        $this->client->request('GET', '/admin/de/club');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Configured club');
        self::assertSelectorTextNotContains('body', 'Other club');
        self::assertSelectorNotExists('form[name="admin_club"]');

        $this->client->request('POST', '/admin/settings/club');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/de/club/new');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/de/club/'.$this->other->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/de/club/'.$this->other->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperadminCanEditConfiguredClubButCannotDeactivateIt(): void
    {
        $this->client->loginUser($this->account('super-edit', $this->club, [User::ROLE_SUPER_ADMIN]));
        $this->client->request('GET', '/admin/de/club/'.$this->club->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name$="[active]"][disabled]');
    }

    public function testPublicApplicationCannotSelectAnotherClub(): void
    {
        foreach ([$this->club, $this->other] as $club) {
            $policy = new MembershipPolicy($club);
            $policy->setApplicationsEnabled(true);
            $policy->setContactEmail('membership@example.invalid');
            $this->em->persist($policy);
        }
        $this->em->flush();
        $this->client->request('GET', '/membership/apply');
        self::assertResponseRedirects('/membership/apply/'.$this->club->getId());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Other club');
        $this->client->request('GET', '/membership/apply/'.$this->other->getId());
        self::assertResponseStatusCodeSame(404);
    }

    public function testForeignMemberSessionIsRejected(): void
    {
        $this->client->loginUser($this->account('foreign', $this->other));
        $this->client->request('GET', '/profile');
        self::assertResponseStatusCodeSame(403);
    }

    public function testExistingForeignSessionCanStillLogOut(): void
    {
        $user = $this->account('logout', $this->club);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/my');
        self::assertResponseIsSuccessful();
        $logout = $crawler->filter('form[action="/logout"]')->form();
        $user->getMember()->setClub($this->other);
        $this->em->flush();
        $this->client->submit($logout);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testRealLoginRejectsForeignMembershipAndAllowsConfiguredClub(): void
    {
        $foreign = $this->account('foreign-login', $this->other);
        $own = $this->account('own-login', $this->club);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        foreach ([$foreign, $own] as $user) {
            $user->setPassword($hasher->hashPassword($user, 'Synthetic-Test-Password-2026!'));
        }
        $this->em->flush();

        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Anmelden')->form([
            '_username' => 'foreign-login',
            '_password' => 'Synthetic-Test-Password-2026!',
        ]));
        self::assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Das Konto ist nicht dem konfigurierten Verein zugeordnet.');
        $this->client->submit($crawler->selectButton('Anmelden')->form([
            '_username' => 'own-login',
            '_password' => 'Synthetic-Test-Password-2026!',
        ]));
        self::assertResponseRedirects('/my');
    }

    public function testAdminWithoutMembershipDoesNotGainClubAdministration(): void
    {
        $person = (new Person())->setFirstname('No')->setLastname('Membership');
        $user = (new User())->setUser('no-member')->setEmail('no-member@example.invalid')
            ->setPassword('unused')->setIsVerified(true)->setRoles([User::ROLE_ADMIN])->setPerson($person);
        $this->em->persist($user);
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/admin');
        self::assertResponseStatusCodeSame(403);
    }

    public function testForeignCourseCannotBeRegisteredByDirectServiceCall(): void
    {
        $person = (new Person())->setFirstname('Guest')->setLastname('Test');
        $course = (new Courses())->setClub($this->other)->setTitle('Foreign course')
            ->setCategory('training')->setStatus(CourseStatus::OPEN);
        $this->em->persist($person);
        $this->em->persist($course);
        $this->em->flush();

        $this->expectException(\DomainException::class);
        static::getContainer()->get(FrontendCourseRegistrationService::class)->register($course->getId(), $person);
    }

    public function testMembershipAutomationProcessesOnlyConfiguredClub(): void
    {
        $own = $this->account('own-automation', $this->club)->getMember();
        $foreign = $this->account('foreign-automation', $this->other)->getMember();
        $termination = new \DateTimeImmutable('2025-01-01');
        $own->setTerminationDate($termination);
        $foreign->setTerminationDate($termination);
        $this->em->flush();

        self::assertSame(1, static::getContainer()->get(MembershipAutomation::class)->run(new \DateTimeImmutable('2025-02-01')));
        self::assertNotNull($own->getLeftAt());
        self::assertNull($foreign->getLeftAt());
    }

    private function account(string $name, Club $club, array $roles = []): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Test')->setEmail($name.'@example.invalid');
        $member = (new Member())->setPerson($person)->setClub($club);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setIsVerified(true)->setRoles($roles)->setMember($member);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
}
