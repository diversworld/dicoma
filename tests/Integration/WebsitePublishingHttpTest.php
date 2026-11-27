<?php

declare(strict_types=1);
namespace App\Tests\Integration;

use App\Entity\{Club, ClubEvent, ClubFunction, ClubFunctionAssignment, Courses, MediaFile, Member, Person, Sport, Tournament, User, WebsiteDisplaySettings, WebsitePublication};
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
        $clubId = str_starts_with($this->name(), 'testUnconfiguredWebsiteCourseCatalog') ? '0' : '1';
        $_ENV['PUBLIC_SITE_CLUB_ID'] = $_SERVER['PUBLIC_SITE_CLUB_ID'] = $clubId; putenv('PUBLIC_SITE_CLUB_ID='.$clubId);
        $_ENV['SITE_THEME'] = $_SERVER['SITE_THEME'] = 'airspace'; putenv('SITE_THEME=airspace');
        $_ENV['CLUB_FUNCTION_MEMBERSHIP_ENABLED'] = $_SERVER['CLUB_FUNCTION_MEMBERSHIP_ENABLED'] = str_starts_with($this->name(), 'testFunctionModel') ? '1' : '0';
        $this->client = static::createClient(['environment' => 'test']); $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Website club'); $this->em->persist($this->club); $this->em->flush();
        self::assertSame(1, $this->club->getId());
        $this->press = $this->account('press', [User::ROLE_PRESS_OFFICER]);
        $this->member = $this->account('member', []);
    }

    public function testClubAndContactArePublicScopedAndNeverExposeInternalNotesOrUnsafeMarkup(): void
    {
        $this->club->setDescription('Public description <script>alert(1)</script>');
        $this->club->setEmail('club@example.invalid')->setPhone('0123456')
            ->setStreet('Club street 1')->setPostalCode('00123')->setCity('Club city')
            ->setWebsite('javascript:alert(1)')->setNotes('Private board notes');
        $other = (new Club())->setName('Foreign club')->setDescription('Foreign description')->setEmail('foreign@example.invalid');
        $this->em->persist($other);
        $this->em->flush();
        foreach (['/club', '/contact'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('[data-public-club]', 'Website club');
            self::assertSelectorTextNotContains('body', 'Private board notes');
            self::assertSelectorTextNotContains('body', 'Foreign description');
            self::assertSelectorTextNotContains('body', 'foreign@example.invalid');
            self::assertSelectorNotExists('main script');
            self::assertSelectorNotExists('a[href^="javascript:"]');
            self::assertSelectorExists('#frontendNavbar a[href="/club"]');
            self::assertSelectorExists('footer a[href="/contact"]');
        }
        self::assertSelectorTextContains('address', '00123');
        self::assertSelectorExists('a[href="mailto:club@example.invalid"]');
        $this->em->find(Club::class, $this->club->getId())->setWebsite('https://club.example.invalid');
        $this->em->flush();
        $this->client->request('GET', '/contact');
        self::assertSelectorExists('a[href="https://club.example.invalid"][rel="noopener noreferrer"]');
    }

    public function testInactiveOrUnconfiguredClubHasNoPublicInformationPageOrNavigationEntry(): void
    {
        $this->club->setActive(false);
        $this->em->flush();
        foreach (['/club', '/contact'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#frontendNavbar a[href="/club"]');
        self::assertSelectorNotExists('footer a[href="/contact"]');
        $_ENV['PUBLIC_SITE_CLUB_ID'] = $_SERVER['PUBLIC_SITE_CLUB_ID'] = '0';
        putenv('PUBLIC_SITE_CLUB_ID=0');
        static::getContainer()->get('kernel')->reboot(null);
        $this->client->request('GET', '/club');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInactiveWebsiteClubDoesNotExposeContentOrAcceptEventRegistrations(): void
    {
        $article = $this->publication('Inactive club article', true);
        $page = $this->publication('Inactive club page', true);
        $page->setKind('page');
        $image = new MediaFile($this->club, str_repeat('d', 40).'.png', 'page.png', 'image/png', 1, '');
        $page->setImage($image);
        $event = new ClubEvent($this->club);
        $event->setTitle('Inactive club event'); $event->setDescription('Event'); $event->setLocation('Club');
        $event->setPricing('paid'); $event->setStartsAt(new \DateTimeImmutable('+1 day'));
        $event->setEndsAt(new \DateTimeImmutable('+2 days')); $event->setPublished(true); $event->setImage($image);
        $this->em->persist($image); $this->em->persist($event); $this->em->flush();
        $articleId = $article->getId(); $pageId = $page->getId(); $eventId = $event->getId();
        foreach (['/news/'.$articleId, '/pages/'.$pageId, '/events/'.$eventId] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
        }
        $this->em->find(Club::class, $this->club->getId())->setActive(false); $this->em->flush();
        foreach (['/', '/articles', '/pages', '/events'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextNotContains('body', 'Inactive club article');
            self::assertSelectorTextNotContains('body', 'Inactive club page');
            self::assertSelectorTextNotContains('body', 'Inactive club event');
        }
        foreach (['/news/'.$articleId, '/pages/'.$pageId, '/news/'.$pageId.'/image', '/events/'.$eventId, '/events/'.$eventId.'/image'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $person = (new Person())->setFirstname('Guest')->setLastname('Test')->setEmail('guest@example.invalid');
        $guest = (new User())->setUser('guest')->setEmail('guest@example.invalid')->setPassword('unused')->setIsVerified(true)->setPerson($person);
        $this->em->persist($guest); $this->em->flush();
        $this->client->loginUser($guest);
        $this->client->request('GET', '/events');
        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = (string) static::getContainer()->get('security.csrf.token_manager')->getToken('event_register_'.$eventId);
            $this->client->getRequest()->getSession()->save();
        } finally {
            $stack->pop();
        }
        $this->client->request('POST', '/events/'.$eventId.'/register', ['_token' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testPressCanDraftPublishEditAndWithdrawArticleWithoutAdminRights(): void
    {
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/admin/de/editorial/new'); self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorExists('.form-switch input[type="checkbox"][name="website_publication[published]"].form-check-input');
        self::assertSelectorNotExists('input[name="website_publication[published]"].form-control');
        $this->client->submitForm('Beitrag speichern', $this->formData());
        self::assertResponseStatusCodeSame(303);
        $publication = $this->em->getRepository(WebsitePublication::class)->findOneBy([]);
        self::assertFalse($publication->isPublished());
        $this->client->request('POST', '/admin/de/editorial/'.$publication->getId().'/delete');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/de/editorial/'.$publication->getId().'/edit');
        $this->client->submitForm('Beitrag speichern', ['website_publication[published]' => true]);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('h1', 'Club news');
        $this->client->request('GET', '/admin/de/editorial/'.$publication->getId().'/edit');
        $this->client->submitForm('Beitrag speichern', ['website_publication[published]' => false, 'website_publication[title]' => 'Revised club news']);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/news/'.$publication->getId()); self::assertResponseStatusCodeSame(404);
        self::assertCount(3, $this->em->find(WebsitePublication::class, $publication->getId())->getHistory());
    }

    public function testEditorialSeparatesPagesArticlesAndAllEventSourcesUseBookingOverview(): void
    {
        $article = $this->publication('Editorial article', false);
        $page = $this->publication('Editorial page', false);
        $page->setKind('page');
        $legacy = $this->publication('Legacy event announcement', false);
        $legacy->setKind('event');
        $legacy->setLocation('Club');
        $legacy->setStartsAt(new \DateTimeImmutable('+2 days'));
        $legacy->setEndsAt(new \DateTimeImmutable('+2 days +1 hour'));
        $booking = new ClubEvent($this->club);
        $booking->setTitle('Bookable event');
        $booking->setDescription('Bookable');
        $booking->setLocation('Hall');
        $booking->setStartsAt(new \DateTimeImmutable('+3 days'));
        $booking->setEndsAt(new \DateTimeImmutable('+3 days +2 hours'));
        $this->em->persist($booking);
        $this->em->flush();
        $this->client->loginUser($this->press);

        $this->client->request('GET', '/admin/de/editorial/articles');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Editorial article');
        self::assertSelectorTextNotContains('body', 'Editorial page');
        self::assertSelectorTextNotContains('body', 'Legacy event announcement');
        self::assertSelectorExists('a[href$="/editorial/articles/new"]');
        $this->client->request('GET', '/admin/de/editorial/articles/new');
        self::assertSelectorNotExists('input[name="website_publication[sortOrder]"]');
        self::assertSelectorNotExists('input[name="website_publication[menuVisible]"]');
        self::assertSelectorNotExists('input[name="website_publication[startsAt]"]');

        $this->client->request('GET', '/admin/de/editorial/pages');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Editorial page');
        self::assertSelectorTextNotContains('body', 'Editorial article');
        $this->client->request('GET', '/admin/de/editorial/pages/new');
        self::assertSelectorExists('input[name="website_publication[sortOrder]"]');
        self::assertSelectorExists('input[name="website_publication[menuVisible]"]');
        self::assertSelectorNotExists('input[name="website_publication[startsAt]"]');

        $this->client->request('GET', '/admin/club-events');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bookable event');
        self::assertSelectorTextContains('body', 'Legacy event announcement');
        self::assertSelectorExists('a[href$="/editorial/'.$legacy->getId().'/edit"]');
    }

    public function testDivisionAreasShowOnlyExplicitlyPublishedContentAndHideInactiveDivisions(): void
    {
        $division = (new Sport())->setClub($this->club)->setName('Public division')->setDescription('Internal division secret');
        $inactive = (new Sport())->setClub($this->club)->setName('Inactive division')->setActive(false);
        $otherClub = (new Club())->setName('Foreign club');
        $foreign = (new Sport())->setClub($otherClub)->setName('Foreign division');
        foreach ([$division, $inactive, $otherClub, $foreign] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $page = $this->publication('Division page', true); $page->setKind('page'); $page->setDivision($division);
        $draft = $this->publication('Division draft', false); $draft->setDivision($division);
        $future = $this->publication('Division future', true); $future->setDivision($division); $future->setPublishAt(new \DateTimeImmutable('+1 day'));
        $inactiveArticle = $this->publication('Inactive division article', true); $inactiveArticle->setDivision($inactive);
        $crossClub = $this->publication('Foreign division article', true); $crossClub->setDivision($foreign);
        $image = new MediaFile($this->club, str_repeat('e', 40).'.png', 'division.png', 'image/png', 1, '');
        $page->setImage($image); $this->em->persist($image); $this->em->flush();
        $divisionId = $division->getId(); $inactiveId = $inactive->getId(); $foreignId = $foreign->getId(); $pageId = $page->getId();
        $this->client->request('GET', '/divisions');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#frontendNavbar a[href="/divisions"]');
        self::assertSelectorExists('main a[href="/divisions/'.$divisionId.'"]');
        self::assertSelectorTextNotContains('body', 'Internal division secret');
        self::assertSelectorTextNotContains('main', 'Inactive division');
        self::assertSelectorTextNotContains('main', 'Foreign division');
        $this->client->request('GET', '/divisions/'.$divisionId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main a[href="/pages/'.$pageId.'"]');
        foreach (['Internal division secret', 'Division draft', 'Division future', 'Inactive division article', 'Foreign division article'] as $hidden) {
            self::assertSelectorTextNotContains('main', $hidden);
        }
        foreach (['/divisions/'.$inactiveId, '/divisions/'.$foreignId, '/news/'.$inactiveArticle->getId(), '/news/'.$crossClub->getId(), '/news/'.$future->getId()] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/pages/'.$pageId);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main a[href="/divisions/'.$divisionId.'"]');
        $this->em->find(Sport::class, $divisionId)->setActive(false); $this->em->flush();
        foreach (['/divisions/'.$divisionId, '/pages/'.$pageId, '/news/'.$pageId.'/image'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('#frontendNavbar a[href="/pages/'.$pageId.'"]');
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/admin/de/editorial');
        self::assertSelectorTextContains('body', 'Public division (inaktiv)');
        $this->client->request('GET', '/admin/de/editorial/'.$pageId.'/edit');
        self::assertSelectorExists('select[name="website_publication[division]"] option[value="'.$divisionId.'"]');
        $this->client->submitForm('Beitrag speichern', ['website_publication[division]' => '']);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/pages/'.$pageId);
        self::assertResponseIsSuccessful();
    }

    public function testEditorialDivisionChoiceIsRestrictedToActiveOwnClub(): void
    {
        $own = (new Sport())->setClub($this->club)->setName('Own division');
        $inactive = (new Sport())->setClub($this->club)->setName('Inactive division')->setActive(false);
        $other = (new Club())->setName('Other club');
        $foreign = (new Sport())->setClub($other)->setName('Foreign division');
        foreach ([$own, $inactive, $other, $foreign] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $this->client->loginUser($this->press);
        $crawler = $this->client->request('GET', '/admin/de/editorial/new');
        self::assertSelectorExists('select[name="website_publication[division]"] option[value="'.$own->getId().'"]');
        self::assertSelectorNotExists('select[name="website_publication[division]"] option[value="'.$inactive->getId().'"]');
        self::assertSelectorNotExists('select[name="website_publication[division]"] option[value="'.$foreign->getId().'"]');
        $form = $crawler->selectButton('Beitrag speichern')->form();
        foreach ($this->formData() as $name => $value) { $form[$name] = $value; }
        $values = $form->getPhpValues();
        foreach ([$inactive->getId(), $foreign->getId()] as $id) {
            $values['website_publication']['division'] = (string) $id;
            $this->client->request('POST', '/admin/de/editorial/new', $values);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
        }
        $this->client->request('GET', '/admin/de/editorial/new');
        $this->client->submitForm('Beitrag speichern', $this->formData() + ['website_publication[division]' => (string) $own->getId()]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame($own->getId(), $this->em->getRepository(WebsitePublication::class)->findOneBy([])->getDivision()->getId());
    }

    public function testDivisionWithEditorialContentCannotBeDeletedIndividuallyOrInBatch(): void
    {
        $used = (new Sport())->setClub($this->club)->setName('Used division');
        $unused = (new Sport())->setClub($this->club)->setName('Unused division');
        $this->em->persist($used); $this->em->persist($unused); $this->em->flush();
        $page = $this->publication('Draft assigned to division', false);
        $page->setDivision($used); $this->em->flush();
        $usedId = $used->getId(); $unusedId = $unused->getId();
        $this->client->loginUser($this->account('division-super', [User::ROLE_SUPER_ADMIN]));
        $this->client->request('GET', '/admin/de/sport');
        self::assertResponseIsSuccessful();
        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = static::getContainer()->get('security.csrf.token_manager')->getToken('ea-batch-action-batchDelete-'.Sport::class)->getValue();
            $this->client->getRequest()->getSession()->save();
        } finally {
            $stack->pop();
        }
        $this->client->request('POST', '/admin/de/sport/'.$usedId.'/delete');
        self::assertResponseStatusCodeSame(409);
        $this->client->request('POST', '/admin/de/sport/batch-delete', [
            'batchActionName' => 'batchDelete', 'entityFqcn' => Sport::class,
            'batchActionCsrfToken' => $token, 'batchActionEntityIds' => [(string) $unusedId, (string) $usedId],
        ]);
        self::assertResponseStatusCodeSame(409);
        $this->em->clear();
        self::assertNotNull($this->em->find(Sport::class, $usedId));
        self::assertNotNull($this->em->find(Sport::class, $unusedId));
    }

    public function testEditorsCanPublishAndWithdrawPagesWithoutExposingDraftsOrOtherClubs(): void
    {
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/de/editorial/new');
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/admin/de/editorial/new');
        $data = $this->formData();
        $data['website_publication[kind]'] = 'page';
        $data['website_publication[title]'] = 'Club information';
        $data['website_publication[body]'] = 'Page text <script>alert(1)</script>';
        $this->client->submitForm('Beitrag speichern', $data);
        self::assertResponseStatusCodeSame(303);
        $page = $this->em->getRepository(WebsitePublication::class)->findOneBy(['title' => 'Club information']);
        self::assertNotNull($page);
        $id = $page->getId();
        $this->client->request('GET', '/pages/'.$id);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('#frontendNavbar a[href="/pages/'.$id.'"]');
        $this->client->request('GET', '/admin/de/editorial/'.$id.'/edit');
        $this->client->submitForm('Beitrag speichern', ['website_publication[published]' => true]);
        self::assertResponseStatusCodeSame(303);

        $future = new WebsitePublication($this->em->find(Club::class, $this->club->getId()));
        $future->setTitle('Future page'); $future->setSummary('Summary'); $future->setBody('Scheduled content');
        $future->setKind('page'); $future->setPublished(true); $future->setPublishAt(new \DateTimeImmutable('+1 day'));
        $foreignClub = (new Club())->setName('Foreign club');
        $foreign = new WebsitePublication($foreignClub);
        $foreign->setTitle('Foreign page'); $foreign->setSummary('Summary'); $foreign->setBody('Foreign page content');
        $foreign->setKind('page'); $foreign->setPublished(true); $foreign->setPublishAt(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($future); $this->em->persist($foreignClub); $this->em->persist($foreign); $this->em->flush();
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', '/pages');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#frontendNavbar a[href="/pages"]');
        self::assertSelectorExists('#frontendNavbar > ul > li > a[href="/pages/'.$id.'"]');
        self::assertSelectorExists('footer a[href="/pages/'.$id.'"]');
        self::assertSelectorNotExists('#frontendNavbar a[href="/pages/'.$future->getId().'"]');
        self::assertSelectorNotExists('#frontendNavbar a[href="/pages/'.$foreign->getId().'"]');
        self::assertSelectorExists('a[href="/pages/'.$id.'"]');
        self::assertSelectorTextNotContains('body', 'Future page');
        self::assertSelectorTextNotContains('body', 'Foreign page');
        $this->client->request('GET', '/pages/'.$id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Club information');
        self::assertSelectorExists('#frontendNavbar a[href="/pages/'.$id.'"][aria-current="page"]');
        self::assertSelectorNotExists('main script');
        $this->client->request('GET', '/news/'.$id);
        self::assertResponseStatusCodeSame(404);
        foreach ([$future->getId(), $foreign->getId()] as $hiddenId) {
            $this->client->request('GET', '/pages/'.$hiddenId);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/admin/de/editorial/'.$id.'/edit');
        $this->client->submitForm('Beitrag speichern', ['website_publication[published]' => false]);
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/pages/'.$id);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/pages');
        self::assertSelectorTextNotContains('body', 'Club information');
        self::assertSelectorNotExists('#frontendNavbar a[href="/pages/'.$id.'"]');
        self::assertSelectorNotExists('footer a[href="/pages/'.$id.'"]');
    }

    public function testFunctionModelEditorialReadIsSeparateFromWritesAndLegacyRoles(): void
    {
        $publication = $this->publication('Private draft', false);
        $id = $publication->getId();
        $function = new ClubFunction($this->club, 'Redaktion lesen');
        $function->setModulePermissions(['website:read']);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $this->member));
        $this->em->flush();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/portal/de');
        self::assertSelectorExists('a[href="/admin/de/editorial"]');
        $this->client->request('GET', '/admin/de/editorial');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Private draft');
        self::assertSelectorNotExists('a[href="/admin/de/editorial/new"]');
        self::assertSelectorNotExists('a[href="/admin/de/editorial/'.$id.'/edit"]');
        self::assertSelectorNotExists('form[action="/admin/de/editorial/'.$id.'/publication"]');
        foreach (['/admin/de/editorial/new', '/admin/de/editorial/'.$id.'/edit'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/de/editorial/new');
        self::assertResponseStatusCodeSame(403);

        $legacy = new ClubFunction($this->em->find(Club::class, $this->club->getId()), 'Alte Pressefunktion');
        $legacy->setPermissions([User::ROLE_PRESS_OFFICER]);
        $this->em->persist($legacy);
        $this->em->persist(new ClubFunctionAssignment($legacy, $this->em->find(User::class, $this->press->getId())));
        $this->em->flush();
        $this->client->loginUser($this->em->find(User::class, $this->press->getId()));
        $this->client->request('GET', '/admin/de/editorial');
        self::assertResponseStatusCodeSame(403);
    }

    public function testFunctionModelPagePublishingRechecksWebsitePermissions(): void
    {
        $function = new ClubFunction($this->club, 'Pages');
        $function->setModulePermissions(['website:create']);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $this->member));
        $this->em->flush();
        $functionId = $function->getId();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/de/editorial/new');
        $data = $this->formData();
        $data['website_publication[kind]'] = 'page';
        $data['website_publication[title]'] = 'Function page';
        $this->client->submitForm('Beitrag speichern', $data);
        self::assertResponseRedirects('/portal', 303);
        $page = $this->em->getRepository(WebsitePublication::class)->findOneBy(['title' => 'Function page']);
        self::assertNotNull($page);
        $id = $page->getId();
        $this->client->request('GET', '/admin/de/editorial/'.$id.'/edit');
        self::assertResponseStatusCodeSame(403);
        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['website:edit']); $this->em->flush();
        $this->client->request('GET', '/admin/de/editorial/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $form = $this->client->getCrawler()->selectButton('Beitrag speichern')->form(['website_publication[published]' => true]);
        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions([]); $this->em->flush();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/pages/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testFunctionModelEditorialDeleteRequiresPermissionCsrfAndCurrentVersion(): void
    {
        $page = $this->publication('Deletable page', true);
        $page->setKind('page');
        $function = new ClubFunction($this->club, 'Delete pages');
        $function->setModulePermissions(['website:read']);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $this->member));
        $this->em->flush();
        $id = $page->getId(); $functionId = $function->getId();
        $url = '/admin/de/editorial/'.$id.'/delete';
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/de/editorial');
        self::assertSelectorNotExists('form[action="'.$url.'"]');
        $this->client->request('POST', $url, ['_token' => 'bad', 'version' => 1]);
        self::assertResponseStatusCodeSame(403);

        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['website:read', 'website:delete']); $this->em->flush();
        $crawler = $this->client->request('GET', '/admin/de/editorial');
        $form = $crawler->filter('form[action="'.$url.'"]')->form();
        $foreignClub = (new Club())->setName('Foreign club');
        $foreign = new WebsitePublication($foreignClub);
        $foreign->setTitle('Foreign page'); $foreign->setSummary('Summary'); $foreign->setBody('Foreign page content');
        $foreign->setKind('page');
        $this->em->persist($foreignClub); $this->em->persist($foreign); $this->em->flush();
        $this->client->request('POST', '/admin/de/editorial/'.$foreign->getId().'/delete', ['_token' => 'bad', 'version' => 1]);
        self::assertResponseStatusCodeSame(404);
        self::assertNotNull($this->em->find(WebsitePublication::class, $foreign->getId()));
        $this->client->request('POST', $url, ['_token' => 'bad', 'version' => 1]);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em->find(WebsitePublication::class, $id));
        $this->em->getConnection()->executeStatement('UPDATE website_publication SET version = version + 1 WHERE id = ?', [$id]);
        $this->em->clear();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(303);
        self::assertNotNull($this->em->find(WebsitePublication::class, $id));
        $crawler = $this->client->request('GET', '/admin/de/editorial');
        self::assertSelectorTextContains('body', 'zwischenzeitlich geändert');
        $form = $crawler->filter('form[action="'.$url.'"]')->form();
        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['website:read']); $this->em->flush();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->em->find(WebsitePublication::class, $id));
        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['website:read', 'website:delete']); $this->em->flush();
        $crawler = $this->client->request('GET', '/admin/de/editorial');
        $this->client->submit($crawler->filter('form[action="'.$url.'"]')->form());
        self::assertResponseRedirects('/admin/de/editorial', 303);
        self::assertNull($this->em->find(WebsitePublication::class, $id));
        $this->client->request('GET', '/pages/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testFunctionModelEditorialCreateAndEditRecheckCurrentPermission(): void
    {
        $function = new ClubFunction($this->club, 'Redaktion schreiben');
        $function->setModulePermissions(['website:create']);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $this->member));
        $this->em->flush();
        $functionId = $function->getId();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/portal/de');
        self::assertSelectorExists('a[href="/admin/de/editorial/new"]');
        $this->client->request('GET', '/admin/de/editorial/new');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/admin/de/editorial"]');
        $this->client->submitForm('Beitrag speichern', $this->formData());
        self::assertResponseRedirects('/portal', 303);
        $publication = $this->em->getRepository(WebsitePublication::class)->findOneBy(['title' => 'Club news']);
        self::assertNotNull($publication);
        $id = $publication->getId();
        $this->client->request('GET', '/admin/de/editorial');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/de/editorial/'.$id.'/edit');
        self::assertResponseStatusCodeSame(403);

        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['website:edit']);
        $this->em->flush();
        $this->client->request('GET', '/admin/de/editorial/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Beitrag speichern', ['website_publication[title]' => 'Updated']);
        self::assertResponseRedirects('/portal', 303);
        self::assertSame('Updated', $this->em->find(WebsitePublication::class, $id)->getTitle());

        $stack = static::getContainer()->get(\Symfony\Component\HttpFoundation\RequestStack::class);
        $stack->push($this->client->getRequest());
        try {
            $token = (string) static::getContainer()->get('security.csrf.token_manager')->getToken('website_publication_toggle_'.$id);
            $this->client->getRequest()->getSession()->save();
        } finally {
            $stack->pop();
        }
        $version = $this->em->find(WebsitePublication::class, $id)->getVersion();
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', ['_token' => $token, 'version' => $version, 'published' => '1']);
        self::assertResponseRedirects('/portal', 303);
        self::assertTrue($this->em->find(WebsitePublication::class, $id)->isPublished());
        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions([]);
        $this->em->flush();
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', ['_token' => $token, 'version' => $version + 1, 'published' => '0']);
        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->em->find(WebsitePublication::class, $id)->isPublished());
    }

    public function testFunctionModelEditorialRejectsForeignPublicationsAndExpiredAssignments(): void
    {
        $other = (new Club())->setName('Other club');
        $this->em->persist($other);
        $this->em->flush();
        $foreign = new WebsitePublication($other);
        $foreign->setTitle('Foreign'); $foreign->setSummary('Summary'); $foreign->setBody('Body');
        $foreign->setPublishAt(new \DateTimeImmutable('-1 hour'));
        $this->em->persist($foreign);
        $function = new ClubFunction($this->club, 'Redaktion');
        $function->setModulePermissions(['website:read', 'website:edit']);
        $assignment = new ClubFunctionAssignment($function, $this->member);
        $this->em->persist($function); $this->em->persist($assignment); $this->em->flush();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/de/editorial/'.$foreign->getId().'/edit');
        self::assertResponseStatusCodeSame(404);
        $assignment->setValidity(null, new \DateTimeImmutable('yesterday'));
        $this->em->flush();
        $this->client->request('GET', '/admin/de/editorial');
        self::assertResponseStatusCodeSame(403);
    }

    public function testFunctionModelEditorialEditPreservesAnUnreadableImage(): void
    {
        $image = new MediaFile($this->club, str_repeat('a', 40).'.png', 'confidential.png', 'image/png', 1, 'Secret image description');
        $publication = $this->publication('Draft with image', false);
        $publication->setImage($image);
        $function = new ClubFunction($this->club, 'Redaktion ohne Dateizugriff');
        $function->setModulePermissions(['website:edit']);
        $this->em->persist($image); $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $this->member));
        $this->em->flush();
        $id = $publication->getId();
        $imageId = $image->getId();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/de/editorial/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Secret image description');
        self::assertSelectorNotExists('img[src="/admin/media/'.$imageId.'"]');
        self::assertSelectorExists('select[name="website_publication[image]"][disabled]');
        $this->client->submitForm('Beitrag speichern', ['website_publication[title]' => 'Updated without file access']);
        self::assertResponseRedirects('/portal', 303);
        self::assertSame($imageId, $this->em->find(WebsitePublication::class, $id)->getImage()?->getId());
    }

    public function testFunctionModelWebsiteSettingsEnforceReadAndEditSeparately(): void
    {
        $function = new ClubFunction($this->club, 'Website-Einstellungen');
        $function->setModulePermissions(['website:read']);
        $this->em->persist($function);
        $this->em->persist(new ClubFunctionAssignment($function, $this->member));
        $this->em->flush();
        $functionId = $function->getId();
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/portal/de');
        self::assertSelectorExists('a[href="/admin/settings/website"]');
        $this->client->request('GET', '/admin/settings/website');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="website_display_settings[articlesPerPage]"][disabled]');
        self::assertSelectorNotExists('form[name="website_display_settings"] button');
        $this->client->request('POST', '/admin/settings/website', ['website_display_settings' => ['articlesPerPage' => '20']]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0, $this->em->getRepository(WebsiteDisplaySettings::class)->count([]));

        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['website:edit']);
        $this->em->flush();
        $this->client->request('GET', '/admin/de/editorial/display-settings');
        self::assertResponseRedirects('/admin/settings/website');
        $crawler = $this->client->request('GET', '/admin/settings/website');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar a[href="/admin/settings/website"]');
        $values = $crawler->selectButton('Anzeige speichern')->form()->getPhpValues();
        $values['website_display_settings']['articlesPerPage'] = '12';
        $values['website_display_settings']['eventsPerPage'] = '13';
        $this->client->request('POST', '/admin/settings/website', $values);
        self::assertResponseRedirects('/admin/settings/website', 303);
        $this->em->clear();
        $settings = $this->em->find(WebsiteDisplaySettings::class, $this->club->getId());
        self::assertSame(12, $settings->getArticlesPerPage());
        self::assertSame(13, $settings->getEventsPerPage());

        $function = $this->em->find(ClubFunction::class, $functionId);
        $function->setModulePermissions(['website:read']);
        $this->em->flush();
        $values['website_display_settings']['articlesPerPage'] = '25';
        $this->client->request('POST', '/admin/settings/website', $values);
        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertSame(12, $this->em->find(WebsiteDisplaySettings::class, $this->club->getId())->getArticlesPerPage());
    }

    public function testFunctionModelWebsiteSettingsRejectInvalidCsrfAndExpiredAssignments(): void
    {
        $function = new ClubFunction($this->club, 'Website ändern');
        $function->setModulePermissions(['website:edit']);
        $assignment = new ClubFunctionAssignment($function, $this->member);
        $this->em->persist($function);
        $this->em->persist($assignment);
        $this->em->flush();
        $assignmentId = $assignment->getId();
        $this->client->loginUser($this->member);
        $crawler = $this->client->request('GET', '/admin/settings/website');
        $values = $crawler->selectButton('Anzeige speichern')->form()->getPhpValues();
        $values['website_display_settings']['_token'] = 'invalid';
        $values['website_display_settings']['articlesPerPage'] = '30';
        $this->client->request('POST', '/admin/settings/website', $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(WebsiteDisplaySettings::class)->count([]));
        $this->em->find(ClubFunctionAssignment::class, $assignmentId)->setValidity(null, new \DateTimeImmutable('yesterday'));
        $this->em->flush();
        $this->client->request('GET', '/admin/settings/website');
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditorialOverviewSwitchPublishesAndWithdrawsWithAuditAndScheduledVisibility(): void
    {
        $article = $this->publication('Toggle article', false);
        $articleId = $article->getId();
        $future = $this->publication('Scheduled article', false);
        $future->setPublishAt(new \DateTimeImmutable('+1 day'));
        $event = $this->publication('Toggle event', false);
        $event->setKind('event'); $event->setLocation('Club');
        $event->setStartsAt(new \DateTimeImmutable('+2 days')); $event->setEndsAt(new \DateTimeImmutable('+2 days +1 hour'));
        $this->em->flush();
        $this->client->loginUser($this->press);
        foreach ([$articleId, $future->getId(), $event->getId()] as $id) {
            $crawler = $this->client->request('GET', '/admin/de/editorial');
            $selector = 'form[action="/admin/de/editorial/'.$id.'/publication"]';
            self::assertSelectorExists($selector.' .form-switch input.form-check-input:not(:checked)');
            self::assertSelectorExists($selector.' button[data-editorial-toggle-submit]');
            self::assertSelectorExists('script[src="/js/editorial-publication-toggle.js"]');
            $form = $crawler->filter($selector)->form();
            $form['published']->tick();
            $this->client->submit($form);
            self::assertResponseRedirects('/admin/de/editorial', 303);
            $this->em->clear();
            $saved = $this->em->find(WebsitePublication::class, $id);
            self::assertTrue($saved->isPublished());
            self::assertTrue($saved->getHistory()[0]['published']);
        }
        $this->client->request('GET', '/news/'.$articleId);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/news/'.$future->getId());
        self::assertResponseStatusCodeSame(404);
        $crawler = $this->client->request('GET', '/admin/de/editorial');
        $selector = 'form[action="/admin/de/editorial/'.$articleId.'/publication"]';
        self::assertSelectorExists($selector.' .form-switch input.form-check-input:checked');
        $form = $crawler->filter($selector)->form(); $form['published']->untick();
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/de/editorial', 303);
        $this->em->clear();
        $saved = $this->em->find(WebsitePublication::class, $articleId);
        self::assertFalse($saved->isPublished());
        self::assertCount(2, $saved->getHistory());
        self::assertFalse($saved->getHistory()[1]['published']);
        $this->client->request('GET', '/news/'.$articleId);
        self::assertResponseStatusCodeSame(404);
    }

    public function testEditorialOverviewSwitchRejectsInvalidOrStaleChangesAndForeignAccess(): void
    {
        $article = $this->publication('Protected article', false);
        $id = $article->getId();
        $other = (new Club())->setName('Other club'); $this->em->persist($other);
        $foreign = new WebsitePublication($other); $foreign->setTitle('Foreign'); $foreign->setSummary('Summary'); $foreign->setBody('Body');
        $this->em->persist($foreign); $this->em->flush();
        $foreignId = $foreign->getId();
        $foreignPress = $this->account('foreign-switch-press', [User::ROLE_PRESS_OFFICER], $other);
        $this->client->loginUser($this->press);
        $crawler = $this->client->request('GET', '/admin/de/editorial');
        $form = $crawler->filter('form[action="/admin/de/editorial/'.$id.'/publication"]')->form();
        $values = $form->getPhpValues(); $values['published'] = '1';
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', array_replace($values, ['_token' => 'invalid']));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/de/editorial/'.$foreignId.'/publication', $values);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', array_replace($values, ['published' => 'other']));
        self::assertResponseStatusCodeSame(400);
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', array_replace($values, ['version' => 'invalid']));
        self::assertResponseStatusCodeSame(400);
        $this->em->getConnection()->executeStatement('UPDATE website_publication SET title = ?, version = version + 1 WHERE id = ?', ['Newer title', $id]);
        $this->em->clear();
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', $values);
        self::assertResponseRedirects('/admin/de/editorial', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'zwischenzeitlich geändert');
        $saved = $this->em->find(WebsitePublication::class, $id);
        self::assertFalse($saved->isPublished());
        self::assertSame('Newer title', $saved->getTitle());
        self::assertCount(0, $saved->getHistory());
        $this->client->loginUser($this->member);
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', $values);
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($foreignPress);
        $this->client->request('POST', '/admin/de/editorial/'.$id.'/publication', $values);
        self::assertResponseStatusCodeSame(403);
    }

    public function testEditorialOverviewSwitchValidatesBeforePublishingButAllowsWithdrawal(): void
    {
        $course = $this->source('course', $this->club);
        $event = $this->publication('Course event', true);
        $event->setKind('event'); $event->setLocation('Club');
        $event->setStartsAt(new \DateTimeImmutable('+2 days')); $event->setEndsAt(new \DateTimeImmutable('+2 days +1 hour'));
        $event->setCourse($course); $this->em->flush();
        $id = $event->getId();
        $course->setStatus(CourseStatus::CANCELLED); $this->em->flush();
        $this->client->loginUser($this->press);
        $crawler = $this->client->request('GET', '/admin/de/editorial');
        $selector = 'form[action="/admin/de/editorial/'.$id.'/publication"]';
        $form = $crawler->filter($selector)->form(); $form['published']->untick();
        $this->client->submit($form); self::assertResponseRedirects('/admin/de/editorial', 303);
        $crawler = $this->client->followRedirect();
        $form = $crawler->filter($selector)->form(); $form['published']->tick();
        $this->client->submit($form); self::assertResponseRedirects('/admin/de/editorial', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Verknüpfte Veranstaltung muss freigegeben sein');
        $this->em->clear();
        $saved = $this->em->find(WebsitePublication::class, $id);
        self::assertFalse($saved->isPublished());
        self::assertCount(1, $saved->getHistory());
    }

    public function testEditorialIsBackendOnlyAndDoesNotGrantGeneralBackendAccess(): void
    {
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/admin/de/editorial');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorNotExists('body[data-site-theme]');
        self::assertSelectorNotExists('a[href="/editorial"]');
        self::assertSelectorTextNotContains('aside.sidebar', 'Mitglieder & Verein');
        self::assertSelectorTextNotContains('aside.sidebar', 'Dashboard');

        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href="/editorial"]');

        $this->client->request('GET', '/editorial');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/de');
        self::assertResponseStatusCodeSame(403);
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
            self::assertSelectorExists('link[href="/themes/airspace/css/style.css"]');
            self::assertSelectorExists('link[href="/themes/airspace/plugins/bootstrap/bootstrap.min.css"]');
            self::assertSelectorExists('link[href="/css/style.css"]');
            self::assertSelectorNotExists('link[href="/plugins/bootstrap/bootstrap.min.css"]');
        }
        $this->client->request('GET', '/news/'.$visible->getId()); self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('main script'); self::assertSelectorNotExists('main img'); self::assertSelectorTextContains('main', '<script>alert(1)</script>');
        $this->client->request('GET', '/news/'.$foreign->getId()); self::assertResponseStatusCodeSame(404);
    }

    #[DataProvider('editorRoles')]
    public function testPublicationRightsAreLimitedToBoardAndPress(string $role, bool $allowed): void
    {
        $user = $this->account('role-user', [$role]); $this->client->loginUser($user);
        $this->client->request('GET', '/admin/de/editorial');
        self::assertResponseStatusCodeSame($allowed ? 200 : 403);
        $this->client->request('GET', '/admin/de/editorial/new');
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
        foreach (['/admin/de/editorial', '/admin/de/editorial/new', '/admin/de/editorial/'.$publication->getId().'/edit'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(1, $this->em->getRepository(WebsitePublication::class)->count([]));
    }

    public function testPublicationFormRejectsCsrfAndInvalidEventDates(): void
    {
        $this->client->loginUser($this->press);
        $this->client->request('POST', '/admin/de/editorial/new', ['website_publication' => ['_token' => 'forged']]);
        self::assertResponseStatusCodeSame(422); self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
        $this->client->request('GET', '/admin/de/editorial/new');
        $data = $this->formData(); $data['website_publication[kind]'] = 'event';
        $this->client->submitForm('Beitrag speichern', $data + ['website_publication[startsAt]' => '2027-01-01T18:00', 'website_publication[endsAt]' => '2027-01-01T17:00', 'website_publication[location]' => 'Clubhouse']);
        self::assertResponseStatusCodeSame(422); self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
    }

    public function testIndependentPublicEventIsListedWithDatesAndLocation(): void
    {
        $this->client->loginUser($this->press); $this->client->request('GET', '/admin/de/editorial/new');
        $data = $this->formData(); $data['website_publication[kind]'] = 'event';
        $data += ['website_publication[startsAt]' => '2027-06-01T18:00', 'website_publication[endsAt]' => '2027-06-01T22:00', 'website_publication[location]' => 'Club garden', 'website_publication[published]' => true];
        $this->client->submitForm('Beitrag speichern', $data); self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/events'); self::assertResponseIsSuccessful(); self::assertSelectorTextContains('main', 'Club garden');
        self::assertSelectorTextContains('main', '01.06.2027 18:00');
    }

    public function testDisplaySettingsAndIndependentPagination(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $article = $this->publication('Article '.$i, true);
            $article->setPublishAt(new \DateTimeImmutable('-'.$i.' hours'));
            $event = new WebsitePublication($this->club);
            $event->setKind('event'); $event->setTitle('Event '.$i); $event->setSummary('Summary'); $event->setBody('Body');
            $event->setLocation('Club'); $event->setPublished(true);
            $event->setStartsAt(new \DateTimeImmutable('+'.$i.' days'));
            $event->setEndsAt(new \DateTimeImmutable('+'.$i.' days +1 hour'));
            $this->em->persist($event);
        }
        $this->em->flush();
        $this->client->loginUser($this->press);
        $this->client->request('POST', '/admin/settings/website', [
            'website_display_settings' => ['articlesPerPage' => '2', 'eventsPerPage' => '3', '_token' => 'invalid'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(WebsiteDisplaySettings::class)->count([]));
        $this->client->request('GET', '/admin/settings/website');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="website_display_settings[articlesPerPage]"][value="6"]');
        self::assertSelectorExists('input[name="website_display_settings[eventsPerPage]"][value="6"]');
        $this->client->submitForm('Anzeige speichern', [
            'website_display_settings[articlesPerPage]' => '2',
            'website_display_settings[eventsPerPage]' => '3',
        ]);
        self::assertResponseRedirects('/admin/settings/website', 303);
        $this->em->clear();
        $settings = $this->em->find(WebsiteDisplaySettings::class, $this->club->getId());
        self::assertSame(2, $settings->getArticlesPerPage());
        self::assertSame(3, $settings->getEventsPerPage());

        $this->client->request('GET', '/?artikel_seite=2&veranstaltungen_seite=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Article 3');
        self::assertSelectorTextNotContains('body', 'Article 1');
        self::assertSelectorTextContains('body', 'Event 3');
        self::assertSelectorTextNotContains('body', 'Event 4');
        self::assertCount(5, $this->client->getCrawler()->filter('article.col-md-6'));
        self::assertSelectorExists('nav[aria-label="Veranstaltungen: Seiten"] a[rel="next"][href*="artikel_seite=2"]');
        $this->client->click($this->client->getCrawler()->filter('nav[aria-label="Veranstaltungen: Seiten"] a[rel="next"]')->link());
        self::assertSelectorTextContains('body', 'Article 3');
        self::assertSelectorTextContains('body', 'Event 4');
        self::assertSelectorTextNotContains('body', 'Event 1');
        self::assertSelectorExists('nav[aria-label="Veranstaltungen: Seiten"] a[rel="prev"]');
        $this->client->request('GET', '/articles?seite=3');
        self::assertSelectorTextContains('main', 'Article 5');
        self::assertSelectorTextNotContains('main', 'Article 1');
        $this->client->request('GET', '/events?seite=2');
        self::assertSelectorTextContains('main', 'Event 5');
        self::assertSelectorTextNotContains('main', 'Event 1');
        $this->client->request('GET', '/events?seite=999');
        self::assertSelectorTextContains('main', 'Event 5');
        $this->client->request('GET', '/events?seite=0');
        self::assertResponseStatusCodeSame(400);
        $this->client->request('GET', '/events?seite[]=2');
        self::assertResponseStatusCodeSame(400);

        $this->client->request('GET', '/admin/settings/website');
        $this->client->submitForm('Anzeige speichern', [
            'website_display_settings[articlesPerPage]' => '0',
            'website_display_settings[eventsPerPage]' => '101',
        ]);
        self::assertResponseStatusCodeSame(422);
        $this->em->clear();
        $settings = $this->em->find(WebsiteDisplaySettings::class, $this->club->getId());
        self::assertSame(2, $settings->getArticlesPerPage());
        self::assertSame(3, $settings->getEventsPerPage());
        $this->client->loginUser($this->member);
        $this->client->request('GET', '/admin/de/editorial/display-settings');
        self::assertResponseStatusCodeSame(403);
        $other = (new Club())->setName('Other club');
        $this->em->persist($other); $this->em->flush();
        $foreignPress = $this->account('foreign-press', [User::ROLE_PRESS_OFFICER], $other);
        $this->client->loginUser($foreignPress);
        $this->client->request('GET', '/admin/de/editorial/display-settings');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDefaultPaginationMakesSeventhArticleAndEventReachable(): void
    {
        for ($i = 1; $i <= 7; ++$i) {
            $article = $this->publication('News item '.$i, true);
            $article->setPublishAt(new \DateTimeImmutable('-'.$i.' hours'));
            $event = new WebsitePublication($this->club);
            $event->setKind('event'); $event->setTitle('Calendar item '.$i); $event->setSummary('Summary'); $event->setBody('Body');
            $event->setLocation('Club'); $event->setPublished(true);
            $event->setStartsAt(new \DateTimeImmutable('+'.$i.' days'));
            $event->setEndsAt(new \DateTimeImmutable('+'.$i.' days +1 hour'));
            $this->em->persist($event);
        }
        $this->em->flush();
        $this->client->request('GET', '/');
        self::assertCount(12, $this->client->getCrawler()->filter('article.col-md-6'));
        self::assertSelectorTextNotContains('body', 'News item 7');
        self::assertSelectorTextNotContains('body', 'Calendar item 7');
        $this->client->request('GET', '/?artikel_seite=2&veranstaltungen_seite=2');
        self::assertSelectorTextContains('body', 'News item 7');
        self::assertSelectorTextContains('body', 'Calendar item 7');
        self::assertCount(2, $this->client->getCrawler()->filter('article.col-md-6'));
    }

    #[DataProvider('eventSources')]
    public function testSourcesAreOptInAndCancelledSourcesHidePublicAnnouncement(string $kind): void
    {
        $source = $this->source($kind, $this->club);
        $this->client->request('GET', '/events'); self::assertResponseIsSuccessful(); self::assertSelectorTextNotContains('main', 'Internal source title');
        $this->client->loginUser($this->press); $this->client->request('GET', '/admin/de/editorial/new');
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
        $this->client->loginUser($this->press); $this->client->request('GET', '/admin/de/editorial/new');
        $data = $this->formData(); $data['website_publication[kind]'] = 'event';
        $data += ['website_publication[startsAt]' => '2027-06-01T18:00', 'website_publication[endsAt]' => '2027-06-01T22:00', 'website_publication[location]' => 'Public place'];
        $form = $this->client->getCrawler()->selectButton('Beitrag speichern')->form();
        $values = $form->getPhpValues(); $values['website_publication'] = array_replace($values['website_publication'], ['course' => (string) $source->getId(), 'kind' => 'event', 'title' => 'Forged', 'summary' => 'Forged summary', 'body' => 'Forged body', 'startsAt' => '2027-06-01T18:00', 'endsAt' => '2027-06-01T22:00', 'location' => 'Public place']);
        $this->client->request('POST', '/admin/de/editorial/new', $values); self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(WebsitePublication::class)->count([]));
    }

    public function testStaleEditorialFormCannotOverwriteAnotherEditorsChanges(): void
    {
        $p = $this->publication('Original title', false);
        $this->client->loginUser($this->press);
        $this->client->request('GET', '/admin/de/editorial/'.$p->getId().'/edit');
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
        $this->client->loginUser($this->press); $this->client->request('GET', '/admin/de/editorial/'.$p->getId().'/edit'); self::assertResponseStatusCodeSame(404);
    }

    public function testConfiguredWebsiteCourseCatalogDoesNotMixClubs(): void
    {
        $own = $this->source('course', $this->club); $own->setTitle('Website course');
        $other = (new Club())->setName('Other club'); $this->em->persist($other); $this->em->flush();
        $foreign = $this->source('course', $other); $foreign->setTitle('Foreign course'); $this->em->flush();
        $this->client->request('GET', '/courses/'); self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Website course'); self::assertSelectorTextNotContains('body', 'Foreign course');
    }

    public function testUnconfiguredWebsiteCourseCatalogRequiresAnUnambiguousActiveClub(): void
    {
        $course = $this->source('course', $this->club);
        $course->setTitle('Only active club course');
        $other = (new Club())->setName('Inactive club')->setActive(false);
        $this->em->persist($other); $this->em->flush();
        $foreign = $this->source('course', $other);
        $foreign->setTitle('Inactive club course'); $this->em->flush();
        $this->client->request('GET', '/courses/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Only active club course');
        self::assertSelectorTextNotContains('body', 'Inactive club course');

        $other->setActive(true); $this->em->flush();
        $this->client->request('GET', '/courses/');
        self::assertResponseStatusCodeSame(500);
        self::assertSelectorTextNotContains('body', 'Inactive club course');
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
