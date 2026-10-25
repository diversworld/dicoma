<?php

declare(strict_types=1);
namespace App\Tests\Integration;

use App\Entity\{Club, Courses, Member, Person, Tournament, User, WebsitePublication};
use App\Enum\{CourseStatus, MembershipStatus, TournamentStatus};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\{DataProvider, PreserveGlobalState, RunTestsInSeparateProcesses};
use Symfony\Bundle\FrameworkBundle\{KernelBrowser, Test\WebTestCase};

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WebsitePublishingHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $press;
    private User $member;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:'; putenv('DATABASE_URL=sqlite:///:memory:');
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null'; putenv('MAILER_DSN=null://null');
        $_ENV['PUBLIC_SITE_CLUB_ID'] = $_SERVER['PUBLIC_SITE_CLUB_ID'] = '1'; putenv('PUBLIC_SITE_CLUB_ID=1');
        $_ENV['SITE_THEME'] = $_SERVER['SITE_THEME'] = 'airspace'; putenv('SITE_THEME=airspace');
        $this->client = static::createClient(['environment' => 'test']); $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Website club'); $this->em->persist($this->club); $this->em->flush();
        self::assertSame(1, $this->club->getId());
        $this->press = $this->account('press', [User::ROLE_PRESS_OFFICER]);
        $this->member = $this->account('member', []);
    }

    public function testPressCanDraftPublishEditAndWithdrawArticleWithoutAdminRights(): void
    {
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/editorial/new'); self::assertResponseIsSuccessful();
        $this->client->submitForm('Beitrag speichern', $this->formData());
        self::assertResponseStatusCodeSame(303);
        $publication = $this->em->getRepository(WebsitePublication::class)->findOneBy([]);
        self::assertFalse($publication->isPublished());
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/editorial/'.$publication->getId().'/edit');
        $this->client->submitForm('Beitrag speichern', ['website_publication[published]' => true]);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1', 'Club news');
        $this->client->request('GET', '/editorial/'.$publication->getId().'/edit');
        $this->client->submitForm('Beitrag speichern', ['website_publication[published]' => false, 'website_publication[title]' => 'Revised club news']);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseStatusCodeSame(404);
        self::assertCount(3, $this->em->find(WebsitePublication::class, $publication->getId())->getHistory());
    }

    public function testPublicPagesShowOnlyApprovedDueContentOfConfiguredClubAndEscapeHtml(): void
    {
        $visible = $this->publication('Visible <script>alert(1)</script>', true);
        $visible->setBody('<script>alert(1)</script>'."\n".'<img src=x onerror=alert(2)>'); $this->em->flush();
        $this->publication('Secret draft', false);
        $future = $this->publication('Future article', true); $future->setPublishAt(new \DateTimeImmutable('+1 day'));
        $other = (new Club())->setName('Other club'); $this->em->persist($other);
        $foreign = new WebsitePublication($other); $foreign->setTitle('Foreign article'); $foreign->setSummary('Foreign'); $foreign->setBody('Foreign');
        $foreign->setPublished(true); $this->em->persist($foreign); $this->em->flush();
        foreach (['/', '/articles'] as $url) {
            $this->client->request('GET', $url); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Visible');
            foreach (['Secret draft', 'Future article', 'Foreign article'] as $hidden) { self::assertSelectorTextNotContains('body', $hidden); }
            self::assertSelectorExists('body[data-site-theme="airspace"]');
        }
        $this->client->request('GET', '/news/'.$visible->getId()); self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('main script'); self::assertSelectorNotExists('main img'); self::assertSelectorTextContains('main', '<script>alert(1)</script>');
        $this->client->request('GET', '/news/'.$foreign->getId()); self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('editorRoles')]
    public function testPublicationRightsAreLimitedToBoardAndPress(string $role, bool $allowed): void
    {
        $user = $this->account('role-user', [$role]); $this->client->loginUser($user);
        $this->client->request('GET', '/editorial');
        self::assertResponseStatusCodeSame($allowed ? 200 : 403);
        $this->client->request('GET', '/editorial/new');
        self::assertResponseStatusCodeSame($allowed ? 200 : 403);
    }
    public static function editorRoles(): iterable
    {
        yield 'board' => [User::ROLE_BOARD, true]; yield 'press' => [User::ROLE_PRESS_OFFICER, true];
        yield 'admin' => [User::ROLE_ADMIN, true]; yield 'ordinary member' => [User::ROLE_MEMBER, false];
        yield 'bar manager' => [User::ROLE_BAR_MANAGER, false];
    }

    public function testForeignPressCannotReadOrEditWebsiteClubContent(): void
    {
        $publication = $this->publication('Website article', false);
        $other = (new Club())->setName('Other club'); $this->em->persist($other); $this->em->flush();
        $foreign = $this->account('foreign-press', [User::ROLE_PRESS_OFFICER], $other);
        $this->client->loginUser($foreign);
        foreach (['/editorial', '/editorial/new', '/editorial/'.$publication->getId().'/edit'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(1, $this->em->getRepository(WebsitePublication::class)->count([]));
    }

    public function testPublicationFormRejectsCsrfAndInvalidEventDates(): void
    {
        $this->client->loginUser($this->press);
        $this->client->request('POST', '/editorial/new', ['website_publication' => ['_token' => 'forged']]);
        self::assertResponseStatusCodeSame(422); self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
        $this->client->request('GET', '/editorial/new');
        $data = $this->formData(); $data['website_publication[kind]'] = 'event';
        $this->client->submitForm('Beitrag speichern', $data + ['website_publication[startsAt]' => '2027-01-01T18:00', 'website_publication[endsAt]' => '2027-01-01T17:00', 'website_publication[location]' => 'Clubhouse']);
        self::assertResponseStatusCodeSame(422); self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
    }

    public function testIndependentPublicEventIsListedWithDatesAndLocation(): void
    {
        $this->client->loginUser($this->press); $this->client->request('GET', '/editorial/new');
        $data = $this->formData(); $data['website_publication[kind]'] = 'event';
        $data += ['website_publication[startsAt]' => '2027-06-01T18:00', 'website_publication[endsAt]' => '2027-06-01T22:00', 'website_publication[location]' => 'Club garden', 'website_publication[published]' => true];
        $this->client->submitForm('Beitrag speichern', $data); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/events'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main', 'Club garden');
        self::assertSelectorTextContains('main', '01.06.2027 18:00');
    }

    #[DataProvider('eventSources')]
    public function testSourcesAreOptInAndCancelledSourcesHidePublicAnnouncement(string $kind): void
    {
        $source = $this->source($kind, $this->club);
        $this->client->request('GET', '/events'); self::assertResponseIsSuccessful(); self::assertSelectorTextNotContains('main', 'Internal source title');
        $this->client->loginUser($this->press); $this->client->request('GET', '/editorial/new');
        $data = $this->formData(); $data['website_publication[kind]'] = 'event';
        $data += ['website_publication[startsAt]' => '2027-06-01T18:00', 'website_publication[endsAt]' => '2027-06-01T22:00', 'website_publication[location]' => 'Public place', 'website_publication[published]' => true,
            'website_publication['.$kind.']' => (string) $source->getId()];
        $this->client->submitForm('Beitrag speichern', $data); self::assertResponseStatusCodeSame(303);
        $publication = $this->em->getRepository(WebsitePublication::class)->findOneBy([]);
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseIsSuccessful(); self::assertSelectorTextNotContains('main', 'Secret internal notes');
        $fresh = $this->em->find($source::class, $source->getId());
        $fresh->setStatus($kind === 'course' ? CourseStatus::CANCELLED : TournamentStatus::CANCELLED); $this->em->flush();
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/events'); self::assertSelectorTextNotContains('main', 'Club news');
    }
    public static function eventSources(): iterable { yield ['course']; yield ['tournament']; }

    public function testForeignSourceIdCannotBeInjectedIntoPublicEvent(): void
    {
        $other = (new Club())->setName('Foreign source club'); $this->em->persist($other); $this->em->flush();
        $source = $this->source('course', $other);
        $this->client->loginUser($this->press); $this->client->request('GET', '/editorial/new');
        $data = $this->formData(); $data['website_publication[kind]'] = 'event';
        $data += ['website_publication[startsAt]' => '2027-06-01T18:00', 'website_publication[endsAt]' => '2027-06-01T22:00', 'website_publication[location]' => 'Public place'];
        $form = $this->client->getCrawler()->selectButton('Beitrag speichern')->form();
        $values = $form->getPhpValues(); $values['website_publication'] = array_replace($values['website_publication'], ['course' => (string) $source->getId(), 'kind' => 'event', 'title' => 'Forged', 'summary' => 'Forged summary', 'body' => 'Forged body', 'startsAt' => '2027-06-01T18:00', 'endsAt' => '2027-06-01T22:00', 'location' => 'Public place']);
        $this->client->request('POST', '/editorial/new', $values); self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
    }

    public function testStaleEditorialFormCannotOverwriteAnotherEditorsChanges(): void
    {
        $p = $this->publication('Original title', false);
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/editorial/'.$p->getId().'/edit');
        $form = $this->client->getCrawler()->selectButton('Beitrag speichern')->form();
        $this->em->getConnection()->executeStatement('UPDATE website_publication SET title = ?, version = version + 1 WHERE id = ?', ['Newer title', $p->getId()]);
        $this->em->clear();
        $this->client->submit($form, ['website_publication[title]' => 'Stale edit']);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('Newer title', $this->em->getConnection()->fetchOne('SELECT title FROM website_publication WHERE id = ?', [$p->getId()]));
    }

    public function testForeignDraftIdCannotBeEditedByWebsitePress(): void
    {
        $club = (new Club())->setName('Foreign club'); $this->em->persist($club);
        $p = new WebsitePublication($club); $p->setTitle('Foreign draft'); $p->setSummary('Foreign summary'); $p->setBody('Foreign body');
        $this->em->persist($p); $this->em->flush();
        $this->client->loginUser($this->press); $this->client->request('GET', '/editorial/'.$p->getId().'/edit'); self::assertResponseStatusCodeSame(404);
    }

    public function testConfiguredWebsiteCourseCatalogDoesNotMixClubs(): void
    {
        $own = $this->source('course', $this->club); $own->setTitle('Website course');
        $other = (new Club())->setName('Other club'); $this->em->persist($other); $this->em->flush();
        $foreign = $this->source('course', $other); $foreign->setTitle('Foreign course'); $this->em->flush();
        $this->client->request('GET', '/my-training/courses'); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Website course'); self::assertSelectorTextNotContains('body', 'Foreign course');
    }

    private function publication(string $title, bool $published): WebsitePublication
    {
        $p = new WebsitePublication($this->club); $p->setTitle($title); $p->setSummary('Public summary'); $p->setBody('Public body'); $p->setPublished($published);
        $p->setPublishAt(new \DateTimeImmutable('-1 hour')); $this->em->persist($p); $this->em->flush(); return $p;
    }
    private function formData(): array
    {
        return ['website_publication[kind]' => 'article', 'website_publication[title]' => 'Club news', 'website_publication[summary]' => 'A public summary', 'website_publication[body]' => 'Public article text', 'website_publication[publishAt]' => '2026-01-01T12:00'];
    }
    private function source(string $kind, Club $club): Courses|Tournament
    {
        if ($kind === 'course') {
            $source = (new Courses())->setClub($club)->setTitle('Internal source title')->setCategory('training')->setStatus(CourseStatus::OPEN);
            $source->setNotes('Secret internal notes');
        } else {
            $source = new Tournament($club); $source->setName('Internal source title'); $source->setStatus(TournamentStatus::OPEN);
            $source->setStartsAt(new \DateTimeImmutable('2027-06-01 18:00')); $source->setEndsAt(new \DateTimeImmutable('2027-06-01 22:00'));
        }
        $this->em->persist($source); $this->em->flush(); return $source;
    }
    private function account(string $name, array $roles, ?Club $club = null): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Synthetic')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($club ?? $this->club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setIsVerified(true)->setMember($member)->setRoles($roles);
        $this->em->persist($user); $this->em->flush(); return $user;
    }
}
