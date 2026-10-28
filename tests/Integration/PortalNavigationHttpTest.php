<?php

declare(strict_types=1);
namespace App\Tests\Integration;

use App\Entity\{BarCashEntry, BarCashSession, BarDutyAssignment, BarDutyPolicy, BarShift, BarShiftDefinition, Club, Member, Person, User};
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\{DataProvider, PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PortalNavigationHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1', 'SITE_THEME' => 'airspace'] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value; putenv($name.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->em->getConnection()->executeStatement('CREATE TABLE equipment_cash_correction (id INTEGER PRIMARY KEY AUTOINCREMENT, club_id INT NOT NULL, source_type VARCHAR(20), source_id INT, direction VARCHAR(10), amount_cents INT, reason VARCHAR(500), created_by VARCHAR(180), created_at DATETIME)');
        $this->club = (new Club())->setName('Portal club');
        $this->em->persist($this->club); $this->em->flush();
    }

    #[DataProvider('roles')]
    public function testCommonLoginChoosesTheCorrectArea(array $roles, string $target): void
    {
        $user = $this->account('login-user', $roles);
        $this->client->loginUser($user);
        $this->client->request('GET', '/login');
        self::assertResponseRedirects($target);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        if (str_starts_with($target, '/admin')) {
            self::assertSelectorExists('aside.sidebar');
            self::assertSelectorNotExists('#frontendNavbar');
            $this->assertNoAreaCrossing(true);
        } else {
            self::assertSelectorExists('#frontendNavbar');
            self::assertSelectorExists('body[data-site-theme="airspace"]');
            $this->assertNoAreaCrossing(false);
        }
    }

    public static function roles(): iterable
    {
        yield 'member' => [[], '/my'];
        yield 'instructor' => [[User::ROLE_INSTRUCTOR], '/my'];
        yield 'equipment manager' => [[User::ROLE_EQUIPMENT_MANAGER], '/my'];
        yield 'board' => [[User::ROLE_BOARD], '/admin'];
        yield 'administrator' => [[User::ROLE_ADMIN], '/admin'];
        yield 'training manager' => [[User::ROLE_TRAINING_MANAGER], '/admin/de/training-overview'];
        yield 'course administrator' => [[User::ROLE_COURSE_ADMIN], '/admin/training'];
        yield 'clubhouse manager' => [[User::ROLE_BAR_MANAGER], '/admin/bar-duty'];
        yield 'press officer' => [[User::ROLE_PRESS_OFFICER], '/admin/de/editorial'];
        yield 'explicit frontend and backend role' => [[User::ROLE_ADMIN, User::ROLE_INSTRUCTOR], '/my'];
    }

    #[DataProvider('credentialLogins')]
    public function testCredentialLoginUsesRoleDestinationAndPreservesSavedTarget(array $roles, ?string $savedTarget, string $expected): void
    {
        $user = $this->account('credential-login', $roles);
        $user->setPassword(static::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)->hashPassword($user, 'Synthetic-Test-Password-2026!'));
        $this->em->flush();
        if ($savedTarget !== null) {
            $this->client->request('GET', $savedTarget);
            self::assertResponseRedirects('/login');
        }
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->filter('form')->form(), [
            '_username' => 'credential-login', '_password' => 'Synthetic-Test-Password-2026!',
        ]);
        if ($savedTarget !== null) {
            self::assertSame($expected, parse_url($this->client->getResponse()->headers->get('Location'), PHP_URL_PATH));
        } else {
            self::assertResponseRedirects($expected);
        }
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->assertNoAreaCrossing(str_starts_with($expected, '/admin'));
    }

    public static function credentialLogins(): iterable
    {
        yield 'pure administrator' => [[User::ROLE_ADMIN], null, '/admin'];
        yield 'frontend instructor' => [[User::ROLE_INSTRUCTOR], null, '/my'];
        yield 'saved personal destination' => [[], '/my/bookings', '/my/bookings'];
        yield 'saved backend destination' => [[User::ROLE_BAR_MANAGER], '/admin/bar-duty/settings', '/admin/bar-duty/settings'];
    }

    #[DataProvider('frontendPages')]
    public function testAllFrontendRolesKeepTheSameThemeAndNeverLinkToBackend(string $path): void
    {
        $user = $this->account('frontend-staff', [User::ROLE_INSTRUCTOR, User::ROLE_EQUIPMENT_MANAGER, User::ROLE_COMPRESSOR_OPERATOR]);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('body[data-site-theme="airspace"] #frontendNavbar');
        self::assertSelectorNotExists('aside.sidebar');
        self::assertSelectorExists('link[href="/css/pages/portal-navigation.css"]');
        $ids = $crawler->filter('[id]')->extract(['id']);
        self::assertSame(count($ids), count(array_unique($ids)));
        $this->assertNoAreaCrossing(false);
    }

    public static function frontendPages(): iterable
    {
        foreach (['/my', '/my/bookings', '/training', '/profile', '/profile/membership', '/equipment-manager',
            '/equipment-manager/loans', '/equipment-manager/inventory', '/equipment-manager/cashbook',
            '/equipment-manager/compressor-evenings', '/equipment-manager/fill-billing',
            '/instructor-portal', '/instructor-portal/courses', '/instructor-portal/tasks', '/bar-duty', '/bar-duty/cash', '/calendar'] as $path) {
            yield $path => [$path];
        }
    }

    public function testFrontendManagementIsForbiddenAndBackendPlanningKeepsItsOwnRoutes(): void
    {
        $manager = $this->account('clubhouse', [User::ROLE_BAR_MANAGER]);
        $this->client->loginUser($manager);
        foreach (['/bar-duty/plan', '/bar-duty/settings', '/bar-duty/report'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
        foreach (['/admin/bar-duty', '/admin/bar-duty/plan', '/admin/bar-duty/settings', '/admin/bar-duty/report', '/admin/calendar'] as $url) {
            $this->client->request('GET', $url); self::assertResponseIsSuccessful();
            self::assertSelectorExists('aside.sidebar');
            $this->assertNoAreaCrossing(true);
        }
    }

    public function testAssignedMemberCanOperateOnlyTheirOwnCashAndClosingIsImmutable(): void
    {
        $user = $this->account('cashier');
        $stranger = $this->account('stranger');
        $definition = new BarShiftDefinition($this->club, 'Own service', 'Clubhouse', '18:00', 180, 2);
        $shift = new BarShift($definition, new \DateTimeImmutable('tomorrow 18:00'));
        $foreignShift = new BarShift($definition, new \DateTimeImmutable('+2 days 18:00'));
        $assignment = new BarDutyAssignment($shift, $user->getMember(), 'Synthetic assignment');
        $policy = new BarDutyPolicy($this->club, (int) $shift->getStartsAt()->format('Y'));
        $policy->configure(0, true, 'fixture', 'Synthetic policy configuration');
        foreach ([$definition, $shift, $foreignShift, $assignment, $policy] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/bar-duty/'.$shift->getId());
        self::assertSelectorExists('#thekenkasse');
        $this->postCash('cash-open', $shift->getId(), ['amount' => '10', 'reason' => 'Initial float counted in person']);
        self::assertResponseRedirects('/bar-duty/'.$shift->getId(), 303);
        $session = $this->em->getRepository(BarCashSession::class)->findOneBy([]);
        self::assertNotNull($session);
        $this->postCash('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '2.50', 'reason' => 'Drinks paid during own shift', 'reference' => 'OWN-1']);
        self::assertResponseRedirects('/bar-duty', 303);
        $income = $this->em->getRepository(BarCashEntry::class)->findOneBy([]);
        self::assertSame(250, $income->getAmountCents());
        $this->postCash('cash-reverse', $session->getId(), ['entry' => $income->getId(), 'reason' => 'Correction of own shift cash receipt']);
        self::assertSame(2, $this->em->getRepository(BarCashEntry::class)->count([]));
        $this->client->request('GET', '/bar-duty/cash/'.$session->getId().'.csv');
        self::assertResponseIsSuccessful();
        $this->postCash('cash-close', $session->getId(), ['amount' => '10.00', 'reason' => 'Counted drawer after own service']);
        self::assertNotNull($this->em->find(BarCashSession::class, $session->getId())->getClosedAt());
        $this->postCash('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '1', 'reason' => 'Must not change closed cash', 'reference' => 'OWN-2']);
        self::assertSame(2, $this->em->getRepository(BarCashEntry::class)->count([]));
        $this->postCash('cash-open', $foreignShift->getId(), ['amount' => '0', 'reason' => 'Must not open an unassigned shift']);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($stranger);
        $this->client->request('GET', '/bar-duty/'.$shift->getId());
        self::assertSelectorNotExists('#thekenkasse');
        $this->client->request('GET', '/bar-duty/cash/'.$session->getId().'.csv');
        self::assertResponseStatusCodeSame(403);
        $this->postCash('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '1']);
        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('cashAccessRevocations')]
    public function testCashAccessRequiresCurrentActiveAssignmentAndClub(string $case): void
    {
        $user = $this->account('cashier', $case === 'unassigned-manager' ? [User::ROLE_BAR_MANAGER] : []);
        $definition = new BarShiftDefinition($this->club, 'Restricted service', 'Clubhouse', '18:00', 180, 2);
        $shift = new BarShift($definition, new \DateTimeImmutable('tomorrow 18:00'));
        $assignment = new BarDutyAssignment($shift, $user->getMember(), 'fixture');
        if ($case === 'cancelled') { $assignment->cancel('fixture', 'Withdrawn before the planned service'); }
        if ($case === 'absent') { $assignment->confirm('absent', 0, 'Confirmed absence from service', 'fixture'); }
        if ($case === 'former') { $user->getMember()->setMembershipStatus(MembershipStatus::FORMER); }
        if ($case === 'foreign-club') {
            $other = (new Club())->setName('Other club');
            $this->em->persist($other);
            $user->getMember()->setClub($other);
        }
        foreach ([$definition, $shift] as $entity) { $this->em->persist($entity); }
        if ($case !== 'unassigned-manager') { $this->em->persist($assignment); }
        $this->em->flush();
        $this->client->loginUser($user);
        $this->postCash('cash-open', $shift->getId(), ['amount' => '0', 'reason' => 'Must not open unauthorized cash']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em->getRepository(BarCashSession::class)->count([]));
    }

    public static function cashAccessRevocations(): iterable
    {
        foreach (['cancelled', 'absent', 'former', 'foreign-club', 'unassigned-manager'] as $case) { yield $case => [$case]; }
    }

    public function testInstructorSeesOnlyOwnClubAndAssignedCoursesWithFrontendAssessment(): void
    {
        $instructor = $this->account('instructor', [User::ROLE_INSTRUCTOR]);
        $other = $this->account('other-instructor', [User::ROLE_INSTRUCTOR]);
        $foreignClub = (new Club())->setName('Foreign training club');
        $this->em->persist($foreignClub);
        $courses = [];
        foreach (['Own lead course', 'Own session course', 'Other instructor course', 'Foreign club course'] as $title) {
            $course = (new \App\Entity\Courses())->setTitle($title)->setCategory('beginner')
                ->setClub($title === 'Foreign club course' ? $foreignClub : $this->club)
                ->setLeadInstructor($title === 'Other instructor course' ? $other->getMember() : $instructor->getMember());
            if ($title === 'Own session course') { $course->setLeadInstructor($other->getMember()); }
            $this->em->persist($course); $courses[$title] = $course;
        }
        $session = (new \App\Entity\Schedule())->setTitle('Assigned training session')
            ->setInstructor($instructor->getMember())->setCourses($courses['Own session course']);
        $this->em->persist($session); $this->em->flush();
        $this->client->loginUser($instructor);
        $this->client->request('GET', '/instructor-portal/courses');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Own lead course');
        self::assertSelectorTextContains('main', 'Own session course');
        self::assertSelectorTextNotContains('main', 'Other instructor course');
        self::assertSelectorTextNotContains('main', 'Foreign club course');
        $this->assertNoAreaCrossing(false);
        $this->client->request('GET', '/my-training/assess/'.$session->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#frontendNavbar');
        $this->assertNoAreaCrossing(false);
        $this->client->request('GET', '/admin/training/assess/'.$session->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorNotExists('#frontendNavbar');
        $this->assertNoAreaCrossing(true);
    }

    public function testBookingsOverviewContainsOnlyOwnBookings(): void
    {
        $user = $this->account('own-booking');
        $other = $this->account('foreign-booking');
        $check = (new \App\Entity\TankCheck())->setCheckDate(new \DateTime('tomorrow'));
        $this->em->persist($check);
        $bookings = [];
        foreach ([$user, $other] as $account) {
            $booking = (new \App\Entity\MemberTankCheckBooking())->setMember($account->getMember())->setTankCheck($check);
            $this->em->persist($booking); $bookings[] = $booking;
        }
        $this->em->flush();
        $this->client->loginUser($user);
        $this->client->request('GET', '/my/bookings');
        self::assertResponseIsSuccessful();
        $router = static::getContainer()->get('router');
        self::assertSelectorExists('main a[href="'.$router->generate('member_tank_check_booking_detail', ['id' => $bookings[0]->getId()]).'"]');
        self::assertSelectorNotExists('main a[href="'.$router->generate('member_tank_check_booking_detail', ['id' => $bookings[1]->getId()]).'"]');
        $this->assertNoAreaCrossing(false);
    }

    private function postCash(string $operation, int $id, array $data): void
    {
        $this->client->request('GET', '/bar-duty');
        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken('bar_duty_'.$operation.'_'.$id)->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally { $stack->pop(); }
        $this->client->request('POST', '/bar-duty/actions/'.$operation.'/'.$id, ['_token' => $token] + $data);
    }

    private function assertNoAreaCrossing(bool $backend): void
    {
        $crawler = $this->client->getCrawler();
        foreach ($crawler->filter('a[href], form[action]')->each(static fn ($node) => $node->attr($node->nodeName() === 'a' ? 'href' : 'action')) as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            if (!is_string($path) || !str_starts_with($path, '/') || $path === '/logout' || str_starts_with($path, '/_')) { continue; }
            if ($backend) { self::assertStringStartsWith('/admin', $path, 'Backend must not link to '.$url); }
            else { self::assertFalse(str_starts_with($path, '/admin'), 'Frontend must not link to '.$url); }
        }
    }

    private function account(string $name, array $roles = []): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Synthetic')->setEmail($name.'@example.invalid');
        $member = (new Member())->setPerson($person)->setClub($this->club)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setIsVerified(true)->setMember($member)->setRoles($roles);
        $this->em->persist($user); $this->em->flush();
        return $user;
    }
}
