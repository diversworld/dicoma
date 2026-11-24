<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\BoardTaskRemindersCommand;
use App\Entity\BoardMeeting;
use App\Entity\BoardResolution;
use App\Entity\BoardTask;
use App\Entity\Club;
use App\Entity\ClubDocument;
use App\Entity\ClubDocumentVersion;
use App\Entity\MembershipMessage;
use App\Entity\User;
use App\Service\BoardGovernanceService;
use App\Service\BoardDashboard;
use App\Service\SimplePdfRenderer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class BoardGovernanceHttpTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Club $club;
    private User $actor;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        putenv('DATABASE_URL=sqlite:///:memory:');
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        self::bootKernel(['environment' => 'test']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Governance Test');
        $this->actor = (new User())->setUser('secretary')->setEmail('secretary@example.invalid')
            ->setPassword('unused')->setRoles([User::ROLE_SECRETARY]);
        $this->em->persist($this->club);
        $this->em->persist($this->actor);
        $this->em->flush();
    }

    public function testResolutionCreatesOneLinkedTaskAndCannotBeDuplicated(): void
    {
        $meeting = new BoardMeeting($this->club, $this->actor, 'Vorstandssitzung', new \DateTimeImmutable('+1 day'));
        $resolution = new BoardResolution($meeting, 'Satzung überarbeiten', 'Entwurf bis zur nächsten Sitzung vorlegen.');
        $resolution->setResponsible($this->actor)->setDueAt(new \DateTimeImmutable('+30 days'));
        $meeting->addResolution($resolution);
        $this->em->persist($meeting);
        $this->em->flush();

        $service = static::getContainer()->get(BoardGovernanceService::class);
        $task = $service->createTaskFromResolution($resolution, $this->actor);
        $this->em->flush();
        self::assertSame($resolution, $task->getResolution());
        self::assertSame($this->actor, $task->getAssignee());
        self::assertSame(1, $this->em->getRepository(BoardTask::class)->count(['resolution' => $resolution]));
        $this->expectException(\DomainException::class);
        $service->createTaskFromResolution($resolution, $this->actor);
    }

    public function testTaskReminderIsPreparedOnce(): void
    {
        $task = new BoardTask($this->club, $this->actor, 'Bericht vorbereiten');
        $task->setAssignee($this->actor)->setDueAt(new \DateTimeImmutable('+1 day'))->setRemindAt(new \DateTimeImmutable('-1 minute'));
        $this->em->persist($task);
        $this->em->flush();
        $command = new CommandTester(static::getContainer()->get(BoardTaskRemindersCommand::class));
        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertNotNull($task->getReminderSentAt());
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));
        self::assertSame(Command::SUCCESS, $command->execute([]));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));
    }

    public function testDashboardCountsAllOpenTasksWhileLimitingTheDisplayList(): void
    {
        for ($index = 1; $index <= 12; ++$index) {
            $this->em->persist(new BoardTask($this->club, $this->actor, 'Aufgabe '.$index));
        }
        $this->em->flush();

        $metrics = static::getContainer()->get(BoardDashboard::class)->forClub($this->club);

        self::assertSame(12, $metrics['openTasks']);
        self::assertCount(10, $metrics['tasks']);
    }

    public function testDocumentApprovalAndPdfRenderer(): void
    {
        $document = new ClubDocument($this->club, $this->actor, 'Satzung');
        $document->setCategory(ClubDocument::CATEGORY_STATUTES)->setMemberVisible(true);
        $version = new ClubDocumentVersion($document, 1, str_repeat('a', 32).'.pdf', 'Satzung.pdf', 'application/pdf', 10, $this->actor);
        $document->addVersion($version);
        $this->em->persist($document);
        $version->approve($this->actor);
        $document->setStatus(ClubDocument::STATUS_APPROVED);
        $this->em->flush();
        self::assertSame($this->actor, $version->getApprovedBy());
        self::assertTrue($document->isMemberVisible());
        self::assertStringStartsWith('%PDF-1.4', static::getContainer()->get(SimplePdfRenderer::class)->render('Mahnung', ['Offener Betrag: 25,00 EUR']));
    }
}
