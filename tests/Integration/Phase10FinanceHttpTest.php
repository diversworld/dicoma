<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\ExportMembershipFinanceCommand;
use App\Command\MembershipDunningCommand;
use App\Entity\Club;
use App\Entity\ContributionClass;
use App\Entity\DivisionContribution;
use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\MembershipCharge;
use App\Entity\MembershipMessage;
use App\Entity\Person;
use App\Entity\Sport;
use App\Entity\User;
use App\Service\MembershipBillingService;
use App\Service\SingleClubMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase10FinanceHttpTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Club $club;
    private Member $member;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
        ] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }
        self::bootKernel(['environment' => 'test']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Finanzverein');
        $class = new ContributionClass($this->club);
        $class->setName('Erwachsene');
        $class->setAnnualCents(12000);
        $person = (new Person())->setFirstname('Finanz')->setLastname('Mitglied')->setEmail('finance@example.invalid');
        $this->member = new Member();
        $this->member->setClub($this->club);
        $this->member->setPerson($person);
        $this->member->setContributionClass($class);
        $division = (new Sport())->setClub($this->club)->setName('Sporttauchen');
        $membership = (new MemberSport())->setMember($this->member)->setSport($division);
        $surcharge = new DivisionContribution($division, 2026, 2500);
        $user = (new User())->setUser('finance-member')->setEmail('finance@example.invalid')->setPassword('unused')->setMember($this->member);
        foreach ([$this->club, $class, $person, $this->member, $division, $membership, $surcharge, $user] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
    }

    public function testBillingRunPreviewsSurchargeIssuesInvoicesAndRejectsDuplicateRun(): void
    {
        $billing = static::getContainer()->get(MembershipBillingService::class);
        $preview = $billing->preview($this->club, 2026, new \DateTimeImmutable('2026-01-31'));
        self::assertSame(1, $preview['members']);
        self::assertSame(1, $preview['charges']);
        self::assertSame(14500, $preview['amountCents']);
        $run = $billing->run($this->club, 2026, new \DateTimeImmutable('2026-01-31'), 'test');
        self::assertSame(2, $run->getIssuedCount());
        self::assertSame(14500, $run->getTotalCents());
        $charges = $this->em->getRepository(MembershipCharge::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $charges);
        self::assertSame(14500, array_sum(array_map(fn (MembershipCharge $charge): int => $charge->getAmountCents(), $charges)));
        foreach ($charges as $charge) { self::assertStringStartsWith('B-2026-', (string) $charge->getInvoiceNumber()); }
        $this->expectException(\DomainException::class);
        $billing->run($this->club, 2026, new \DateTimeImmutable('2026-01-31'), 'test');
    }

    public function testDunningIsIdempotentWithinRepeatPeriodAndIgnoresFreshClaims(): void
    {
        $charge = new MembershipCharge($this->member, 'special:test', 'Spenderbeitrag', 5000, new \DateTimeImmutable('-20 days'));
        $fresh = new MembershipCharge($this->member, 'special:fresh', 'Neuer Beitrag', 5000, new \DateTimeImmutable('-1 day'));
        $this->em->persist($charge);
        $this->em->persist($fresh);
        $this->em->flush();
        $command = new CommandTester(static::getContainer()->get(MembershipDunningCommand::class));
        self::assertSame(Command::SUCCESS, $command->execute(['--minimum-days' => '14', '--repeat-days' => '30']));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));
        self::assertSame(1, $charge->getReminderCount());
        self::assertSame(Command::SUCCESS, $command->execute(['--minimum-days' => '14', '--repeat-days' => '30']));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));
    }

    public function testFinanceExportRequiresConfirmationAndCreatesPrivateNewFile(): void
    {
        $charge = new MembershipCharge($this->member, 'special:export', 'Beitrag', 1000, new \DateTimeImmutable('2026-12-31'));
        $this->em->persist($charge);
        $this->em->flush();
        $path = sys_get_temp_dir().'/dicoma-finance-'.bin2hex(random_bytes(8)).'.csv';
        $command = new CommandTester(static::getContainer()->get(ExportMembershipFinanceCommand::class));
        self::assertSame(Command::FAILURE, $command->execute(['--year' => '2026', '--output' => $path]));
        self::assertFileDoesNotExist($path);
        self::assertSame(Command::SUCCESS, $command->execute(['--year' => '2026', '--output' => $path, '--confirm' => true]));
        self::assertSame(0600, fileperms($path) & 0777);
        self::assertStringContainsString('Finanz Mitglied', (string) file_get_contents($path));
        unlink($path);
    }
}
