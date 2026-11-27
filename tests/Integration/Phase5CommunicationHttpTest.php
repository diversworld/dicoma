<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Entity\Club;
use App\Entity\ClubEvent;
use App\Entity\ClubEventRegistration;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\Courses;
use App\Entity\DivisionRoleAssignment;
use App\Entity\InternalMessage;
use App\Entity\InternalMessageRecipient;
use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\MembershipMessage;
use App\Entity\Person;
use App\Entity\Schedule;
use App\Entity\Sport;
use App\Entity\User;
use App\Enum\DivisionRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase5CommunicationHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $sender;
    private User $recipient;
    private User $outsider;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite required.');
        }
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
        $this->club = (new Club())->setName('Kommunikationsverein');
        $this->em->persist($this->club);
        $this->em->flush();
        $this->sender = $this->account('sender');
        $this->recipient = $this->account('recipient');
        $this->outsider = $this->account('outsider');
    }

    public function testDirectMessageIsInboxScopedAndQueuesEmailNotification(): void
    {
        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/my/messages/new');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Nachricht senden')->form(), [
            'form[audience]' => 'member',
            'form[recipient]' => (string) $this->recipient->getMember()->getId(),
            'form[subject]' => 'Private Einladung',
            'form[body]' => 'Bitte antworte in DICOMA.',
        ]);
        self::assertResponseRedirects('/my/messages');
        $message = $this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Private Einladung']);
        self::assertNotNull($message);
        self::assertSame(1, $this->em->getRepository(InternalMessageRecipient::class)->count(['message' => $message]));
        self::assertSame(1, $this->em->getRepository(MembershipMessage::class)->count([]));

        $this->client->loginUser($this->outsider);
        $this->client->request('GET', '/my/messages/'.$message->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/my/messages');
        self::assertSelectorTextNotContains('body', 'Private Einladung');

        $foreignClub = (new Club())->setName('Fremder Verein');
        $foreignMember = (new Member())->setClub($foreignClub)
            ->setPerson((new Person())->setFirstname('Fremd')->setLastname('Empfänger')->setEmail('foreign@example.invalid'));
        $this->em->persist($foreignClub);
        $this->em->persist($foreignMember);
        $this->em->flush();
        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/my/messages/new');
        $values = $crawler->selectButton('Nachricht senden')->form()->getPhpValues();
        $values['form']['audience'] = 'member';
        $values['form']['recipient'] = (string) $foreignMember->getId();
        $values['form']['subject'] = 'Vereinsfremd';
        $values['form']['body'] = 'Nicht senden';
        $this->client->request('POST', '/my/messages/new', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Vereinsfremd']));

        $this->client->loginUser($this->recipient);
        $this->client->request('GET', '/my/messages');
        self::assertSelectorTextContains('body', 'Private Einladung');
        self::assertSelectorTextContains('body', 'Neu');
        $this->client->request('GET', '/my/messages/'.$message->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article', 'Bitte antworte in DICOMA.');
        $this->em->clear();
        $delivery = $this->em->getRepository(InternalMessageRecipient::class)->findOneBy([
            'message' => $message->getId(),
            'member' => $this->recipient->getMember()->getId(),
        ]);
        self::assertNotNull($delivery->getReadAt());
    }

    public function testOrdinaryMemberCanWriteToBoardButCannotForgeDivisionLeaderCampaign(): void
    {
        $board = $this->account('board-contact', [User::ROLE_BOARD]);
        $function = new ClubFunction($this->club, 'Vorstand-Kontakt');
        $function->setPermissions([User::ROLE_BOARD]);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $board));
        $this->em->flush();
        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/my/messages/new');
        self::assertSelectorExists('option[value="board"]');
        self::assertSelectorNotExists('option[value="division_leaders"]');
        $this->client->submit($crawler->selectButton('Nachricht senden')->form(), [
            'form[audience]' => 'board',
            'form[subject]' => 'Frage an den Vorstand',
            'form[body]' => 'Bitte um Rückmeldung.',
        ]);
        self::assertResponseRedirects('/my/messages');
        $message = $this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Frage an den Vorstand']);
        self::assertSame(1, $this->em->getRepository(InternalMessageRecipient::class)->count(['message' => $message]));
        self::assertSame($board->getMember()->getId(), $this->em->getRepository(InternalMessageRecipient::class)->findOneBy(['message' => $message])->getMember()->getId());

        $crawler = $this->client->request('GET', '/my/messages/new');
        $values = $crawler->selectButton('Nachricht senden')->form()->getPhpValues();
        $values['form']['audience'] = 'division_leaders';
        $values['form']['subject'] = 'Manipulierter Versand';
        $values['form']['body'] = 'Nicht erlaubt';
        $this->client->request('POST', '/my/messages/new', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Manipulierter Versand']));
    }

    public function testBoardCanMessageAllActiveDivisionLeadersOnce(): void
    {
        $firstDivision = $this->division('Erste Sparte');
        $secondDivision = $this->division('Zweite Sparte');
        foreach ([$firstDivision, $secondDivision] as $division) {
            $assignment = new DivisionRoleAssignment($division);
            $assignment->setMember($this->recipient->getMember());
            $assignment->setRole(DivisionRole::LEADER);
            $this->em->persist($assignment);
        }
        $expired = new DivisionRoleAssignment($secondDivision);
        $expired->setMember($this->outsider->getMember());
        $expired->setRole(DivisionRole::LEADER);
        $expired->setStartsAt(new \DateTimeImmutable('-2 years'));
        $expired->setEndsAt(new \DateTimeImmutable('-1 year'));
        $this->em->persist($expired);
        $this->em->flush();
        $boardFunction = new ClubFunction($this->club, 'Vorstand-Absender');
        $boardFunction->setPermissions([User::ROLE_BOARD]);
        $this->em->persist($boardFunction);
        $this->em->persist(new ClubFunctionAssignment($boardFunction, $this->sender));
        $this->em->flush();

        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/my/messages/new');
        self::assertSelectorExists('option[value="division_leaders"]');
        $this->client->submit($crawler->selectButton('Nachricht senden')->form(), [
            'form[audience]' => 'division_leaders',
            'form[subject]' => 'Rundmail Spartenleitungen',
            'form[body]' => 'Bitte Quartalsplanung einreichen.',
        ]);
        self::assertResponseRedirects('/my/messages');
        $message = $this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Rundmail Spartenleitungen']);
        self::assertSame(1, $this->em->getRepository(InternalMessageRecipient::class)->count(['message' => $message]));
    }

    public function testBoardInboxIsSharedForBoardMembersAndHiddenFromOtherMembers(): void
    {
        $boardOne = $this->account('board-one');
        $boardTwo = $this->account('board-two');
        $function = new ClubFunction($this->club, 'Gemeinsamer Vorstand');
        $function->setPermissions([User::ROLE_BOARD]);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $boardOne));
        $this->em->persist(new ClubFunctionAssignment($function, $boardTwo));
        $this->em->flush();
        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/my/messages/new');
        $this->client->submit($crawler->selectButton('Nachricht senden')->form(), [
            'form[audience]' => 'board',
            'form[subject]' => 'Gemeinsamer Vorstandsvorgang',
            'form[body]' => 'Für alle Vorstandsmitglieder.',
        ]);
        self::assertResponseRedirects('/my/messages');

        foreach ([$boardOne, $boardTwo] as $board) {
            $this->client->loginUser($board);
            $this->client->request('GET', '/admin/board-messages');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'Gemeinsamer Vorstandsvorgang');
            $message = $this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Gemeinsamer Vorstandsvorgang']);
            $this->client->request('GET', '/admin/board-messages/'.$message->getId());
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'Für alle Vorstandsmitglieder.');
        }
        $this->client->loginUser($this->recipient);
        $this->client->request('GET', '/admin/board-messages');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDivisionLeaderCanMessageOnlyOwnActiveDivisionMembers(): void
    {
        $division = $this->division('Tauchen');
        $other = $this->division('Jugend');
        $leader = new DivisionRoleAssignment($division);
        $leader->setMember($this->sender->getMember());
        $leader->setRole(DivisionRole::LEADER);
        $this->em->persist($leader);
        $this->em->persist((new MemberSport())->setMember($this->recipient->getMember())->setSport($division));
        $this->em->persist((new MemberSport())->setMember($this->outsider->getMember())->setSport($other));
        $this->em->flush();

        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/my/messages/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="form[division]"] option[value="'.$division->getId().'"]');
        self::assertSelectorNotExists('select[name="form[division]"] option[value="'.$other->getId().'"]');
        $form = $crawler->selectButton('Nachricht senden')->form();
        $this->client->submit($form, [
            'form[audience]' => 'division',
            'form[division]' => (string) $division->getId(),
            'form[subject]' => 'Sparten-Training',
            'form[body]' => 'Treffen am Samstag.',
        ]);
        self::assertResponseRedirects('/my/messages');
        $message = $this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Sparten-Training']);
        self::assertSame(1, $this->em->getRepository(InternalMessageRecipient::class)->count(['message' => $message]));

        $this->client->loginUser($this->outsider);
        $this->client->request('GET', '/my/messages');
        self::assertSelectorTextNotContains('body', 'Sparten-Training');

        $crawler = $this->client->request('GET', '/my/messages/new');
        $values = $crawler->selectButton('Nachricht senden')->form()->getPhpValues();
        $values['form']['audience'] = 'division';
        $values['form']['division'] = (string) $division->getId();
        $values['form']['subject'] = 'Manipuliert';
        $values['form']['body'] = 'Nicht erlaubt';
        $this->client->request('POST', '/my/messages/new', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Manipuliert']));
    }

    public function testDashboardShowsDivisionsMessagesAndPersonalCalendarWithoutForeignItems(): void
    {
        $division = $this->division('Sporttauchen');
        $this->em->persist((new MemberSport())->setMember($this->sender->getMember())->setSport($division));
        $ownCourse = (new Courses())->setClub($this->club)->setTitle('Eigener Kurs')->setCategory('training');
        $otherCourse = (new Courses())->setClub($this->club)->setTitle('Fremder Kurs')->setCategory('training');
        $ownSchedule = (new Schedule())->setCourses($ownCourse)->setTitle('Eigener Termin')
            ->setStartDate(new \DateTimeImmutable('+1 day'))->setStartTime(new \DateTimeImmutable('18:00'))->setLocation('Halle');
        $otherSchedule = (new Schedule())->setCourses($otherCourse)->setTitle('Fremder Termin')
            ->setStartDate(new \DateTimeImmutable('+2 day'))->setLocation('Fremd');
        $ownBooking = (new Booking())->setMember($this->sender->getMember())->setSchedule($ownSchedule)->setBookingdate(new \DateTimeImmutable('today'));
        $foreignBooking = (new Booking())->setMember($this->recipient->getMember())->setSchedule($otherSchedule)->setBookingdate(new \DateTimeImmutable('today'));
        $ownEvent = (new ClubEvent($this->club));
        $ownEvent->setTitle('Eigene Veranstaltung');
        $ownEvent->setDescription('Beschreibung');
        $ownEvent->setLocation('Clubheim');
        $ownEvent->setStartsAt(new \DateTimeImmutable('+3 day 19:00'));
        $ownEvent->setEndsAt(new \DateTimeImmutable('+3 day 21:00'));
        $ownRegistration = new ClubEventRegistration($ownEvent, $this->sender->getMember()->getPerson(), 'registered', 0, 'member');
        foreach ([$ownCourse, $otherCourse, $ownSchedule, $otherSchedule, $ownBooking, $foreignBooking, $ownEvent, $ownRegistration] as $entity) {
            $this->em->persist($entity);
        }
        $message = new InternalMessage($this->club, $this->recipient, 'member', 'Ungelesene Nachricht', 'Hallo');
        $this->em->persist($message);
        $this->em->persist(new InternalMessageRecipient($message, $this->sender->getMember()));
        $this->em->flush();

        $this->client->loginUser($this->sender);
        $this->client->request('GET', '/my');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sporttauchen');
        self::assertSelectorTextContains('body', '1');
        self::assertSelectorTextContains('body', 'Nachrichten');
        $this->client->request('GET', '/my/calendar');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Eigener Kurs');
        self::assertSelectorTextContains('body', 'Eigene Veranstaltung');
        self::assertSelectorTextNotContains('body', 'Fremder Kurs');
        self::assertSelectorTextNotContains('body', 'Fremder Termin');
    }

    public function testSuperAdminCanAddressEffectiveRoleWhileOtherMembersCannotForgeIt(): void
    {
        $super = $this->account('superadmin', [User::ROLE_SUPER_ADMIN]);
        $instructors = new ClubFunction($this->club, 'Test-Ausbilder');
        $instructors->setPermissions([User::ROLE_INSTRUCTOR]);
        $assignment = new ClubFunctionAssignment($instructors, $this->recipient);
        $this->em->persist($instructors);
        $this->em->persist($assignment);
        $this->em->flush();

        $this->client->loginUser($super);
        $crawler = $this->client->request('GET', '/my/messages/new');
        self::assertSelectorExists('select[name="form[role]"] option[value="'.User::ROLE_INSTRUCTOR.'"]');
        $this->client->submit($crawler->selectButton('Nachricht senden')->form(), [
            'form[audience]' => 'role',
            'form[role]' => User::ROLE_INSTRUCTOR,
            'form[subject]' => 'Ausbilderrunde',
            'form[body]' => 'Bitte Terminpläne prüfen.',
        ]);
        self::assertResponseRedirects('/my/messages');
        $message = $this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Ausbilderrunde']);
        self::assertSame(1, $this->em->getRepository(InternalMessageRecipient::class)->count(['message' => $message]));

        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/my/messages/new');
        self::assertSelectorNotExists('select[name="form[role]"] option[value="'.User::ROLE_INSTRUCTOR.'"]');
        $values = $crawler->selectButton('Nachricht senden')->form()->getPhpValues();
        $values['form']['audience'] = 'role';
        $values['form']['role'] = User::ROLE_INSTRUCTOR;
        $values['form']['subject'] = 'Verbotene Runde';
        $values['form']['body'] = 'Nicht senden';
        $this->client->request('POST', '/my/messages/new', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em->getRepository(InternalMessage::class)->findOneBy(['subject' => 'Verbotene Runde']));
    }

    private function account(string $name, array $roles = []): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Mitglied')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setMember($member)->setRoles($roles);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function division(string $name): Sport
    {
        $sport = (new Sport())->setClub($this->club)->setName($name);
        $this->em->persist($sport);
        $this->em->flush();
        return $sport;
    }
}
