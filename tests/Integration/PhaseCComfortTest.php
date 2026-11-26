<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\BoardMeeting;
use App\Entity\Club;
use App\Entity\Member;
use App\Entity\MembershipMessage;
use App\Entity\Person;
use App\Entity\User;
use App\Service\BoardDigest;
use App\Service\ClubAnnualExport;
use App\Service\MemberCsvImport;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhaseCComfortTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        putenv('DATABASE_URL=sqlite:///:memory:');
        self::bootKernel(['environment' => 'test']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Phase C Club');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testMemberImportDetectsDuplicatesAndDryRunPersistsNothing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dicoma-import-');
        file_put_contents($path, implode("\n", [
            'firstname;lastname;memberNumber;email;birthday;joinedAt;status',
            'Anna;Beispiel;M-100;anna@example.invalid;1985-04-03;2026-01-01;active',
            'Anna;Beispiel;M-100;other@example.invalid;1985-04-03;2026-01-01;active',
        ]));
        $service = static::getContainer()->get(MemberCsvImport::class);
        $preview = $service->run($this->club, $path, false);
        self::assertSame(2, $preview['rows']);
        self::assertSame(1, $preview['created']);
        self::assertSame([3], $preview['duplicates']);
        self::assertSame(0, $this->em->getRepository(Member::class)->count([]));
        $result = $service->run($this->club, $path, true);
        $this->em->flush();
        self::assertSame(1, $result['created']);
        self::assertSame(1, $this->em->getRepository(Member::class)->count([]));
        @unlink($path);
    }

    public function testDigestIsPersonalAndIdempotentAndMentionsMissingMinutes(): void
    {
        $person = (new Person())->setFirstname('Vorstand')->setLastname('Person')->setEmail('board@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $user = (new User())->setUser('board')->setEmail('board@example.invalid')->setPassword('x')->setRoles([User::ROLE_BOARD])->setMember($member);
        $meeting = new BoardMeeting($this->club, $user, 'Sitzung ohne Protokoll', new \DateTimeImmutable('-2 days'));
        foreach ([$person, $member, $user, $meeting] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $service = static::getContainer()->get(BoardDigest::class);
        $preview = $service->queue($this->club, 14, false);
        self::assertSame(1, $preview['queued']);
        self::assertSame(0, $this->em->getRepository(MembershipMessage::class)->count([]));
        $service->queue($this->club, 14, true);
        $this->em->flush();
        $service->queue($this->club, 14, true);
        $this->em->flush();
        $messages = $this->em->getRepository(MembershipMessage::class)->findAll();
        self::assertCount(1, $messages);
        self::assertStringContainsString('Ausstehende Protokolle', $messages[0]->getBody());
        self::assertStringContainsString('Sitzung ohne Protokoll', $messages[0]->getBody());
    }

    public function testAnnualPackageContainsReportAndCsvFiles(): void
    {
        $package = static::getContainer()->get(ClubAnnualExport::class)->create($this->club, (int) date('Y') - 1);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($package['path']));
        self::assertNotFalse($zip->locateName('jahresbericht.json'));
        self::assertNotFalse($zip->locateName('mitglieder.csv'));
        self::assertNotFalse($zip->locateName('zahlungen.csv'));
        $zip->close();
        @unlink($package['path']);
    }

    public function testDashboardPreferencesMustContainEachKpiExactlyOnce(): void
    {
        $user = new User();
        $governance = ['membersTotal', 'membersActive', 'membersPassive', 'memberDepartures', 'openTasks', 'openResolutions', 'pendingDocuments', 'newMemberApplications', 'upcomingMeetingCount'];
        $finance = ['openCents', 'overdueCents', 'paidCents', 'dunningCount', 'yearIncomeCents', 'yearExpenseCents', 'assetBalanceCents', 'sepaOpenCount'];
        $user->setDashboardPreferences(array_reverse($governance), array_reverse($finance));
        self::assertSame(array_reverse($governance), $user->getDashboardPreferences()['governance']);
        $this->expectException(\InvalidArgumentException::class);
        $user->setDashboardPreferences(['membersTotal'], $finance);
    }
}
