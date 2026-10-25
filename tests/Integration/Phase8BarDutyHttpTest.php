<?php

declare(strict_types=1);
namespace App\Tests\Integration;

use App\Command\{BarDutyRemindersCommand, SendBarDutyNotificationsCommand};
use App\Entity\{BarCashEntry, BarCashSession, BarDutyAssignment, BarDutyExchange, BarDutyNotification, BarDutyPolicy, BarDutyRequirement, BarShift, BarShiftDefinition, Club, Member, Person, User};
use App\Enum\MembershipStatus;
use App\Service\{BarCashWorkflow, BarDutyAccounts, BarDutyWorkflow};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\{SchemaTool, SchemaValidator};
use PHPUnit\Framework\Attributes\{DataProvider, PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};
use Symfony\Component\Console\Tester\CommandTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase8BarDutyHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $manager;
    private User $alice;
    private User $bob;
    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:'); putenv('MAILER_DSN=null://null');
        $this->client = static::createClient(['environment' => 'test']); $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Synthetic clubhouse'); $this->em->persist($this->club);
        $this->manager = $this->account('bar-manager', [User::ROLE_BAR_MANAGER]);
        $this->alice = $this->account('alice'); $this->bob = $this->account('bob');
        $this->login($this->manager);
    }

    public function testDefinitionsPlanningSignupNotificationsAndCalendarAreIntegrated(): void
    {
        $this->client->request('GET', '/bar-duty/settings'); self::assertResponseIsSuccessful();
        $this->post('define', 0, ['title' => 'Friday bar', 'location' => 'Clubhouse', 'time' => '18:30', 'minutes' => '180', 'capacity' => '2']);
        $definition = $this->em->getRepository(BarShiftDefinition::class)->findOneBy([]);
        self::assertSame(180, $definition->getDurationMinutes());
        $date = new \DateTimeImmutable('+3 days');
        $this->post('schedule', $definition->getId(), ['date' => $date->format('Y-m-d')]);
        $shift = $this->em->getRepository(BarShift::class)->findOneBy([]);
        self::assertSame('18:30', $shift->getStartsAt()->format('H:i'));
        self::assertSame(180, intdiv($shift->getEndsAt()->getTimestamp() - $shift->getStartsAt()->getTimestamp(), 60));
        $this->login($this->alice);
        $this->client->request('GET', '/bar-duty/'.$shift->getId()); self::assertResponseIsSuccessful();
        $this->post('signup', $shift->getId());
        self::assertSame(1, $this->em->getRepository(BarDutyAssignment::class)->count(['status' => 'active']));
        self::assertSame(1, $this->em->getRepository(BarDutyNotification::class)->count([]));
        $notification = $this->em->getRepository(BarDutyNotification::class)->findOneBy([]);
        $this->post('read', $notification->getId());
        self::assertNotNull($this->em->find(BarDutyNotification::class, $notification->getId())->getReadAt());
        $this->client->request('GET', '/admin/calendar?month='.$date->format('Y-m'));
        self::assertResponseIsSuccessful(); self::assertSelectorExists('[data-event-key="bar-duty-'.$shift->getId().'"]');
        self::assertSame([], (new SchemaValidator($this->em))->validateMapping());
    }

    #[DataProvider('portalRoles')]
    public function testDedicatedPortalIsUsedForEveryRoleAndDutyPage(string $role, bool $management): void
    {
        $user = $this->account('portal-user', [$role]);
        $shift = $this->shift('+3 days');
        $this->login($user);
        $urls = ['/bar-duty', '/bar-duty/'.$shift->getId()];
        if ($management) { $urls = [...$urls, '/bar-duty/plan', '/bar-duty/settings', '/bar-duty/report']; }
        foreach ($urls as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('body[data-bar-duty-portal]');
            self::assertSelectorTextContains('header', 'Thekendienstportal');
            self::assertSelectorExists('nav[aria-label="Thekendienstportal"] a[href="/bar-duty"]');
            self::assertSelectorExists('form[method="post"][action="/logout"] input[name="_csrf_token"]');
            self::assertSelectorExists('link[href="/css/pages/bar-duty-portal.css"]');
            self::assertSelectorNotExists('aside.sidebar');
            self::assertSelectorNotExists('#frontendNavbar');
            if ($management) {
                self::assertSelectorExists('nav[aria-label="Thekendienstportal"] a[href="/bar-duty/plan"]');
                self::assertSelectorExists('nav[aria-label="Thekendienstportal"] a[href="/bar-duty/report"]');
            } else {
                self::assertSelectorNotExists('a[href="/bar-duty/plan"]');
                self::assertSelectorNotExists('a[href="/bar-duty/settings"]');
                self::assertSelectorNotExists('a[href="/bar-duty/report"]');
            }
        }
        $this->client->request('GET', '/bar-duty');
        self::assertSelectorExists('link[href="/css/pages/admin-calendar-index.css"]');
    }

    public static function portalRoles(): iterable
    {
        yield 'member' => [User::ROLE_MEMBER, false];
        yield 'bar manager' => [User::ROLE_BAR_MANAGER, true];
        yield 'admin' => [User::ROLE_ADMIN, true];
        yield 'superadmin' => [User::ROLE_SUPER_ADMIN, true];
    }

    public function testSelfSignupCannotAssignAnotherMemberAndCapacityIsEnforced(): void
    {
        $shift = $this->shift('+3 days', 1);
        $this->login($this->alice);
        $this->post('signup', $shift->getId(), ['member' => $this->bob->getMember()->getId()]);
        $row = $this->em->getRepository(BarDutyAssignment::class)->findOneBy([]);
        self::assertSame($this->alice->getMember()->getId(), $row->getMember()->getId());
        $this->login($this->bob); $this->post('signup', $shift->getId());
        self::assertSame(1, $this->em->getRepository(BarDutyAssignment::class)->count(['status' => 'active']));
        $this->client->followRedirect(); self::assertSelectorTextContains('body', 'vollständig besetzt');
    }

    public function testOverlappingDutiesAreRejectedWhileAdjacentShiftsAreAllowed(): void
    {
        $one = $this->shift('+3 days', 2, '18:00', 60);
        $overlap = $this->shift('+3 days', 2, '18:30', 60);
        $adjacent = $this->shift('+3 days', 2, '19:00', 60);
        $this->login($this->alice); $this->post('signup', $one->getId()); $this->post('signup', $adjacent->getId()); $this->post('signup', $overlap->getId());
        self::assertSame(2, $this->em->getRepository(BarDutyAssignment::class)->count(['status' => 'active']));
        $this->client->followRedirect(); self::assertSelectorTextContains('body', 'Zeitraum bereits eingeteilt');
    }

    public function testReplacementNeedsTargetConsentAndDoesNotNeedManagerApproval(): void
    {
        $shift = $this->shift('+3 days');
        $this->login($this->alice); $this->post('signup', $shift->getId());
        $source = $this->em->getRepository(BarDutyAssignment::class)->findOneBy([]);
        $this->post('propose', $source->getId(), ['member' => $this->bob->getMember()->getId()]);
        $exchange = $this->em->getRepository(BarDutyExchange::class)->findOneBy([]);
        self::assertSame('active', $this->em->find(BarDutyAssignment::class, $source->getId())->getStatus());
        $this->login($this->bob); $this->post('accept', $exchange->getId());
        self::assertSame('accepted', $this->em->find(BarDutyExchange::class, $exchange->getId())->getStatus());
        self::assertSame('cancelled', $this->em->find(BarDutyAssignment::class, $source->getId())->getStatus());
        self::assertSame($this->bob->getMember()->getId(), $this->em->getRepository(BarDutyAssignment::class)->findOneBy(['status' => 'active'])->getMember()->getId());
        self::assertSame(1, $this->em->getRepository(BarDutyAssignment::class)->count(['status' => 'active']));
    }

    public function testMutualSwapReplacesBothAssignmentsAtomically(): void
    {
        $one = $this->shift('+3 days'); $two = $this->shift('+4 days');
        $this->login($this->alice); $this->post('signup', $one->getId());
        $a = $this->em->getRepository(BarDutyAssignment::class)->findOneBy(['shift' => $one]);
        $this->login($this->bob); $this->post('signup', $two->getId());
        $b = $this->em->getRepository(BarDutyAssignment::class)->findOneBy(['shift' => $two]);
        $this->login($this->alice);
        $this->post('propose', $a->getId(), ['member' => $this->bob->getMember()->getId(), 'counterpart' => $b->getId()]);
        $exchange = $this->em->getRepository(BarDutyExchange::class)->findOneBy([]);
        $this->login($this->bob); $this->post('accept', $exchange->getId());
        self::assertSame(2, $this->em->getRepository(BarDutyAssignment::class)->count(['status' => 'active']));
        self::assertSame(2, $this->em->getRepository(BarDutyAssignment::class)->count(['status' => 'cancelled']));
        self::assertSame($this->bob->getMember()->getId(), $this->em->getRepository(BarDutyAssignment::class)->findOneBy(['shift' => $one->getId(), 'status' => 'active'])->getMember()->getId());
        self::assertSame($this->alice->getMember()->getId(), $this->em->getRepository(BarDutyAssignment::class)->findOneBy(['shift' => $two->getId(), 'status' => 'active'])->getMember()->getId());
    }

    public function testConflictingReplacementRollsBackWithoutCancellingOriginal(): void
    {
        $one = $this->shift('+3 days', 1, '18:00'); $two = $this->shift('+3 days', 1, '19:00');
        $this->login($this->alice); $this->post('signup', $one->getId());
        $a = $this->em->getRepository(BarDutyAssignment::class)->findOneBy([]);
        $this->post('propose', $a->getId(), ['member' => $this->bob->getMember()->getId()]);
        $exchange = $this->em->getRepository(BarDutyExchange::class)->findOneBy([]);
        $this->login($this->bob); $this->post('signup', $two->getId()); $this->post('accept', $exchange->getId());
        self::assertSame('active', $this->em->getConnection()->fetchOne('SELECT status FROM bar_duty_assignment WHERE id = ?', [$a->getId()]));
        self::assertSame('pending', $this->em->getConnection()->fetchOne('SELECT status FROM bar_duty_exchange WHERE id = ?', [$exchange->getId()]));
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM bar_duty_assignment WHERE status = 'active'"));
    }

    public function testAttendanceHoursExceptionsCorrectionAndFinalization(): void
    {
        $year = (int) date('Y');
        $shift = $this->pastShift($year.'-01-02 18:00');
        $assignment = new BarDutyAssignment($shift, $this->alice->getMember(), 'fixture'); $this->em->persist($assignment); $this->em->flush();
        $this->post('policy', 0, ['year' => $year, 'minutes' => '600', 'cash' => '1', 'reason' => 'Annual meeting approved obligations']);
        $this->post('exception', 0, ['year' => $year, 'member' => $this->alice->getMember()->getId(), 'minutes' => '120', 'reason' => 'Reduced duty obligation for this year']);
        $this->post('attendance', $assignment->getId(), ['attendance' => 'present', 'minutes' => '150', 'reason' => 'Actual attendance confirmed by supervisor']);
        $accounts = static::getContainer()->get(BarDutyAccounts::class);
        $row = $accounts->account($this->em->find(Member::class, $this->alice->getMember()->getId()), $year);
        self::assertSame(150, $row['earned']); self::assertSame(120, $row['required']); self::assertSame(30, $row['balance']); self::assertSame(0, $row['remaining']);
        self::assertSame(0, $accounts->account($row['member'], $year + 1)['earned']);
        $this->post('attendance', $assignment->getId(), ['attendance' => 'present', 'minutes' => '90', 'reason' => 'Correction after verified early departure']);
        $row = $accounts->account($row['member'], $year); self::assertSame(90, $row['earned']); self::assertSame(30, $row['remaining']);
        $this->post('complete', $shift->getId(), ['reason' => 'All attendance reviewed and completed']);
        $this->client->request('GET', '/bar-duty/report?year='.$year); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', '90');
        $this->client->request('GET', '/bar-duty/report.csv?year='.$year); self::assertResponseIsSuccessful();
        self::assertStringContainsString('Bestätigte Minuten', $this->client->getInternalResponse()->getContent());
        $this->post('attendance', $assignment->getId(), ['attendance' => 'present', 'minutes' => '100', 'reason' => 'Must reject changes after completion']);
        self::assertSame(90, (int) $this->em->getConnection()->fetchOne('SELECT credited_minutes FROM bar_duty_assignment WHERE id = ?', [$assignment->getId()]));
    }

    public function testUnknownPolicyIsNotSilentlyTreatedAsExemptionAndZeroIsExplicit(): void
    {
        $accounts = static::getContainer()->get(BarDutyAccounts::class);
        $row = $accounts->account($this->alice->getMember(), (int) date('Y'));
        self::assertNull($row['required']); self::assertNull($row['remaining']);
        $this->post('exception', 0, ['year' => date('Y'), 'member' => $this->alice->getMember()->getId(), 'minutes' => '0', 'reason' => 'Explicit exemption for documented reason']);
        $row = $accounts->account($this->em->find(Member::class, $this->alice->getMember()->getId()), (int) date('Y'));
        self::assertSame(0, $row['required']); self::assertSame(0, $row['remaining']);
        $this->post('exception', 0, ['year' => date('Y'), 'member' => $this->alice->getMember()->getId(), 'minutes' => '', 'reason' => 'Return to ordinary club obligation']);
        self::assertNull($accounts->account($row['member'], (int) date('Y'))['required']);
    }

    public function testSeparateCashTracksExactAmountsReversalsClosingAndExport(): void
    {
        $shift = $this->shift('+3 days');
        $this->post('policy', 0, ['year' => $shift->getStartsAt()->format('Y'), 'minutes' => '120', 'cash' => '1', 'reason' => 'Enable independent clubhouse cash']);
        $this->post('cash-open', $shift->getId(), ['amount' => '50,00', 'reason' => 'Counted and handed over initial float']);
        $session = $this->em->getRepository(BarCashSession::class)->findOneBy([]);
        self::assertNotNull($session);
        $this->post('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '12,34', 'reason' => 'Drinks actually paid in cash', 'reference' => '=SUM(A1)']);
        $income = $this->em->getRepository(BarCashEntry::class)->findOneBy([]);
        $this->post('cash-book', $session->getId(), ['direction' => 'out', 'amount' => '2,34', 'reason' => 'Supplies purchased from the cash float', 'reference' => 'S-001']);
        $this->post('cash-reverse', $session->getId(), ['entry' => $income->getId(), 'reason' => 'Returned duplicate cash receipt amount']);
        $cash = static::getContainer()->get(BarCashWorkflow::class);
        $fresh = $this->em->find(BarCashSession::class, $session->getId());
        self::assertSame(4766, $cash->balance($fresh)); self::assertCount(3, $cash->entries($fresh));
        self::assertSame(0, $this->em->getRepository(\App\Entity\EquipmentCashPayout::class)->count([]));
        self::assertSame(0, $this->em->getRepository(\App\Entity\EquipmentRentalReceipt::class)->count([]));
        $this->post('cash-close', $session->getId(), ['amount' => '47,60', 'reason' => 'Counted six cents less than expected']);
        $fresh = $this->em->find(BarCashSession::class, $session->getId());
        self::assertSame(4766, $fresh->getExpectedCents()); self::assertSame(4760, $fresh->getCountedCents()); self::assertNotNull($fresh->getClosedAt());
        self::assertSame(64, strlen($fresh->getMovementHash()));
        $this->client->request('GET', '/bar-duty/cash/'.$fresh->getId().'.csv'); self::assertResponseIsSuccessful();
        $csv = $this->client->getInternalResponse()->getContent(); self::assertStringContainsString("'=SUM(A1)", $csv); self::assertStringContainsString('4766', $csv);
        $this->post('cash-book', $fresh->getId(), ['direction' => 'in', 'amount' => '1', 'reason' => 'Attempt after immutable cash closing']);
        self::assertSame(3, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_cash_entry'));
    }

    public function testCashRejectsOverdrawWithoutChangingJournal(): void
    {
        $shift = $this->shift('+3 days');
        $this->post('policy', 0, ['year' => $shift->getStartsAt()->format('Y'), 'minutes' => '0', 'cash' => '1', 'reason' => 'Independent cash float configured']);
        $this->post('cash-open', $shift->getId(), ['amount' => '0', 'reason' => 'Counted empty drawer before service']);
        $session = $this->em->getRepository(BarCashSession::class)->findOneBy([]);
        $this->post('cash-book', $session->getId(), ['direction' => 'out', 'amount' => '1', 'reason' => 'Attempt to overdraw an empty drawer']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_cash_entry'));
    }

    public function testRemindersAreIdempotentAndMailSendingRequiresExplicitOptIn(): void
    {
        $shift = $this->shift('+1 day');
        $this->login($this->alice); $this->post('signup', $shift->getId());
        $command = new CommandTester(static::getContainer()->get(BarDutyRemindersCommand::class));
        self::assertSame(0, $command->execute(['--hours' => '48'])); self::assertStringContainsString('1 Erinnerungen', $command->getDisplay());
        self::assertSame(0, $command->execute(['--hours' => '48'])); self::assertStringContainsString('0 Erinnerungen', $command->getDisplay());
        self::assertSame(2, $this->em->getRepository(BarDutyNotification::class)->count([]));
        $sender = new CommandTester(static::getContainer()->get(SendBarDutyNotificationsCommand::class));
        self::assertSame(0, $sender->execute([])); self::assertStringContainsString('Kein Versand', $sender->getDisplay());
        self::assertSame(2, $this->em->getRepository(BarDutyNotification::class)->count(['mailState' => 'pending']));
    }

    #[DataProvider('managerOperations')]
    public function testMembersCannotInvokeManagementOperations(string $operation): void
    {
        $shift = $this->shift('+3 days');
        $this->login($this->alice);
        $this->post($operation, $shift->getId(), ['year' => date('Y'), 'minutes' => '0', 'capacity' => '1', 'member' => $this->bob->getMember()->getId(), 'reason' => 'Must not permit role escalation', 'amount' => '0'], 403);
    }
    public static function managerOperations(): iterable
    {
        foreach (['plan', 'define', 'deactivate', 'schedule', 'policy', 'exception', 'assign', 'attendance', 'complete', 'cancel', 'cash-open', 'cash-book', 'cash-reverse', 'cash-close'] as $op) { yield $op => [$op]; }
    }

    public function testCrossClubAccessCsrfAndPrivateReportsAreEnforced(): void
    {
        $shift = $this->shift('+3 days');
        $otherClub = (new Club())->setName('Foreign club'); $this->em->persist($otherClub);
        $other = $this->account('foreign', [User::ROLE_BAR_MANAGER], $otherClub);
        $this->login($other);
        $this->client->request('GET', '/bar-duty/'.$shift->getId()); self::assertResponseStatusCodeSame(404);
        $this->post('assign', $shift->getId(), ['member' => $other->getMember()->getId()], 403);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_duty_assignment'));
        $this->login($this->alice);
        foreach (['/bar-duty/report', '/bar-duty/report.csv', '/bar-duty/settings', '/bar-duty/plan'] as $url) { $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403); }
        $this->client->request('POST', '/bar-duty/actions/signup/'.$shift->getId()); self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/bar-duty/actions/signup/'.$shift->getId()); self::assertResponseStatusCodeSame(405);
    }

    public function testCancelledShiftWithdrawsRequestsAndCreatesNoStaleReminders(): void
    {
        $shift = $this->shift('+1 day');
        $this->login($this->alice); $this->post('signup', $shift->getId());
        $assignment = $this->em->getRepository(BarDutyAssignment::class)->findOneBy([]);
        $this->post('propose', $assignment->getId(), ['member' => $this->bob->getMember()->getId()]);
        $this->login($this->manager); $this->post('cancel', $shift->getId(), ['reason' => 'Clubhouse closed because of maintenance']);
        self::assertSame('withdrawn', $this->em->getRepository(BarDutyExchange::class)->findOneBy([])->getStatus());
        self::assertSame('cancelled', $this->em->find(BarDutyAssignment::class, $assignment->getId())->getStatus());
        $reminder = new CommandTester(static::getContainer()->get(BarDutyRemindersCommand::class));
        self::assertSame(0, $reminder->execute([])); self::assertStringContainsString('0 Erinnerungen', $reminder->getDisplay());
        $this->client->request('GET', '/admin/calendar?month='.$shift->getStartsAt()->format('Y-m'));
        self::assertResponseIsSuccessful(); self::assertSelectorNotExists('[data-event-key="bar-duty-'.$shift->getId().'"]');
    }

    public function testWithdrawalFreesCapacityAndWithdrawsPendingRequest(): void
    {
        $shift = $this->shift('+3 days');
        $this->login($this->alice); $this->post('signup', $shift->getId());
        $assignment = $this->em->getRepository(BarDutyAssignment::class)->findOneBy([]);
        $this->post('propose', $assignment->getId(), ['member' => $this->bob->getMember()->getId()]);
        $this->post('withdraw', $assignment->getId(), ['reason' => 'Cannot attend because of illness']);
        self::assertSame('withdrawn', $this->em->getRepository(BarDutyExchange::class)->findOneBy([])->getStatus());
        $this->login($this->bob); $this->post('signup', $shift->getId());
        self::assertSame(1, $this->em->getRepository(BarDutyAssignment::class)->count(['status' => 'active']));
    }

    #[DataProvider('exchangeDecisions')]
    public function testExchangeCanBeDeclinedOrRetracted(string $operation, string $expected): void
    {
        $shift = $this->shift('+3 days');
        $this->login($this->alice); $this->post('signup', $shift->getId());
        $assignment = $this->em->getRepository(BarDutyAssignment::class)->findOneBy([]);
        $this->post('propose', $assignment->getId(), ['member' => $this->bob->getMember()->getId()]);
        $exchange = $this->em->getRepository(BarDutyExchange::class)->findOneBy([]);
        if ($operation === 'decline') { $this->login($this->bob); }
        $this->post($operation, $exchange->getId());
        self::assertSame($expected, $this->em->find(BarDutyExchange::class, $exchange->getId())->getStatus());
        self::assertSame('active', $this->em->find(BarDutyAssignment::class, $assignment->getId())->getStatus());
    }
    public static function exchangeDecisions(): iterable
    {
        yield ['decline', 'declined']; yield ['retract', 'withdrawn'];
    }

    public function testOnlyTargetCanAcceptExchange(): void
    {
        $shift = $this->shift('+3 days');
        $this->login($this->alice); $this->post('signup', $shift->getId());
        $assignment = $this->em->getRepository(BarDutyAssignment::class)->findOneBy([]);
        $this->post('propose', $assignment->getId(), ['member' => $this->bob->getMember()->getId()]);
        $exchange = $this->em->getRepository(BarDutyExchange::class)->findOneBy([]);
        $this->post('accept', $exchange->getId(), [], 403);
        self::assertSame('pending', $this->em->getConnection()->fetchOne('SELECT status FROM bar_duty_exchange'));
    }

    #[DataProvider('privateOperations')]
    public function testMemberCannotChangeOtherMembersAssignmentsOrReadMessages(string $operation): void
    {
        $shift = $this->shift('+3 days');
        $this->login($this->alice); $this->post('signup', $shift->getId());
        $id = $operation === 'read' ? $this->em->getRepository(BarDutyNotification::class)->findOneBy([])->getId()
            : $this->em->getRepository(BarDutyAssignment::class)->findOneBy([])->getId();
        $this->login($this->bob); $this->post($operation, $id, ['reason' => 'Cannot change another member assignment'], 403);
        self::assertSame('active', $this->em->getConnection()->fetchOne('SELECT status FROM bar_duty_assignment'));
        self::assertNull($this->em->getConnection()->fetchOne('SELECT read_at FROM bar_duty_notification'));
    }
    public static function privateOperations(): iterable { yield ['read']; yield ['withdraw']; }

    #[DataProvider('attendanceRejections')]
    public function testAttendanceRejectsEarlyOrInconsistentCredit(string $case): void
    {
        $shift = $case === 'early' ? $this->shift('+3 days') : $this->pastShift(date('Y').'-01-02 18:00');
        $assignment = new BarDutyAssignment($shift, $this->alice->getMember(), 'fixture'); $this->em->persist($assignment); $this->em->flush();
        $this->post('attendance', $assignment->getId(), ['attendance' => $case === 'absent-credit' ? 'absent' : 'present', 'minutes' => $case === 'zero-present' ? '0' : '60', 'reason' => 'Attendance must be consistent and final']);
        self::assertSame('pending', $this->em->getConnection()->fetchOne('SELECT attendance FROM bar_duty_assignment'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT credited_minutes FROM bar_duty_assignment'));
    }
    public static function attendanceRejections(): iterable { yield ['early']; yield ['absent-credit']; yield ['zero-present']; }

    public function testAbsentAttendanceAndYearBoundaryDoNotCreateCreditOrCarryover(): void
    {
        $year = (int) date('Y') - 1;
        $shift = $this->pastShift($year.'-12-31 23:30');
        $a = new BarDutyAssignment($shift, $this->alice->getMember(), 'fixture');
        $b = new BarDutyAssignment($shift, $this->bob->getMember(), 'fixture');
        $this->em->persist($a); $this->em->persist($b); $this->em->flush();
        $this->post('attendance', $a->getId(), ['attendance' => 'present', 'minutes' => '180', 'reason' => 'Confirmed attendance crossing annual boundary']);
        $this->post('attendance', $b->getId(), ['attendance' => 'absent', 'minutes' => '0', 'reason' => 'Member was absent throughout the service']);
        $accounts = static::getContainer()->get(BarDutyAccounts::class);
        self::assertSame(180, $accounts->account($this->em->find(Member::class, $this->alice->getMember()->getId()), $year)['earned']);
        self::assertSame(0, $accounts->account($this->em->find(Member::class, $this->alice->getMember()->getId()), $year + 1)['earned']);
        $absent = $accounts->account($this->em->find(Member::class, $this->bob->getMember()->getId()), $year);
        self::assertSame(0, $absent['earned']); self::assertSame(1, $absent['absent']);
    }

    public function testOtherClubsMembersCannotBeAssignedAndForeignShiftsAreNotInCalendar(): void
    {
        $shift = $this->shift('+3 days');
        $otherClub = (new Club())->setName('Foreign club'); $this->em->persist($otherClub);
        $other = $this->account('foreign-assignee', [], $otherClub);
        $definition = new BarShiftDefinition($otherClub, 'Private foreign service', 'Foreign clubhouse', '18:00', 120, 1);
        $foreignShift = new BarShift($definition, $shift->getStartsAt()); $this->em->persist($definition); $this->em->persist($foreignShift); $this->em->flush();
        $this->client->request('GET', '/admin/calendar?month='.$shift->getStartsAt()->format('Y-m'));
        self::assertResponseIsSuccessful(); self::assertSelectorNotExists('[data-event-key="bar-duty-'.$foreignShift->getId().'"]');
        $this->post('assign', $shift->getId(), ['member' => $other->getMember()->getId()], 403);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_duty_assignment'));
    }

    #[DataProvider('noMemberAccess')]
    public function testInactiveOrContextlessAccountsCannotAccessDutyOrCalendar(string $case): void
    {
        $user = $this->em->find(User::class, $this->alice->getId());
        if ($case === 'inactive') { $user->getMember()->setMembershipStatus(MembershipStatus::FORMER); }
        else { $user->setMember(null); }
        $this->em->flush(); $this->client->loginUser($user);
        foreach (['/bar-duty', '/admin/calendar'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
    }
    public static function noMemberAccess(): iterable { yield ['inactive']; yield ['contextless']; }

    public function testBoardInheritsThekenleitungWithoutChangingMemberStatus(): void
    {
        $board = $this->account('board', [User::ROLE_BOARD]);
        $this->login($board); $this->client->request('GET', '/bar-duty/settings'); self::assertResponseIsSuccessful();
    }

    public function testMissingRecipientPersistsFailureWithoutCallingMailer(): void
    {
        $shift = $this->shift('+3 days');
        $member = $this->em->find(Member::class, $this->alice->getMember()->getId()); $member->setEmail(null);
        $notice = new BarDutyNotification($member, $shift, 'invalid-recipient', 'Test invalid recipient', 'Synthetic message');
        $this->em->persist($notice); $this->em->flush();
        $_ENV['BAR_DUTY_MAIL_FROM'] = $_SERVER['BAR_DUTY_MAIL_FROM'] = 'bar@example.invalid';
        $mailer = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class); $mailer->expects(self::never())->method('send');
        $sender = new CommandTester(new SendBarDutyNotificationsCommand($this->em, $mailer, static::getContainer()->getParameter('kernel.project_dir')));
        self::assertSame(1, $sender->execute(['--send' => true]));
        $this->em->refresh($notice); self::assertSame('failed', $notice->getMailState()); self::assertNotNull($notice->getMailError());
    }

    #[DataProvider('cashRejections')]
    public function testCashRejectsDuplicateOpeningReversalAndUnexplainedDifference(string $operation): void
    {
        $shift = $this->shift('+3 days');
        $this->post('policy', 0, ['year' => $shift->getStartsAt()->format('Y'), 'minutes' => '0', 'cash' => '1', 'reason' => 'Enable separate cash for this service']);
        $this->post('cash-open', $shift->getId(), ['amount' => '10', 'reason' => 'Initial cash counted and documented']);
        $session = $this->em->getRepository(BarCashSession::class)->findOneBy([]);
        if ($operation === 'double-open') {
            $this->post('cash-open', $shift->getId(), ['amount' => '20', 'reason' => 'Must reject duplicate initial float']);
            self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_cash_session'));
        } elseif ($operation === 'difference') {
            $this->post('cash-close', $session->getId(), ['amount' => '9', 'reason' => '']);
            self::assertNull($this->em->getConnection()->fetchOne('SELECT closed_at FROM bar_cash_session'));
        } else {
            $this->post('cash-book', $session->getId(), ['direction' => 'in', 'amount' => '5', 'reason' => 'Cash sales during actual service']);
            $entry = $this->em->getRepository(BarCashEntry::class)->findOneBy([]);
            $this->post('cash-reverse', $session->getId(), ['entry' => $entry->getId(), 'reason' => 'Correction of duplicate receipt entry']);
            $this->post('cash-reverse', $session->getId(), ['entry' => $entry->getId(), 'reason' => 'Must reject repeated reversal of receipt']);
            self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_cash_entry'));
        }
    }
    public static function cashRejections(): iterable
    {
        foreach (['double-open', 'difference', 'double-reverse'] as $op) { yield $op => [$op]; }
    }

    public function testUnconfiguredCashCannotBeOpened(): void
    {
        $shift = $this->shift('+3 days');
        $this->post('cash-open', $shift->getId(), ['amount' => '0', 'reason' => 'Opening must require explicit policy']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_cash_session'));
    }

    public function testFutureTerminationPreventsAssignmentBeyondMembershipEnd(): void
    {
        $shift = $this->shift('+3 days');
        $member = $this->em->find(Member::class, $this->alice->getMember()->getId());
        $member->setTerminationDate(new \DateTimeImmutable('tomorrow')); $this->em->flush();
        $this->post('assign', $shift->getId(), ['member' => $member->getId()]);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_duty_assignment'));
    }

    public function testTransportFailureIsPersistedAndExplicitRetrySucceeds(): void
    {
        $shift = $this->shift('+3 days');
        $notice = new BarDutyNotification($this->alice->getMember(), $shift, 'transport-test', 'Test service notification', 'Synthetic service message');
        $this->em->persist($notice); $this->em->flush();
        $_ENV['BAR_DUTY_MAIL_FROM'] = $_SERVER['BAR_DUTY_MAIL_FROM'] = 'bar@example.invalid';
        $mailer = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willThrowException(new \Symfony\Component\Mailer\Exception\TransportException('Synthetic transport failure'));
        $sender = new CommandTester(new SendBarDutyNotificationsCommand($this->em, $mailer, static::getContainer()->getParameter('kernel.project_dir')));
        self::assertSame(1, $sender->execute(['--send' => true]));
        $this->em->refresh($notice); self::assertSame('failed', $notice->getMailState()); self::assertSame('Synthetic transport failure', $notice->getMailError());
        $mailer = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class);
        $mailer->expects(self::once())->method('send');
        $sender = new CommandTester(new SendBarDutyNotificationsCommand($this->em, $mailer, static::getContainer()->getParameter('kernel.project_dir')));
        self::assertSame(0, $sender->execute(['--send' => true])); self::assertSame('failed', $notice->getMailState());
        self::assertSame(0, $sender->execute(['--send' => true, '--retry' => true]));
        $this->em->refresh($notice); self::assertSame('sent', $notice->getMailState()); self::assertNull($notice->getMailError());
    }

    public function testAmbiguousMailClaimsAreNotAutomaticallyResent(): void
    {
        $shift = $this->shift('+3 days');
        $notice = new BarDutyNotification($this->alice->getMember(), $shift, 'claimed-test', 'Claimed message', 'Synthetic message');
        $notice->mailResult('sending'); $this->em->persist($notice); $this->em->flush();
        $_ENV['BAR_DUTY_MAIL_FROM'] = $_SERVER['BAR_DUTY_MAIL_FROM'] = 'bar@example.invalid';
        $mailer = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class); $mailer->expects(self::never())->method('send');
        $sender = new CommandTester(new SendBarDutyNotificationsCommand($this->em, $mailer, static::getContainer()->getParameter('kernel.project_dir')));
        self::assertSame(1, $sender->execute(['--send' => true])); self::assertSame(1, $sender->execute(['--send' => true, '--retry' => true]));
        self::assertStringContainsString('unklarem Versandstatus', $sender->getDisplay());
        self::assertSame('sending', $notice->getMailState());
    }

    public function testManagerEntersEventOnCalendarDayAndMemberClicksItToSignUp(): void
    {
        $date = new \DateTimeImmutable('+3 days');
        $crawler = $this->client->request('GET', '/bar-duty?month='.$date->format('Y-m'));
        self::assertResponseIsSuccessful();
        self::assertCount(7, $crawler->filter('[data-bar-duty-calendar] thead th'));
        $cell = $crawler->filter('[data-calendar-date="'.$date->format('Y-m-d').'"]');
        $this->client->click($cell->selectLink('Veranstaltung eintragen')->link()); self::assertResponseIsSuccessful();
        self::assertInputValueSame('date', $date->format('Y-m-d'));
        $this->client->submitForm('Veranstaltung / Serie im Kalender anlegen', [
            'title' => 'Clubhouse summer party', 'location' => 'Club garden', 'time' => '18:30', 'minutes' => '180', 'capacity' => '2',
        ]);
        self::assertResponseRedirects('/bar-duty?month='.$date->format('Y-m'), 303);
        $shift = $this->em->getRepository(BarShift::class)->findOneBy([]);
        self::assertSame('Clubhouse summer party', $shift->getTitle());
        $this->login($this->alice);
        $crawler = $this->client->request('GET', '/bar-duty?month='.$date->format('Y-m'));
        self::assertSelectorTextContains('[data-bar-event="'.$shift->getId().'"]', 'Noch niemand eingeteilt');
        self::assertSelectorTextContains('[data-bar-event="'.$shift->getId().'"]', '2 frei');
        self::assertSelectorNotExists('a[href^="/bar-duty/plan"]');
        $this->client->click($crawler->filter('[data-bar-event="'.$shift->getId().'"]')->link()); self::assertResponseIsSuccessful();
        $this->client->submitForm('Für Dienst anmelden'); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/bar-duty?month='.$date->format('Y-m'));
        self::assertSelectorTextContains('[data-bar-event="'.$shift->getId().'"]', 'alice Synthetic (du)');
        self::assertSelectorTextContains('[data-bar-event="'.$shift->getId().'"]', '1 frei');
        $this->login($this->bob); $this->client->request('GET', '/bar-duty?month='.$date->format('Y-m'));
        self::assertSelectorTextContains('[data-bar-event="'.$shift->getId().'"]', 'alice Synthetic');
        self::assertSelectorTextNotContains('[data-bar-event="'.$shift->getId().'"]', '(du)');
    }

    #[DataProvider('recurrences')]
    public function testWeeklyAndFortnightlyClubEveningsHaveExactDaysAndVisibleRoster(string $recurrence, array $offsets): void
    {
        $start = new \DateTimeImmutable('next monday +1 week');
        $this->post('plan', 0, $this->eventData($start) + [
            'recurrence' => $recurrence, 'until' => $start->modify('+16 days')->format('Y-m-d'), 'weekdays' => ['1', '3'],
        ]);
        $shifts = $this->em->getRepository(BarShift::class)->findBy([], ['startsAt' => 'ASC']);
        self::assertCount(count($offsets), $shifts);
        self::assertSame(array_map(static fn (int $offset): string => $start->modify('+'.$offset.' days')->format('Y-m-d'), $offsets),
            array_map(static fn (BarShift $shift): string => $shift->getStartsAt()->format('Y-m-d'), $shifts));
        foreach ($shifts as $shift) {
            self::assertSame('Club evening', $shift->getTitle()); self::assertSame('19:00', $shift->getStartsAt()->format('H:i'));
            self::assertSame(2, $shift->getCapacity()); self::assertSame(180, intdiv($shift->getEndsAt()->getTimestamp() - $shift->getStartsAt()->getTimestamp(), 60));
        }
        self::assertSame(1, $this->em->getRepository(BarShiftDefinition::class)->count([]));
        $this->post('assign', $shifts[0]->getId(), ['member' => $this->alice->getMember()->getId()]);
        $this->client->request('GET', '/bar-duty?month='.$start->format('Y-m'));
        self::assertResponseIsSuccessful(); self::assertSelectorTextContains('[data-bar-event="'.$shifts[0]->getId().'"]', 'alice Synthetic');
    }
    public static function recurrences(): iterable
    {
        yield 'weekly Monday Wednesday' => ['weekly', [0, 2, 7, 9, 14, 16]];
        yield 'fortnightly Monday Wednesday' => ['fortnightly', [0, 2, 14, 16]];
    }

    public function testSeriesConflictRollsBackAllNewDatesAndDoesNotCreateDuplicateDefinitions(): void
    {
        $start = new \DateTimeImmutable('next monday +1 week');
        $this->post('plan', 0, $this->eventData($start->modify('+7 days')) + ['recurrence' => 'once']);
        $this->post('plan', 0, $this->eventData($start) + ['recurrence' => 'weekly', 'until' => $start->modify('+14 days')->format('Y-m-d'), 'weekdays' => ['1']]);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_shift'));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_shift_definition'));
        self::assertSame($start->modify('+7 days')->format('Y-m-d').' 19:00:00', $this->em->getConnection()->fetchOne('SELECT starts_at FROM bar_shift'));
        $this->client->followRedirect(); self::assertSelectorTextContains('body', 'kein Termin der Anfrage angelegt');
    }

    public function testSingleSeriesDateCanBeCancelledWithoutChangingOtherDates(): void
    {
        $start = new \DateTimeImmutable('next monday +1 week');
        $this->post('plan', 0, $this->eventData($start) + ['recurrence' => 'weekly', 'until' => $start->modify('+14 days')->format('Y-m-d'), 'weekdays' => ['1']]);
        $shifts = $this->em->getRepository(BarShift::class)->findBy([], ['startsAt' => 'ASC']);
        $this->post('cancel', $shifts[1]->getId(), ['reason' => 'Club evening cancelled on this date only']);
        self::assertSame(2, $this->em->getRepository(BarShift::class)->count(['status' => 'open']));
        self::assertSame(1, $this->em->getRepository(BarShift::class)->count(['status' => 'cancelled']));
        $this->login($this->alice); $this->client->request('GET', '/bar-duty?month='.$shifts[1]->getStartsAt()->format('Y-m'));
        self::assertResponseIsSuccessful(); self::assertSelectorTextContains('[data-bar-event="'.$shifts[1]->getId().'"]', 'Abgesagt');
    }

    #[DataProvider('invalidSeries')]
    public function testInvalidSeriesDoesNotPersistAnyDefinitionOrDate(string $case): void
    {
        $start = new \DateTimeImmutable('next monday +1 week');
        $data = $this->eventData($start) + ['recurrence' => 'weekly', 'until' => $start->modify('+14 days')->format('Y-m-d'), 'weekdays' => ['1']];
        switch ($case) {
            case 'missing-end': $data['until'] = ''; break;
            case 'end-before-start': $data['until'] = $start->modify('-1 day')->format('Y-m-d'); break;
            case 'no-weekday': $data['weekdays'] = []; break;
            case 'invalid-weekday': $data['weekdays'] = ['8']; break;
            case 'nested-weekday': $data['weekdays'] = [['1']]; break;
            case 'no-matching-date': $data['until'] = $data['date']; $data['weekdays'] = ['2']; break;
            case 'unknown-recurrence': $data['recurrence'] = 'daily'; break;
            case 'past-date': $data['date'] = '2001-01-01'; $data['recurrence'] = 'once'; break;
        }
        $this->post('plan', 0, $data);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_shift'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM bar_shift_definition'));
    }
    public static function invalidSeries(): iterable
    {
        foreach (['missing-end', 'end-before-start', 'no-weekday', 'invalid-weekday', 'nested-weekday', 'no-matching-date', 'unknown-recurrence', 'past-date'] as $case) { yield $case => [$case]; }
    }
    private function eventData(\DateTimeImmutable $day): array
    {
        return ['title' => 'Club evening', 'location' => 'Clubhouse', 'time' => '19:00', 'minutes' => '180', 'capacity' => '2', 'date' => $day->format('Y-m-d')];
    }

    private function login(User $user): void
    {
        $this->client->loginUser($user);
        $this->client->request('GET', '/bar-duty'); self::assertResponseIsSuccessful();
    }
    private function post(string $operation, int $id, array $data = [], int $status = 303): void
    {
        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken('bar_duty_'.$operation.'_'.$id)->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally { $stack->pop(); }
        $this->client->request('POST', '/bar-duty/actions/'.$operation.'/'.$id, ['_token' => $token] + $data);
        self::assertResponseStatusCodeSame($status);
    }
    private function shift(string $day, int $capacity = 1, string $time = '18:00', int $minutes = 180): BarShift
    {
        $definition = new BarShiftDefinition($this->club, 'Test bar shift', 'Clubhouse', $time, $minutes, $capacity);
        $shift = new BarShift($definition, new \DateTimeImmutable((new \DateTimeImmutable($day))->format('Y-m-d').' '.$time));
        $this->em->persist($definition); $this->em->persist($shift); $this->em->flush(); return $shift;
    }
    private function pastShift(string $start): BarShift
    {
        $definition = new BarShiftDefinition($this->club, 'Past service', 'Clubhouse', '18:00', 180, 2);
        $shift = new BarShift($definition, new \DateTimeImmutable($start));
        $this->em->persist($definition); $this->em->persist($shift); $this->em->flush(); return $shift;
    }
    private function account(string $name, array $roles = [], ?Club $club = null): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Synthetic')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($club ?? $this->club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setIsVerified(true)->setMember($member)->setRoles($roles);
        $this->em->persist($user); $this->em->flush(); return $user;
    }
}
