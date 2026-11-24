<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\ContributionClass;
use App\Entity\FinanceAccount;
use App\Entity\FinanceTransaction;
use App\Entity\Member;
use App\Entity\MemberDocument;
use App\Entity\MembershipCharge;
use App\Entity\MembershipPolicy;
use App\Entity\Person;
use App\Entity\PrivacyPolicy;
use App\Entity\SepaDirectDebitBatch;
use App\Entity\SepaMandate;
use App\Entity\User;
use App\Enum\PermissionAction;
use App\Enum\PermissionModule;
use App\Security\ModuleAccess;
use App\Service\AuditLogger;
use App\Service\FinanceLedgerService;
use App\Service\FinanceChartService;
use App\Finance\Skr42Chart;
use App\Service\SepaDirectDebitService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhaseARbacFinanceComplianceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Club $club;
    private User $treasurer;
    private User $treasurerChecker;
    private User $board;
    private User $admin;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1', 'DICOMA_MEMBERSHIP_BANK_KEY' => base64_encode(str_repeat('k', 32))] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value; putenv($key.'='.$value);
        }
        self::bootKernel(['environment' => 'test']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Phase A Club');
        $this->treasurer = (new User())->setUser('treasurer')->setEmail('treasurer@example.invalid')->setPassword('x')->setRoles([User::ROLE_TREASURER]);
        $this->treasurerChecker = (new User())->setUser('treasurer-checker')->setEmail('treasurer-checker@example.invalid')->setPassword('x')->setRoles([User::ROLE_TREASURER]);
        $this->board = (new User())->setUser('board')->setEmail('board@example.invalid')->setPassword('x')->setRoles([User::ROLE_BOARD]);
        $this->admin = (new User())->setUser('admin')->setEmail('admin@example.invalid')->setPassword('x')->setRoles([User::ROLE_ADMIN]);
        foreach ([$this->club, $this->treasurer, $this->treasurerChecker, $this->board, $this->admin] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
    }

    public function testRoleHierarchyDoesNotCrossBusinessCapabilities(): void
    {
        $hierarchy = static::getContainer()->get(RoleHierarchyInterface::class);
        self::assertNotContains(User::ROLE_ADMIN, $hierarchy->getReachableRoleNames([User::ROLE_BOARD]));
        self::assertNotContains(User::ROLE_TREASURER, $hierarchy->getReachableRoleNames([User::ROLE_ADMIN]));
        self::assertNotContains(User::ROLE_TREASURER, $hierarchy->getReachableRoleNames([User::ROLE_BOARD]));
        $access = static::getContainer()->get(ModuleAccess::class);
        self::assertTrue($access->can($this->treasurer, $this->club, PermissionModule::FINANCE, PermissionAction::EDIT));
        self::assertFalse($access->can($this->treasurer, $this->club, PermissionModule::MEMBERSHIP, PermissionAction::READ));
        self::assertTrue($access->can($this->board, $this->club, PermissionModule::MEMBERSHIP, PermissionAction::EDIT));
        self::assertFalse($access->can($this->board, $this->club, PermissionModule::ACCOUNTING, PermissionAction::EDIT));
    }

    public function testBalancedLedgerAndAnnualCloseAreImmutable(): void
    {
        $bank = new FinanceAccount($this->club, '1200', 'Bank', FinanceAccount::TYPE_ASSET);
        $income = new FinanceAccount($this->club, '4000', 'Mitgliedsbeiträge', FinanceAccount::TYPE_INCOME);
        $expense = new FinanceAccount($this->club, '6100', 'Aufwand', FinanceAccount::TYPE_EXPENSE);
        $cash = new FinanceAccount($this->club, '1000', 'Kasse', FinanceAccount::TYPE_ASSET);
        foreach ([$bank, $income, $expense, $cash] as $entity) { $this->em->persist($entity); }
        $ledger = static::getContainer()->get(FinanceLedgerService::class);
        $ledger->post($this->club, $bank, $income, 10000, new \DateTimeImmutable('2025-02-01'), 'Jahresbeitrag', 'membership:1', $this->treasurer);
        $ledger->post($this->club, $expense, $cash, 2500, new \DateTimeImmutable('2025-02-02'), 'Material', 'expense:1', $this->treasurer);
        $this->em->flush();
        self::assertSame(10000, $ledger->balanceUntil($bank));
        self::assertSame(-2500, $ledger->balanceUntil($cash));
        $close = $ledger->closeYear($this->club, 2025, $this->treasurer);
        $this->em->flush();
        self::assertSame(10000, $close->getIncomeCents());
        self::assertSame(2500, $close->getExpenseCents());
        $this->expectException(\DomainException::class);
        $ledger->post($this->club, $bank, $income, 100, new \DateTimeImmutable('2025-03-01'), 'Nachträglich', 'late', $this->treasurer);
    }

    public function testSepaBatchRequiresDifferentApproverAndExportsPainXml(): void
    {
        $person = (new Person())->setFirstname('SEPA')->setLastname('Mitglied')->setEmail('sepa@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $class = new ContributionClass($this->club);
        $class->setName('Standard');
        $document = new MemberDocument($member, str_repeat('a', 32).'.pdf', 'Mandat.pdf', 'application/pdf', 10);
        $member->setContributionClass($class);
        $charge = new MembershipCharge($member, 'sepa:test', 'Mitgliedsbeitrag', 2500, new \DateTimeImmutable('-10 days'));
        $charge->issueInvoice('TEST-1');
        $key = str_repeat('k', 32);
        $encrypt = static function (string $value) use ($key): string {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return base64_encode($nonce.sodium_crypto_secretbox($value, $nonce, $key));
        };
        $mandate = new SepaMandate($member, 'MANDATE-1', 'DE98ZZZ09999999999', 'SEPA Mitglied', $encrypt('DE02100100100006820101'), '0101', new \DateTimeImmutable('-100 days'), $encrypt('PBNKDEFF'));
        $mandate->setDocument($document);
        $policy = new MembershipPolicy($this->club);
        $policy->setCreditorIdentifier('DE98ZZZ09999999999');
        $policy->setSepaCreditorName('Phase A Club');
        $policy->setSepaCreditorIban('DE89370400440532013000');
        $policy->setSepaCreditorBic('COBADEFFXXX');
        foreach ([$person, $member, $class, $document, $charge, $mandate, $policy] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $service = static::getContainer()->get(SepaDirectDebitService::class);
        $batch = $service->create($this->club, $this->treasurer, 'BATCH-1', new \DateTimeImmutable('+7 days'), [$charge->getId()]);
        $this->em->flush();
        try {
            $service->create($this->club, $this->treasurer, 'BATCH-2', new \DateTimeImmutable('+7 days'), [$charge->getId()]);
            self::fail('A charge must not be included in two active direct-debit batches.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('aktiven oder exportierten Lastschriftlauf', $exception->getMessage());
        }
        try {
            $service->approve($batch, $this->treasurer);
            self::fail('Same maker and approver must be rejected.');
        } catch (\DomainException) {
            self::assertSame(SepaDirectDebitBatch::STATUS_DRAFT, $batch->getStatus());
        }
        try {
            $service->approve($batch, $this->board);
            self::fail('Board must not approve finance batches.');
        } catch (\DomainException) {
            self::assertSame(SepaDirectDebitBatch::STATUS_DRAFT, $batch->getStatus());
        }
        $service->approve($batch, $this->treasurerChecker);
        $result = $service->export($batch, $this->treasurer);
        self::assertStringContainsString('pain.008.001.02', $result['xml']);
        self::assertStringContainsString('DE02100100100006820101', $result['xml']);
        self::assertStringContainsString('PBNKDEFF', $result['xml']);
        self::assertSame(hash('sha256', $result['xml']), $result['sha256']);
    }

    public function testAuditRedactsSensitiveContextAndCanHaveRetentionPolicy(): void
    {
        $policy = new PrivacyPolicy($this->club);
        $policy->setAuditRetentionYears(1);
        $this->em->persist($policy);
        $audit = static::getContainer()->get(AuditLogger::class);
        $audit->record($this->club, 'tester', 'test.secret', $this->club, ['iban' => 'DE00', 'nested' => ['token' => 'secret'], 'amountCents' => 100]);
        $this->em->flush();
        $event = $this->em->getRepository(\App\Entity\AuditEvent::class)->findOneBy(['club' => $this->club]);
        self::assertSame('[redacted]', $event->getContext()['iban']);
        self::assertSame('[redacted]', $event->getContext()['nested']['token']);
        self::assertSame(100, $event->getContext()['amountCents']);
    }

    public function testSkr42ChartImportIsIdempotentAndUsesProvidedAccountTypes(): void
    {
        $chart = static::getContainer()->get(FinanceChartService::class);
        $first = $chart->installSkr42($this->club, 'tester');
        $this->em->flush();
        self::assertSame(96, $first['total']);
        self::assertSame(96, $first['created']);
        $memberIncome = $this->em->getRepository(FinanceAccount::class)->findOneBy(['club' => $this->club, 'code' => '40000']);
        self::assertSame(FinanceAccount::TYPE_INCOME, $memberIncome->getType());
        self::assertSame('1', $memberIncome->getSphere());
        self::assertSame(Skr42Chart::SOURCE, $memberIncome->getSourceReference());
        $expense = $this->em->getRepository(FinanceAccount::class)->findOneBy(['club' => $this->club, 'code' => '60040']);
        self::assertSame(FinanceAccount::TYPE_EXPENSE, $expense->getType());
        $bank = $this->em->getRepository(FinanceAccount::class)->findOneBy(['club' => $this->club, 'code' => '19000']);
        self::assertSame(FinanceAccount::TYPE_ASSET, $bank->getType());
        self::assertStringContainsString('Internes Vereins-Unterkonto', $bank->getUsage());
        $youthFees = $this->em->getRepository(FinanceAccount::class)->findOneBy(['club' => $this->club, 'code' => '40010']);
        self::assertSame('1', $youthFees->getSphere());
        self::assertSame('Mitgliedsbeiträge Jugend', $youthFees->getName());
        $second = $chart->installSkr42($this->club, 'tester');
        self::assertSame(0, $second['created']);
        self::assertSame(96, $second['total']);
        self::assertSame(0, $second['updated']);
    }

    public function testRetentionCommandDryRunDoesNotDeleteExpiredAudit(): void
    {
        $policy = new PrivacyPolicy($this->club);
        $policy->setPurgeEnabled(true);
        $this->em->persist($policy);
        $this->em->persist(new \App\Entity\AuditEvent($this->club, 'tester', 'expired', Club::class, null, [], new \DateTimeImmutable('-1 day')));
        $this->em->flush();
        $tester = new \Symfony\Component\Console\Tester\CommandTester(static::getContainer()->get(\App\Command\PrivacyRetentionCommand::class));
        self::assertSame(0, $tester->execute([]));
        self::assertSame(1, $this->em->getRepository(\App\Entity\AuditEvent::class)->count([]));
        self::assertStringContainsString('"audit":1', $tester->getDisplay());
    }

    public function testRetentionRequiresAnExplicitPolicyAndSkipsIneligibleMembersBeforeLimit(): void
    {
        $expired = new \App\Entity\AuditEvent($this->club, 'tester', 'expired', Club::class, null, [], new \DateTimeImmutable('-1 day'));
        $this->em->persist($expired);
        $this->em->flush();

        $tester = new \Symfony\Component\Console\Tester\CommandTester(static::getContainer()->get(\App\Command\PrivacyRetentionCommand::class));
        self::assertSame(0, $tester->execute(['--apply' => true]));
        self::assertSame(1, $this->em->getRepository(\App\Entity\AuditEvent::class)->count([]));

        $policy = new PrivacyPolicy($this->club);
        $policy->setPurgeEnabled(true);
        $this->em->persist($policy);
        $blockedPerson = (new Person())->setFirstname('Neu')->setLastname('Finanzvorgang')->setEmail('blocked@example.invalid');
        $blocked = (new Member())->setClub($this->club)->setPerson($blockedPerson);
        $blocked->setMembershipStatus(\App\Enum\MembershipStatus::FORMER);
        $blocked->setArchivedAt(new \DateTimeImmutable('-5 years'));
        $eligiblePerson = (new Person())->setFirstname('Alt')->setLastname('Datensatz')->setEmail('eligible@example.invalid');
        $eligible = (new Member())->setClub($this->club)->setPerson($eligiblePerson);
        $eligible->setMembershipStatus(\App\Enum\MembershipStatus::FORMER);
        $eligible->setArchivedAt(new \DateTimeImmutable('-4 years'));
        $recentCharge = new MembershipCharge($blocked, 'retention:recent', 'Jüngerer Vorgang', 1000, new \DateTimeImmutable('-1 day'));
        foreach ([$blockedPerson, $blocked, $eligiblePerson, $eligible, $recentCharge] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        self::assertSame(0, $tester->execute(['--apply' => true, '--limit' => '1']));
        $this->em->clear();
        $storedBlocked = $this->em->find(Member::class, $blocked->getId());
        $storedEligible = $this->em->find(Member::class, $eligible->getId());
        self::assertSame('Neu', $storedBlocked->getPerson()->getFirstname());
        self::assertSame('Anonymisiert', $storedEligible->getPerson()->getFirstname());
    }

    public function testDunningUsesConfiguredDeadlineAndTemplate(): void
    {
        $policy = new MembershipPolicy($this->club);
        $policy->setDunningIntervals(['reminder' => 5, 'first' => 15, 'second' => 30, 'final' => 45]);
        $templates = $policy->getDunningTemplates();
        $templates['reminder'] = "Erinnerung {{ invoice }}\nBitte {{ amount }} EUR bis {{ dueDate }} zahlen.";
        $policy->setDunningTemplates($templates);
        $person = (new Person())->setFirstname('Mahn')->setLastname('Mitglied')->setEmail('dunning@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $charge = new MembershipCharge($member, 'dunning:configured', 'Beitrag', 1200, new \DateTimeImmutable('-6 days'));
        $charge->issueInvoice('M-1');
        foreach ([$policy, $person, $member, $charge] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $tester = new \Symfony\Component\Console\Tester\CommandTester(static::getContainer()->get(\App\Command\MembershipDunningCommand::class));
        self::assertSame(0, $tester->execute([]));
        $notice = $this->em->getRepository(\App\Entity\MembershipDunningNotice::class)->findOneBy(['charge' => $charge]);
        self::assertSame('Erinnerung M-1', $notice->getSubject());
        self::assertStringContainsString('12,00 EUR', $notice->getBody());
    }
}
