<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Entity\Club;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\Person;
use App\Entity\Schedule;
use App\Entity\Sport;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\TeamTraining;
use App\Entity\Tournament;
use App\Entity\TournamentDocument;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\TournamentPayment;
use App\Entity\TournamentPlayer;
use App\Entity\User;
use App\Enum\CourseStatus;
use App\Enum\MembershipStatus;
use App\Enum\TeamMemberRole;
use App\Enum\TournamentStatus;
use App\Service\PrivateMemberDocumentStorage;
use App\Service\TournamentService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase5CompetitionHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private Sport $sport;
    private Team $team;
    private User $admin;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:'); putenv('MAILER_DSN=null://null');
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Own Competition Club');
        $this->sport = (new Sport())->setClub($this->club)->setName('Unterwasserrugby')->setShortName('uwr');
        $this->team = (new Team())->setName('Own Team')->setSport($this->sport);
        foreach ([$this->club, $this->sport, $this->team] as $entity) { $this->em->persist($entity); }
        $this->admin = $this->account('admin', User::ROLE_ADMIN);
        $this->em->flush();
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/admin/teams');
        self::assertResponseIsSuccessful();
    }

    public function testTeamRosterTrainingAndTournamentCreationUseScopedBackendForms(): void
    {
        $crawler = $this->client->request('GET', '/admin/teams/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'form[name]' => 'New Competition Team', 'form[sport]' => (string) $this->sport->getId(),
            'form[type]' => 'team', 'form[active]' => '1',
        ]);
        self::assertResponseRedirects();
        $newTeam = $this->em->getRepository(Team::class)->findOneBy(['name' => 'New Competition Team']);
        self::assertNotNull($newTeam);
        $crawler = $this->client->request('GET', '/admin/teams/' . $newTeam->getId() . '/training/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'form[title]' => 'League practice', 'form[startsAt]' => '2027-02-01T18:00',
            'form[endsAt]' => '2027-02-01T19:30', 'form[location]' => 'Own pool',
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorTextContains('body', 'League practice');
        $crawler = $this->client->request('GET', '/admin/tournaments/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'tournament[name]' => 'Phase 5 Cup', 'tournament[sportCode]' => 'uwr',
            'tournament[organizer]' => 'Own Club', 'tournament[location]' => 'Competition pool',
            'tournament[startsAt]' => '2027-02-10T09:00', 'tournament[endsAt]' => '2027-02-10T18:00',
            'tournament[feeCents]' => '25,50', 'tournament[maxTeams]' => '8',
            'tournament[minPlayers]' => '0', 'tournament[maxPlayers]' => '12',
            'tournament[winPoints]' => '3', 'tournament[drawPoints]' => '1', 'tournament[lossPoints]' => '0',
        ]);
        self::assertResponseRedirects();
        $tournament = $this->em->getRepository(Tournament::class)->findOneBy(['name' => 'Phase 5 Cup']);
        self::assertSame(2550, $tournament->getFeeCents());
        $this->client->followRedirect();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorTextContains('body', 'Phase 5 Cup');
        self::assertSame([], (new SchemaValidator($this->em))->validateMapping());
    }

    public function testRoundRobinResultsStandingsCorrectionFeesAndImmutableArchive(): void
    {
        $tournament = $this->tournament();
        $service = $this->service();
        $entries = $this->externalEntries($tournament, 3);
        $service->generate($tournament, 20);
        $matches = $service->matches($tournament);
        self::assertCount(3, $matches);
        $pairs = [];
        foreach ($matches as $match) {
            $pair = [$match->getHome()->getId(), $match->getAway()->getId()]; sort($pair);
            $pairs[] = implode('-', $pair);
        }
        self::assertCount(3, array_unique($pairs));
        $service->transition($tournament, TournamentStatus::OPEN);
        $service->transition($tournament, TournamentStatus::RUNNING);
        foreach ($matches as $match) {
            $service->recordResult($match, 2, 1, 'test', 'Initial result', $match->getVersion());
        }
        $first = $matches[0];
        $oldVersion = $first->getVersion();
        $service->recordResult($first, 4, 4, 'test', 'Referee correction', $first->getVersion());
        self::assertCount(2, $first->getResultHistory());
        self::assertGreaterThan($oldVersion, $first->getVersion());
        $standings = $service->standings($tournament)['A'];
        self::assertCount(3, $standings);
        self::assertSame(6, array_sum(array_column($standings, 'played')));
        self::assertSame(8, array_sum(array_column($standings, 'points')));
        self::assertSame(array_sum(array_column($standings, 'for')), array_sum(array_column($standings, 'against')));
        $service->payment($entries[0], 2550, 'pay-1', 'test');
        $service->payment($entries[0], -550, 'refund-1', 'test');
        self::assertSame(2000, $service->paidCents($entries[0]));
        $service->transition($tournament, TournamentStatus::COMPLETED);
        $archive = $tournament->getArchive();
        self::assertNotNull($archive);
        self::assertSame($standings, $archive['standings']['A']);
        $this->client->request('GET', '/admin/tournaments/' . $tournament->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-tournament-standings]');
        self::assertSelectorNotExists('a[href$="/generate"]');
        $this->client->request('GET', '/admin/tournaments/' . $tournament->getId() . '/edit');
        self::assertResponseStatusCodeSame(403);
        try { $service->recordResult($first, 9, 0, 'test', 'Forbidden edit', $first->getVersion()); self::fail('Archive must lock results.'); }
        catch (\DomainException) { self::assertSame($archive, $tournament->getArchive()); }
    }

    public function testEvenAndGroupedRoundRobinContainsEveryPairWithoutRoundConflicts(): void
    {
        $tournament = $this->tournament();
        $tournament->setEndsAt($tournament->getStartsAt()->modify('+2 days'));
        $entries = $this->externalEntries($tournament, 6);
        foreach ($entries as $i => $entry) { $entry->setGroupName($i < 4 ? 'A' : 'B'); }
        $this->em->flush();
        $this->service()->generate($tournament, 10);
        $matches = $this->service()->matches($tournament);
        self::assertCount(7, $matches);
        $rounds = []; $pairs = [];
        foreach ($matches as $match) {
            self::assertSame($match->getHome()->getGroupName(), $match->getAway()->getGroupName());
            foreach ([$match->getHome(), $match->getAway()] as $entry) {
                $key = $match->getRoundNumber() . '-' . $entry->getId();
                self::assertArrayNotHasKey($key, $rounds);
                $rounds[$key] = true;
            }
            $pair = [$match->getHome()->getId(), $match->getAway()->getId()]; sort($pair); $pairs[] = implode('-', $pair);
        }
        self::assertCount(7, array_unique($pairs));
        try { $this->service()->generate($tournament, 10); self::fail('Must not overwrite existing games.'); }
        catch (\DomainException) { self::assertCount(7, $this->service()->matches($tournament)); }
    }

    public function testEligibilityRosterLimitsAndExternalPlayersNeverCreateMemberships(): void
    {
        $tournament = $this->tournament();
        $tournament->setMaxPlayers(1);
        $assignment = $this->player('eligible-player');
        $entry = new TournamentEntry($tournament); $entry->setTeam($this->team); $entry->setName('Own entry');
        $this->service()->register($entry);
        $player = new TournamentPlayer($entry); $player->setTeamMember($assignment); $player->setEligibilityEvidence('Licence checked');
        $this->service()->addPlayer($player);
        self::assertSame('eligible-player Diver', $player->getName());
        self::assertSame('Defence', $player->getPosition());
        self::assertNull($this->service()->eligibility($assignment, $tournament));
        $assignment->setEligibilityUntil($tournament->getEndsAt()->modify('-1 day'));
        self::assertNotNull($this->service()->eligibility($assignment, $tournament));
        $assignment->setEligibilityUntil($tournament->getEndsAt());
        $before = $this->em->getRepository(Member::class)->count([]);
        $external = $this->externalEntries($tournament, 1)[0];
        $externalPlayer = new TournamentPlayer($external);
        $externalPlayer->setName('External Guest Player'); $externalPlayer->setEligibilityEvidence('External licence verified by organizer');
        $this->service()->addPlayer($externalPlayer);
        self::assertSame($before, $this->em->getRepository(Member::class)->count([]));
        self::assertNull($externalPlayer->getTeamMember());
        $duplicate = new TournamentPlayer($entry); $duplicate->setTeamMember($assignment); $duplicate->setEligibilityEvidence('Checked');
        try { $this->service()->addPlayer($duplicate); self::fail('Roster limit must be enforced.'); }
        catch (\DomainException) { self::assertSame(2, $this->em->getRepository(TournamentPlayer::class)->count([])); }
        self::assertCount(0, $this->em->getRepository(Booking::class)->findAll());
    }

    public function testClubTrainerAndMemberBoundariesAndCsrfProtectAllWrites(): void
    {
        $tournament = $this->tournament();
        $foreignClub = (new Club())->setName('Foreign Competition Club');
        $this->em->persist($foreignClub);
        $foreign = $this->account('foreign-admin', User::ROLE_ADMIN, $foreignClub);
        $trainer = $this->account('trainer', User::ROLE_MEMBER);
        $assignment = (new TeamMember())->setTeam($this->team)->setMember($trainer->getMember())->setRole(TeamMemberRole::TRAINER);
        $this->em->persist($assignment); $this->em->flush();
        $this->client->loginUser($foreign);
        foreach (['/admin/teams/' . $this->team->getId(), '/admin/tournaments/' . $tournament->getId(), '/admin/tournaments/' . $tournament->getId() . '/edit'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
        $this->client->loginUser($trainer);
        $this->client->request('GET', '/admin/teams/' . $this->team->getId());
        self::assertResponseIsSuccessful(); self::assertSelectorExists('[data-competition-navigation]');
        self::assertSelectorExists('body[data-site-theme="airspace"]');
        $managedAssignment = $this->em->find(TeamMember::class, $assignment->getId());
        $managedAssignment->setLeftAt(new \DateTime('yesterday')); $this->em->flush();
        $this->client->request('GET', '/admin/teams/' . $this->team->getId()); self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/tournaments/new'); self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/teams/new'); self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->admin);
        $this->client->request('POST', '/admin/tournaments/' . $tournament->getId() . '/status', ['status' => 'open', '_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(TournamentStatus::DRAFT, $this->em->find(Tournament::class, $tournament->getId())->getStatus());
        $crawler = $this->client->request('GET', '/admin/tournaments/' . $tournament->getId());
        $token = $crawler->filter('form[action$="/status"] input[name="_token"]')->first()->attr('value');
        $this->client->request('POST', '/admin/tournaments/' . $tournament->getId() . '/status', ['status' => 'open', '_token' => $token]);
        self::assertResponseRedirects();
        $this->client->loginUser($foreign);
        $this->client->request('GET', '/admin/tournaments/' . $tournament->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href$="/edit"]');
        $this->client->request('GET', '/admin/tournaments/' . $tournament->getId() . '/edit');
        self::assertResponseStatusCodeSame(403);
        $ordinary = $this->account('ordinary', User::ROLE_MEMBER);
        $this->client->loginUser($ordinary);
        foreach (['/admin/teams', '/admin/tournaments'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('GET', '/admin/calendar'); self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-event-key="tournament-'.$tournament->getId().'"]');
        $contextless = $this->account('contextless-admin', User::ROLE_ADMIN);
        $contextless->setMember(null); $this->em->flush();
        $this->client->loginUser($contextless);
        foreach (['/admin/teams', '/admin/tournaments', '/admin/calendar'] as $url) {
            $this->client->request('GET', $url); self::assertResponseStatusCodeSame(403);
        }
    }

    public function testSharedCalendarProjectsOriginalDatesWithoutDuplicateRecords(): void
    {
        $tournament = $this->tournament(); $tournament->setStatus(TournamentStatus::OPEN);
        $session = new TeamTraining($this->team);
        $session->setTitle('Own team practice'); $session->setStartsAt($tournament->getStartsAt()->modify('-1 day'));
        $session->setEndsAt($session->getStartsAt()->modify('+2 hours')); $session->setLocation('Training pool');
        $course = (new Courses())->setClub($this->club)->setTitle('Own dive course')->setCategory('training')->setStatus(CourseStatus::OPEN);
        $schedule = (new Schedule())->setCourses($course)->setTitle('Own theory session')->setStartDate($tournament->getStartsAt())->setStartTime(new \DateTime('10:00'))->setDuration(60);
        foreach ([$session, $course, $schedule] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $month = $tournament->getStartsAt()->format('Y-m');
        $crawler = $this->client->request('GET', '/admin/calendar?month=' . $month);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-club-calendar]', 'Own team practice');
        self::assertSelectorTextContains('[data-club-calendar]', 'Own theory session');
        self::assertSelectorTextContains('[data-club-calendar]', 'Competition Cup');
        self::assertCount(3, $crawler->filter('[data-event-key]'));
        self::assertSame('/admin/calendar', $crawler->filter('form[method="get"]')->attr('action'));
        $crawler = $this->client->request('GET', '/admin?routeName=club_calendar&month=' . $month);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Anzeigen')->form(), ['month' => $month]);
        self::assertRequestAttributeValueSame('_route', 'club_calendar');
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->em->getRepository(TeamTraining::class)->count([]));
        self::assertSame(1, $this->em->getRepository(Schedule::class)->count([]));
        self::assertSame(1, $this->em->getRepository(Tournament::class)->count([]));
        $this->em->find(TeamTraining::class, $session->getId())->setCancelled(true); $this->em->flush();
        $this->client->request('GET', '/admin/calendar?month=' . $month);
        self::assertSelectorTextNotContains('[data-club-calendar]', 'Own team practice');
    }

    public function testProtectedDocumentsAndInvalidUploads(): void
    {
        $tournament = $this->tournament();
        $crawler = $this->client->request('GET', '/admin/tournaments/' . $tournament->getId());
        $token = $crawler->filter('form[enctype] input[name="_token"]')->attr('value');
        $source = tempnam(sys_get_temp_dir(), 'competition_pdf_');
        file_put_contents($source, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF");
        $storagePath = null;
        try {
            foreach (['not a PDF', str_repeat('x', 5 * 1024 * 1024 + 1)] as $invalidContents) {
                $invalidSource = tempnam(sys_get_temp_dir(), 'competition_invalid_');
                try {
                    file_put_contents($invalidSource, $invalidContents);
                    $this->client->request('POST', '/admin/tournaments/' . $tournament->getId() . '/document',
                        ['_token' => $token], ['document' => new UploadedFile($invalidSource, 'forged.pdf', 'application/pdf', null, true)]);
                    self::assertResponseRedirects();
                    self::assertSame(0, $this->em->getRepository(TournamentDocument::class)->count([]));
                } finally { if (is_file($invalidSource)) { unlink($invalidSource); } }
            }
            $this->client->request('POST', '/admin/tournaments/' . $tournament->getId() . '/document',
                ['_token' => $token], ['document' => new UploadedFile($source, 'rules.pdf', 'application/pdf', null, true)]);
            self::assertResponseRedirects();
            $document = $this->em->getRepository(TournamentDocument::class)->findOneBy(['tournament' => $tournament]);
            self::assertNotNull($document);
            $storagePath = static::getContainer()->get(PrivateMemberDocumentStorage::class)->path($document->getStorageName());
            $this->client->request('GET', '/admin/tournaments/document/' . $document->getId());
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
            self::assertStringContainsString('attachment;', $this->client->getResponse()->headers->get('Content-Disposition'));
            $member = $this->account('document-reader', User::ROLE_MEMBER);
            $this->client->loginUser($member);
            $this->client->request('GET', '/admin/tournaments/document/' . $document->getId());
            self::assertResponseStatusCodeSame(403);
        } finally {
            if (is_file($source)) { unlink($source); }
            if ($storagePath !== null && is_file($storagePath)) { unlink($storagePath); }
        }
    }

    public function testEmptyAndInvalidFormsReturnErrorsInsteadOfServerFailures(): void
    {
        $crawler = $this->client->request('GET', '/admin/tournaments/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'tournament[name]' => '', 'tournament[sportCode]' => '', 'tournament[organizer]' => '', 'tournament[location]' => '',
            'tournament[startsAt]' => '', 'tournament[endsAt]' => '', 'tournament[feeCents]' => '',
            'tournament[maxTeams]' => '', 'tournament[minPlayers]' => '', 'tournament[maxPlayers]' => '',
            'tournament[winPoints]' => '', 'tournament[drawPoints]' => '', 'tournament[lossPoints]' => '',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(Tournament::class)->count([]));
        $tournament = $this->tournament();
        $crawler = $this->client->request('GET', '/admin/tournaments/' . $tournament->getId() . '/entry');
        $this->client->submit($crawler->selectButton('Speichern')->form(), ['form[name]' => '', 'form[groupName]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(TournamentEntry::class)->count([]));
        $tournament = $this->em->find(Tournament::class, $tournament->getId());
        $entry = $this->externalEntries($tournament, 1)[0];
        $crawler = $this->client->request('GET', '/admin/tournaments/entry/' . $entry->getId());
        $this->client->submit($crawler->selectButton('Spieler registrieren')->form(), ['form[name]' => '', 'form[eligibilityEvidence]' => '']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em->getRepository(TournamentPlayer::class)->count([]));
        $crawler = $this->client->request('GET', '/admin/teams/' . $this->team->getId() . '/training/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'form[title]' => '', 'form[location]' => '', 'form[startsAt]' => '', 'form[endsAt]' => '',
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testPaymentFormUsesExactCentsAndRejectsDuplicatesOverpaymentsAndExcessRefunds(): void
    {
        $tournament = $this->tournament(); $entry = $this->externalEntries($tournament, 1)[0];
        foreach ([['12,34', 'payment-1'], ['13,16', 'payment-2'], ['-0,50', 'refund-1']] as [$amount, $reference]) {
            $crawler = $this->client->request('GET', '/admin/tournaments/entry/' . $entry->getId() . '/payment');
            $this->client->submit($crawler->selectButton('Speichern')->form(), ['form[amount]' => $amount, 'form[reference]' => $reference]);
            self::assertResponseRedirects();
        }
        $entry = $this->em->find(TournamentEntry::class, $entry->getId());
        self::assertSame(2500, $this->service()->paidCents($entry));
        foreach ([[1, 'payment-1'], [51, 'overpayment'], [-2501, 'excess-refund'], [0, 'empty-payment']] as [$amount, $reference]) {
            try { $this->service()->payment($entry, $amount, $reference, 'test'); self::fail('Invalid ledger booking must be rejected.'); }
            catch (\DomainException) { self::assertSame(2500, $this->service()->paidCents($entry)); }
        }
        self::assertSame(3, $this->em->getRepository(TournamentPayment::class)->count([]));
    }

    public function testManualScheduleRejectsSameTeamsGroupsCollisionsAndSupportsExplicitReset(): void
    {
        $tournament = $this->tournament(); $entries = $this->externalEntries($tournament, 3);
        $match = new TournamentMatch($tournament);
        $match->setHome($entries[0]); $match->setAway($entries[0]);
        try { $this->service()->saveMatch($match); self::fail('Same team cannot be its own opponent.'); } catch (\DomainException) {}
        $match->setAway($entries[1]); $match->setStartsAt($tournament->getStartsAt());
        $this->service()->saveMatch($match);
        $conflict = new TournamentMatch($tournament); $conflict->setHome($entries[0]); $conflict->setAway($entries[2]); $conflict->setRoundNumber(2);
        $conflict->setStartsAt($match->getStartsAt());
        try { $this->service()->saveMatch($conflict); self::fail('Simultaneous games for one team must be rejected.'); } catch (\DomainException) {}
        $conflict->setStartsAt($match->getStartsAt()->modify('+10 minutes'));
        try { $this->service()->saveMatch($conflict); self::fail('Overlapping match windows must be rejected.'); } catch (\DomainException) {}
        $conflict->setStartsAt($match->getStartsAt()->modify('+30 minutes'));
        $entries[2]->setGroupName('B');
        try { $this->service()->saveMatch($conflict); self::fail('Different groups must be rejected.'); } catch (\DomainException) {}
        $entries[2]->setGroupName('A'); $this->em->flush();
        $this->service()->saveMatch($conflict);
        self::assertCount(2, $this->service()->matches($tournament));
        $crawler = $this->client->request('GET', '/admin/tournaments/' . $tournament->getId());
        $token = $crawler->filter('form[action$="/reset-schedule"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/tournaments/' . $tournament->getId() . '/reset-schedule', ['_token' => $token, 'confirm' => '1']);
        self::assertResponseRedirects();
        self::assertSame(0, $this->em->getRepository(TournamentMatch::class)->count([]));
    }

    public function testClosedRostersConfigurationLocksAndOptimisticResultConflict(): void
    {
        $tournament = $this->tournament(); $entries = $this->externalEntries($tournament, 2);
        $this->client->request('GET', '/admin/tournaments/' . $tournament->getId() . '/edit');
        self::assertResponseStatusCodeSame(403);
        $tournament = $this->em->find(Tournament::class, $tournament->getId());
        $this->service()->generate($tournament, 20);
        $this->service()->transition($tournament, TournamentStatus::OPEN);
        $this->service()->transition($tournament, TournamentStatus::RUNNING);
        $match = $this->service()->matches($tournament)[0]; $version = $match->getVersion();
        $this->service()->recordResult($match, 0, 0, 'referee', 'Scoreless draw', $version);
        try {
            $this->service()->recordResult($match, 1, 0, 'referee', 'Stale correction', $version);
            self::fail('Stale score versions must not overwrite results.');
        } catch (\Doctrine\ORM\OptimisticLockException) { self::assertSame(0, $match->getHomeScore()); }
        $this->client->request('POST', '/admin/tournaments/entry/' . $entries[0]->getId(), ['form' => ['name' => 'Late player']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/tournaments/entry/' . $entries[0]->getId());
        self::assertSelectorNotExists('button:contains("Spieler registrieren")');
        self::assertSame(0, $this->em->getRepository(TournamentPlayer::class)->count([]));
        $tournament = $this->em->find(Tournament::class, $tournament->getId());
        foreach ($this->service()->standings($tournament)['A'] as $row) {
            self::assertSame(1, $row['rank']); self::assertSame(1, $row['points']); self::assertSame(1, $row['drawn']);
        }
    }

    public function testRosterFormsPreserveSportAssignmentsAndRejectDuplicateMembers(): void
    {
        $account = $this->account('new-squad-player', User::ROLE_MEMBER);
        $sport = (new MemberSport())->setMember($account->getMember())->setSport($this->em->find(Sport::class, $this->sport->getId()));
        $account->getMember()->addSport($sport); $this->em->persist($sport); $this->em->flush();
        $crawler = $this->client->request('GET', '/admin/teams/' . $this->team->getId() . '/roster/new');
        $values = [
            'team_member[member]' => (string) $account->getMember()->getId(), 'team_member[role]' => 'player',
            'team_member[position]' => 'Goalkeeper', 'team_member[jerseyNumber]' => '0',
            'team_member[competitionEligible]' => '1', 'team_member[eligibilityNote]' => 'Valid player licence',
            'team_member[eligibilityUntil]' => '2028-12-31',
        ];
        $this->client->submit($crawler->selectButton('Speichern')->form(), $values);
        self::assertResponseRedirects();
        $assignment = $this->em->getRepository(TeamMember::class)->findOneBy(['member' => $account->getMember()->getId()]);
        self::assertSame(0, $assignment->getJerseyNumber()); self::assertTrue($assignment->isCompetitionEligible());
        self::assertSame('Goalkeeper', $assignment->getPosition());
        self::assertSame(1, $this->em->getRepository(MemberSport::class)->count([]));
        $crawler = $this->client->request('GET', '/admin/teams/' . $this->team->getId() . '/roster/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Dieses Mitglied gehört bereits zum Kader.');
        self::assertSame(1, $this->em->getRepository(TeamMember::class)->count([]));
    }

    public function testResultFormsAcceptZeroScoresAndRejectStaleCorrectionsAndOutOfRangeScores(): void
    {
        $tournament = $this->tournament(); $this->externalEntries($tournament, 2);
        $this->service()->generate($tournament, 20);
        $this->service()->transition($tournament, TournamentStatus::OPEN);
        $this->service()->transition($tournament, TournamentStatus::RUNNING);
        $match = $this->service()->matches($tournament)[0];
        $path = '/admin/tournaments/match/' . $match->getId() . '/result';
        $crawler = $this->client->request('GET', $path);
        $form = $crawler->selectButton('Speichern')->form();
        $stale = $form->getPhpValues();
        $this->client->submit($form, ['form[home]' => '0', 'form[away]' => '0', 'form[reason]' => 'Scoreless draw']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', '0 : 0');
        self::assertSelectorTextContains('details', 'Scoreless draw');
        $stale['form']['home'] = '1'; $stale['form']['away'] = '0'; $stale['form']['reason'] = 'Stale correction';
        $this->client->request('POST', $path, $stale);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Das Ergebnis wurde zwischenzeitlich geändert.');
        $crawler = $this->client->request('GET', $path);
        $this->client->submit($crawler->selectButton('Speichern')->form(), ['form[home]' => '10000', 'form[away]' => '-1', 'form[reason]' => 'Invalid scores']);
        self::assertResponseStatusCodeSame(422);
        $persisted = $this->em->find(TournamentMatch::class, $match->getId());
        self::assertSame(0, $persisted->getHomeScore()); self::assertSame(0, $persisted->getAwayScore());
        self::assertCount(1, $persisted->getResultHistory());
    }

    public function testEasyAdminAliasFormsAlwaysSubmitToCanonicalBackendRoutes(): void
    {
        $tournament = $this->tournament(); $editable = $this->tournament();
        $entries = $this->externalEntries($tournament, 2);
        $match = new TournamentMatch($tournament); $match->setHome($entries[0]); $match->setAway($entries[1]);
        $this->service()->saveMatch($match);
        $teamId = $this->team->getId(); $tournamentId = $tournament->getId(); $entryId = $entries[0]->getId();
        foreach ([
            ['competition_team_new', [], '/admin/teams/new'],
            ['competition_team_edit', ['id' => $teamId], '/admin/teams/' . $teamId . '/edit'],
            ['competition_roster_new', ['id' => $teamId], '/admin/teams/' . $teamId . '/roster/new'],
            ['competition_training_new', ['id' => $teamId], '/admin/teams/' . $teamId . '/training/new'],
            ['competition_tournament_new', [], '/admin/tournaments/new?club=' . $this->club->getId()],
            ['competition_tournament_edit', ['id' => $editable->getId()], '/admin/tournaments/' . $editable->getId() . '/edit'],
            ['competition_entry_new', ['id' => $tournamentId], '/admin/tournaments/' . $tournamentId . '/entry'],
            ['competition_entry', ['id' => $entryId], '/admin/tournaments/entry/' . $entryId],
            ['competition_match_new', ['id' => $tournamentId], '/admin/tournaments/' . $tournamentId . '/match/new'],
            ['competition_match_edit', ['matchId' => $match->getId()], '/admin/tournaments/match/' . $match->getId() . '/edit'],
            ['competition_result', ['id' => $match->getId()], '/admin/tournaments/match/' . $match->getId() . '/result'],
            ['competition_generate', ['id' => $tournamentId], '/admin/tournaments/' . $tournamentId . '/generate'],
            ['competition_payment', ['id' => $entryId], '/admin/tournaments/entry/' . $entryId . '/payment'],
        ] as [$route, $parameters, $action]) {
            $crawler = $this->client->request('GET', '/admin?' . http_build_query(['routeName' => $route, 'routeParams' => $parameters]));
            self::assertResponseIsSuccessful();
            self::assertSame($action, $crawler->filter('form[name]')->attr('action'), $route);
        }
        $crawler = $this->client->request('GET', '/admin?routeName=competition_team_new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'form[name]' => 'Created via menu alias', 'form[sport]' => (string) $this->sport->getId(), 'form[type]' => 'team',
        ]);
        self::assertResponseRedirects();
        self::assertNotNull($this->em->getRepository(Team::class)->findOneBy(['name' => 'Created via menu alias']));
    }

    private function tournament(): Tournament
    {
        $tournament = new Tournament($this->em->find(Club::class, $this->club->getId()));
        $tournament->setName('Competition Cup'); $tournament->setSportCode('uwr'); $tournament->setOrganizer('Own Club');
        $tournament->setLocation('Competition pool'); $tournament->setFeeCents(2550);
        $tournament->setStartsAt((new \DateTimeImmutable('first day of next month'))->modify('+9 days')->setTime(9, 0));
        $tournament->setEndsAt($tournament->getStartsAt()->setTime(19, 0));
        $this->em->persist($tournament); $this->em->flush();
        return $tournament;
    }

    private function account(string $name, string $role, ?Club $club = null): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Diver')->setEmail($name . '@example.invalid');
        $club ??= $this->club;
        if ($club->getId() !== null) { $club = $this->em->find(Club::class, $club->getId()); }
        $member = (new Member())->setClub($club)->setPerson($person)->setMembershipStatus(MembershipStatus::ACTIVE);
        $user = (new User())->setUser($name)->setEmail($name . '@example.invalid')->setPassword('unused')->setIsVerified(true)->setMember($member)->setRoles([$role]);
        $this->em->persist($user); $this->em->flush();
        return $user;
    }

    private function player(string $name): TeamMember
    {
        $account = $this->account($name, User::ROLE_MEMBER);
        $sport = (new MemberSport())->setMember($account->getMember())->setSport($this->sport);
        $account->getMember()->addSport($sport);
        $assignment = (new TeamMember())->setMember($account->getMember())->setTeam($this->team)
            ->setRole(TeamMemberRole::PLAYER)->setPosition('Defence')->setCompetitionEligible(true)->setEligibilityNote('Licence checked');
        $this->em->persist($sport); $this->em->persist($assignment); $this->em->flush();
        return $assignment;
    }

    /** @return list<TournamentEntry> */
    private function externalEntries(Tournament $tournament, int $count): array
    {
        $entries = [];
        $offset = count($this->service()->entries($tournament));
        for ($i = 0; $i < $count; ++$i) {
            $entry = new TournamentEntry($tournament); $entry->setName('Guest Team ' . ($i + $offset));
            $this->service()->register($entry); $entries[] = $entry;
        }
        return $entries;
    }

    private function service(): TournamentService { return static::getContainer()->get(TournamentService::class); }
}
