<?php

declare(strict_types=1);
namespace App\Tests\Integration;

use App\Entity\{Club, ClubEvent, ClubEventPayment, ClubEventRegistration, Member, Person, User};
use App\Enum\MembershipStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ClubEventBookingHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $admin;
    private User $member;
    private User $guest;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach (['DATABASE_URL' => 'sqlite:///:memory:', 'MAILER_DSN' => 'null://null', 'PUBLIC_SITE_CLUB_ID' => '1'] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value; putenv($key.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']); $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Events club'); $this->em->persist($this->club); $this->em->flush();
        $this->admin = $this->account('admin', true, [User::ROLE_ADMIN]);
        $this->member = $this->account('member', true);
        $this->guest = $this->account('guest', false);
    }

    public function testBackendCreatesPaidEventAndGuestsAndMembersPayDifferentPrices(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/club-events/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        $this->client->submitForm('Veranstaltung speichern', [
            'club_event[title]' => 'Summer meeting', 'club_event[description]' => 'A club meeting with guests',
            'club_event[pricing]' => 'paid', 'club_event[memberFeeCents]' => '12,50', 'club_event[guestFeeCents]' => '20,00',
            'club_event[startsAt]' => (new \DateTimeImmutable('+10 days 18:00'))->format('Y-m-d\TH:i'),
            'club_event[endsAt]' => (new \DateTimeImmutable('+10 days 20:00'))->format('Y-m-d\TH:i'),
            'club_event[location]' => 'Clubhouse', 'club_event[capacity]' => '2', 'club_event[published]' => true,
        ]);
        self::assertResponseStatusCodeSame(303);
        $event = $this->em->getRepository(ClubEvent::class)->findOneBy([]);
        self::assertSame(1250, $event->getMemberFeeCents());
        self::assertSame(2000, $event->getGuestFeeCents());
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/events/'.$event->getId()); self::assertResponseIsSuccessful();
        $this->client->submitForm('Verbindlich anmelden'); self::assertResponseStatusCodeSame(303);
        $memberRegistration = $this->em->getRepository(ClubEventRegistration::class)->findOneBy(['person' => $this->member->getPerson()]);
        self::assertSame(1250, $memberRegistration->getFeeCents());
        $this->client->loginUser($this->guest);
        $this->client->request('GET', '/events/'.$event->getId());
        $this->client->submitForm('Verbindlich anmelden'); self::assertResponseStatusCodeSame(303);
        $guestRegistration = $this->em->getRepository(ClubEventRegistration::class)->findOneBy(['person' => $this->guest->getPerson()]);
        self::assertSame(2000, $guestRegistration->getFeeCents());
        $this->client->request('GET', '/event-registrations/'.$memberRegistration->getId());
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/club-events/'.$event->getId());
        self::assertSelectorTextContains('body', 'guest Synthetic');
        $this->postWithToken('/admin/club-events/registrations/'.$guestRegistration->getId().'/payment', 'event_payment_'.$guestRegistration->getId(),
            ['amount' => '20,00', 'method' => 'cash', 'reference' => 'guest-receipt']);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->em->getRepository(ClubEventPayment::class)->count([]));
    }

    public function testInvalidPricesNeverCreateAnEvent(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/club-events/new');
        $this->client->submitForm('Veranstaltung speichern', [
            'club_event[title]' => 'Invalid', 'club_event[description]' => 'Invalid prices',
            'club_event[pricing]' => 'paid', 'club_event[memberFeeCents]' => '', 'club_event[guestFeeCents]' => '0',
            'club_event[startsAt]' => (new \DateTimeImmutable('+10 days 18:00'))->format('Y-m-d\TH:i'),
            'club_event[endsAt]' => (new \DateTimeImmutable('+10 days 20:00'))->format('Y-m-d\TH:i'),
            'club_event[location]' => 'Clubhouse',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'positive Beträge');
        self::assertSame(0, $this->em->getRepository(ClubEvent::class)->count([]));
    }

    public function testDraftCanBeEditedBeforeTheFirstRegistration(): void
    {
        $event = $this->event('paid');
        $id = $event->getId();
        $this->em->find(ClubEvent::class, $id)->setPublished(false); $this->em->flush();
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/club-events/'.$id.'/edit');
        $this->client->submitForm('Veranstaltung speichern', ['club_event[title]' => 'Updated event', 'club_event[published]' => true]);
        self::assertResponseRedirects('/admin/club-events/'.$id, 303);
        $this->em->clear();
        $saved = $this->em->find(ClubEvent::class, $id);
        self::assertSame('Updated event', $saved->getTitle());
        self::assertTrue($saved->isPublished());
    }

    public function testFreeMemberEventRejectsGuestsAndWaitlistPromotesAfterCancellation(): void
    {
        $event = $this->event('members_free', 1);
        $this->client->loginUser($this->guest);
        $this->client->request('GET', '/events/'.$event->getId()); self::assertResponseStatusCodeSame(404);
        $this->postWithToken('/events/'.$event->getId().'/register', 'event_register_'.$event->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/events/'.$event->getId());
        $this->client->submitForm('Verbindlich anmelden');
        $first = $this->em->getRepository(ClubEventRegistration::class)->findOneBy(['person' => $this->member->getPerson()]);
        self::assertSame('registered', $first->getStatus()); self::assertSame(0, $first->getFeeCents());
        $secondUser = $this->account('second', true);
        $this->client->loginUser($secondUser);
        $this->client->request('GET', '/events/'.$event->getId()); $this->client->submitForm('Verbindlich anmelden');
        $second = $this->em->getRepository(ClubEventRegistration::class)->findOneBy(['person' => $secondUser->getPerson()]);
        self::assertSame('waitlisted', $second->getStatus());
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/event-registrations/'.$first->getId());
        $this->client->submitForm('Anmeldung stornieren');
        $this->em->clear();
        self::assertSame('cancelled', $this->em->find(ClubEventRegistration::class, $first->getId())->getStatus());
        self::assertSame('registered', $this->em->find(ClubEventRegistration::class, $second->getId())->getStatus());
    }

    public function testCsrfClubIsolationAndDuplicatePaymentsAreEnforced(): void
    {
        $event = $this->event('paid');
        $this->client->loginUser($this->guest);
        $this->client->request('POST', '/events/'.$event->getId().'/register', ['_token' => 'bad']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/events/'.$event->getId()); $this->client->submitForm('Verbindlich anmelden');
        $registration = $this->em->getRepository(ClubEventRegistration::class)->findOneBy([]);
        $this->client->loginUser($this->admin);
        $this->postWithToken('/admin/club-events/registrations/'.$registration->getId().'/payment', 'event_payment_'.$registration->getId(),
            ['amount' => '20,00', 'method' => 'transfer', 'reference' => 'unique']);
        self::assertResponseStatusCodeSame(303);
        $this->postWithToken('/admin/club-events/registrations/'.$registration->getId().'/payment', 'event_payment_'.$registration->getId(),
            ['amount' => '20,00', 'method' => 'transfer', 'reference' => 'unique']);
        self::assertSame(1, $this->em->getRepository(ClubEventPayment::class)->count([]));
        $otherClub = (new Club())->setName('Other club'); $this->em->persist($otherClub); $this->em->flush();
        $foreign = $this->account('foreign', true, [User::ROLE_ADMIN], $otherClub);
        $this->client->loginUser($foreign);
        $this->client->request('GET', '/admin/club-events/'.$event->getId());
        self::assertResponseStatusCodeSame(404);
        $this->postWithToken('/admin/club-events/registrations/'.$registration->getId().'/payment', 'event_payment_'.$registration->getId(), ['amount' => '-20', 'method' => 'cash', 'reference' => 'foreign']);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, $this->em->getRepository(ClubEventPayment::class)->count([]));
    }

    public function testDeadlinesAndDuplicateRegistrationsDoNotCreateAnotherBooking(): void
    {
        $event = $this->event('paid');
        $this->client->loginUser($this->guest);
        $this->client->request('GET', '/events/'.$event->getId()); $this->client->submitForm('Verbindlich anmelden');
        $this->postWithToken('/events/'.$event->getId().'/register', 'event_register_'.$event->getId());
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->em->getRepository(ClubEventRegistration::class)->count([]));
        $expired = $this->event('paid');
        $expired = $this->em->find(ClubEvent::class, $expired->getId());
        $expired->setDeadline(new \DateTimeImmutable('-1 hour')); $this->em->flush();
        $this->postWithToken('/events/'.$expired->getId().'/register', 'event_register_'.$expired->getId());
        self::assertResponseStatusCodeSame(303);
        self::assertSame(1, $this->em->getRepository(ClubEventRegistration::class)->count([]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'geschlossen');
    }

    public function testRefundAndEventCancellationKeepThePaymentJournal(): void
    {
        $event = $this->event('paid');
        $this->client->loginUser($this->guest);
        $this->client->request('GET', '/events/'.$event->getId()); $this->client->submitForm('Verbindlich anmelden');
        $registration = $this->em->getRepository(ClubEventRegistration::class)->findOneBy([]);
        $this->client->loginUser($this->admin);
        $path = '/admin/club-events/registrations/'.$registration->getId().'/payment';
        $token = 'event_payment_'.$registration->getId();
        $this->postWithToken($path, $token, ['amount' => '20,00', 'method' => 'cash', 'reference' => 'receipt']);
        $this->postWithToken($path, $token, ['amount' => '0,01', 'method' => 'cash', 'reference' => 'overpayment']);
        self::assertSame(1, $this->em->getRepository(ClubEventPayment::class)->count([]));
        $this->postWithToken('/admin/club-events/'.$event->getId().'/cancel', 'event_admin_cancel_'.$event->getId());
        self::assertResponseStatusCodeSame(303);
        $this->postWithToken($path, $token, ['amount' => '1', 'method' => 'cash', 'reference' => 'after-cancel']);
        self::assertSame(1, $this->em->getRepository(ClubEventPayment::class)->count([]));
        $this->postWithToken($path, $token, ['amount' => '-20,00', 'method' => 'cash', 'reference' => 'refund']);
        self::assertSame(2, $this->em->getRepository(ClubEventPayment::class)->count([]));
        $this->client->loginUser($this->guest);
        $this->client->request('GET', '/event-registrations/'.$registration->getId());
        self::assertSelectorTextContains('body', 'Veranstaltung abgesagt');
        self::assertSelectorTextContains('body', 'Erstattung offen: 0,00 EUR');
    }

    public function testEventAppearsInCalendarAndLockedDetailsCannotBeEditedAfterBooking(): void
    {
        $event = $this->event('paid');
        $id = $event->getId();
        $month = $event->getStartsAt()->format('Y-m');
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/calendar?month='.$month);
        self::assertSelectorExists('[data-event-key="club-event-'.$id.'"] a[href="/events/'.$id.'"]');
        $this->client->request('GET', '/events/'.$id); $this->client->submitForm('Verbindlich anmelden');
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/club-events/'.$id.'/edit');
        $this->client->submitForm('Veranstaltung speichern', ['club_event[title]' => 'Changed after booking']);
        self::assertSelectorNotExists('.invalid-feedback', $this->client->getCrawler()->filter('form[name="club_event"]')->text());
        self::assertSelectorTextContains('body', 'kann nicht geändert werden');
        $this->em->clear();
        self::assertSame('Test event', $this->em->find(ClubEvent::class, $id)->getTitle());
        $this->client->request('GET', '/admin/calendar?month='.$month);
        self::assertSelectorExists('[data-event-key="club-event-'.$id.'"] a[href="/admin/club-events/'.$id.'"]');
    }

    public function testInactiveWaitingMemberIsNotPromotedForFreeEvent(): void
    {
        $event = $this->event('members_free', 1);
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/events/'.$event->getId()); $this->client->submitForm('Verbindlich anmelden');
        $first = $this->em->getRepository(ClubEventRegistration::class)->findOneBy(['person' => $this->member->getPerson()]);
        $waiting = $this->account('waiting', true);
        $this->client->loginUser($waiting);
        $this->client->request('GET', '/events/'.$event->getId()); $this->client->submitForm('Verbindlich anmelden');
        $queued = $this->em->getRepository(ClubEventRegistration::class)->findOneBy(['person' => $waiting->getPerson()]);
        $this->em->find(Member::class, $waiting->getMember()->getId())->setMembershipStatus(MembershipStatus::FORMER);
        $this->em->flush();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/event-registrations/'.$first->getId()); $this->client->submitForm('Anmeldung stornieren');
        $this->em->clear();
        self::assertSame('ineligible', $this->em->find(ClubEventRegistration::class, $queued->getId())->getStatus());
    }

    private function event(string $pricing, ?int $capacity = null): ClubEvent
    {
        $event = new ClubEvent($this->em->find(Club::class, $this->club->getId()));
        $event->setTitle('Test event'); $event->setDescription('Synthetic event');
        $event->setPricing($pricing); $event->setMemberFeeCents($pricing === 'paid' ? 1000 : 0);
        $event->setGuestFeeCents($pricing === 'paid' ? 2000 : 0);
        $event->setStartsAt(new \DateTimeImmutable('+10 days')); $event->setEndsAt(new \DateTimeImmutable('+11 days'));
        $event->setLocation('Clubhouse'); $event->setCapacity($capacity); $event->setPublished(true);
        $this->em->persist($event); $this->em->flush();
        return $event;
    }

    private function account(string $name, bool $isMember, array $roles = [], ?Club $club = null): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Synthetic')->setEmail($name.'@example.invalid');
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setIsVerified(true)->setRoles($roles)->setPerson($person);
        if ($isMember) {
            $club = $this->em->find(Club::class, ($club ?? $this->club)->getId());
            $member = (new Member())->setClub($club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
            $user->setMember($member);
        }
        $this->em->persist($user); $this->em->flush(); return $user;
    }

    private function postWithToken(string $url, string $tokenId, array $data = []): void
    {
        $this->client->request('GET', '/events');
        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken($tokenId)->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally { $stack->pop(); }
        $this->client->request('POST', $url, ['_token' => $token] + $data);
    }
}
