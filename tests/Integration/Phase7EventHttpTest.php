<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\QueueClubEventRemindersCommand;
use App\Entity\Club;
use App\Entity\ClubEvent;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\ClubEventRegistration;
use App\Entity\Member;
use App\Entity\MembershipMessage;
use App\Entity\Person;
use App\Entity\Sport;
use App\Entity\User;
use App\Service\ClubCalendar;
use App\Service\ClubEventBooking;
use App\Service\ClubEventConflictService;
use App\Service\ClubEventSeries;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase7EventHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'CLUB_FUNCTION_MEMBERSHIP_ENABLED' => '1',
        ] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value;
            putenv($name.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Eventverein');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testRecurringDiveSeriesKeepsResponsibleDivisionAndDetectsConflicts(): void
    {
        $division = (new Sport())->setClub($this->club)->setName('Sporttauchen');
        $member = $this->account('leader', [User::ROLE_ADMIN])->getMember();
        $event = $this->event('Wochenendtauchgang', '+3 days 09:00');
        $event->setEventType('dive');
        $event->setDivision($division);
        $event->setResponsible($member);
        $event->setRecurrence('weekly');
        $event->setRecurrenceUntil((new \DateTimeImmutable('+17 days'))->setTime(0, 0));
        $this->em->persist($division);
        $this->em->persist($member);
        $this->em->persist($event);
        $this->em->flush();

        self::assertSame(2, static::getContainer()->get(ClubEventSeries::class)->materialize($event));
        $this->em->flush();
        $rows = $this->em->getRepository(ClubEvent::class)->findBy(['seriesParent' => $event], ['startsAt' => 'ASC']);
        self::assertCount(2, $rows);
        self::assertSame('dive', $rows[0]->getEventType());
        self::assertSame($division->getId(), $rows[0]->getDivision()?->getId());
        self::assertSame($member->getId(), $rows[0]->getResponsible()?->getId());

        $overlap = $this->event('Doppelbelegung', '+3 days 10:00');
        $overlap->setResponsible($member);
        self::assertStringContainsString('Wochenendtauchgang', static::getContainer()->get(ClubEventConflictService::class)->firstFor($overlap));
    }

    public function testRegistrationCancellationAndReminderQueueNotifications(): void
    {
        $user = $this->account('participant', []);
        $event = $this->event('Abendveranstaltung', '+2 days 19:00');
        $event->setPublished(true);
        $event->setCapacity(1);
        $event->setPricing('paid');
        $event->setMemberFeeCents(100);
        $event->setGuestFeeCents(200);
        $this->em->persist($event);
        $this->em->flush();

        $registration = static::getContainer()->get(ClubEventBooking::class)->register($event->getId(), $user);
        self::assertSame('registered', $registration->getStatus());
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));

        $reminders = new CommandTester(static::getContainer()->get(QueueClubEventRemindersCommand::class));
        self::assertSame(Command::SUCCESS, $reminders->execute([]));
        self::assertStringContainsString('1 Veranstaltungs-Erinnerungen vorbereitet', $reminders->getDisplay());
        self::assertSame(Command::SUCCESS, $reminders->execute([]));
        self::assertSame(2, $this->em->getRepository(MembershipMessage::class)->count([]));

        static::getContainer()->get(ClubEventBooking::class)->cancel($registration, $user->getPerson());
        self::assertSame(3, $this->em->getRepository(MembershipMessage::class)->count([]));
    }

    public function testCalendarFiltersEventByDivisionAndDoesNotLeakForeignDivision(): void
    {
        $member = $this->account('member', []);
        $own = (new Sport())->setClub($this->club)->setName('Eigene Sparte');
        $other = (new Sport())->setClub($this->club)->setName('Andere Sparte');
        $ownEvent = $this->event('Eigener Tauchgang', '+2 days 09:00');
        $ownEvent->setEventType('dive');
        $ownEvent->setDivision($own);
        $ownEvent->setPublished(true);
        $otherEvent = $this->event('Anderer Tauchgang', '+3 days 09:00');
        $otherEvent->setDivision($other);
        $otherEvent->setPublished(true);
        foreach ([$own, $other, $ownEvent, $otherEvent] as $entity) { $this->em->persist($entity); }
        $this->em->flush();

        $this->client->loginUser($member);
        $this->client->request('GET', '/calendar?month='.date('Y-m', strtotime('+2 days')).'&division='.$own->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-club-calendar]', 'Eigener Tauchgang');
        self::assertSelectorTextNotContains('[data-club-calendar]', 'Anderer Tauchgang');
        self::assertSelectorTextContains('[data-club-calendar]', 'Tauchgang');
        $this->client->request('GET', '/calendar?month='.date('Y-m').'&division=999999');
        self::assertResponseStatusCodeSame(404);
    }

    private function account(string $name, array $roles): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Mitglied')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setMember($member)->setIsVerified(true);
        $this->em->persist($user);
        if ($roles !== []) {
            $function = new ClubFunction($this->club, 'Funktion '.$name);
            $function->setPermissions($roles);
            $this->em->persist($function);
            $this->em->persist(new ClubFunctionAssignment($function, $user));
        }
        $this->em->flush();
        return $user;
    }

    private function event(string $title, string $start): ClubEvent
    {
        $startsAt = new \DateTimeImmutable($start);
        $event = new ClubEvent($this->club);
        $event->setTitle($title);
        $event->setDescription('Beschreibung');
        $event->setLocation('Bootshaus');
        $event->setStartsAt($startsAt);
        $event->setEndsAt($startsAt->modify('+2 hours'));
        return $event;
    }
}
