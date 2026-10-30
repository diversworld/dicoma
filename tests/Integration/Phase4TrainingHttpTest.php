<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Entity\Club;
use App\Entity\CourseParticipant;
use App\Entity\CoursePayment;
use App\Entity\Courses;
use App\Entity\CourseTrainingUnit;
use App\Entity\Member;
use App\Entity\MemberQualification;
use App\Entity\Person;
use App\Entity\Qualification;
use App\Entity\Schedule;
use App\Entity\Sport;
use App\Entity\TrainingUnitResult;
use App\Entity\TrainingUnitType;
use App\Entity\User;
use App\Enum\CourseParticipantStatus;
use App\Enum\CourseStatus;
use App\Enum\MembershipStatus;
use App\Service\CourseCompletionService;
use App\Service\FrontendCourseRegistrationService;
use App\Service\ScheduleParticipantSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\SchemaValidator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase4TrainingHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        putenv('DATABASE_URL=sqlite:///:memory:');
        putenv('MAILER_DSN=null://null');
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Training Test Club');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testPublicCatalogAndVerifiedGuestRegistrationCreateNoMembership(): void
    {
        $course = $this->course()->setRequiredEquipment('Maske und Flossen')->setDescription('Guest course description');
        $guest = $this->user('guest');
        $this->em->flush();
        $this->client->request('GET', '/my-training/courses');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Guest course description');
        self::assertSelectorTextContains('body', 'Maske und Flossen');
        self::assertSelectorExists('a[href="/register"]');
        $this->client->loginUser($guest);
        $crawler = $this->client->request('GET', '/my-training/courses');
        $this->client->submit($crawler->selectButton('Verbindlich anmelden')->form());
        self::assertResponseRedirects();
        $participant = $this->em->getRepository(CourseParticipant::class)->findOneBy(['person' => $guest->getPerson()]);
        self::assertNotNull($participant);
        self::assertNull($participant->getMember());
        self::assertSame(12550, $participant->getFeeCents());
        self::assertSame(0, $this->em->getRepository(Member::class)->count([]));
        self::assertSame($course->getId(), $participant->getCourse()->getId());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/training/participant/' . $participant->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', '125,50');
        $this->client->request('POST', '/my-training/courses/' . $course->getId() . '/register', ['_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->em->getRepository(CourseParticipant::class)->count([]));
        self::assertSame([], (new SchemaValidator($this->em))->validateMapping());
    }

    public function testCourseDivisionAssignmentsAreReviewedScopedAndPreserveCourseData(): void
    {
        $manager = $this->staff('division-course-admin', User::ROLE_COURSE_ADMIN);
        $ordinary = $this->staff('division-member', User::ROLE_MEMBER);
        $course = $this->course();
        $club = $this->em->find(Club::class, $this->club->getId());
        $diving = (new Sport())->setClub($club)->setName('Tauchen');
        $triathlon = (new Sport())->setClub($club)->setName('Triathlon');
        $inactive = (new Sport())->setClub($club)->setName('Alte Sparte')->setActive(false);
        $foreignClub = (new Club())->setName('Foreign club');
        $foreignSport = (new Sport())->setClub($foreignClub)->setName('Foreign sport');
        foreach ([$diving, $triathlon, $inactive, $foreignClub, $foreignSport] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $courseId = $course->getId();
        $url = '/admin/training/course/'.$courseId.'/divisions';
        $this->client->loginUser($manager);
        $this->client->request('GET', '/admin/training');
        self::assertSelectorExists('a[href="/admin/training/divisions/review"]');
        $this->client->request('GET', '/admin/training/divisions/review');
        self::assertSelectorTextContains('body', 'Phase 4 Dive Course');
        $this->client->request('GET', '/admin/training/divisions/review?club='.$foreignClub->getId());
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('option[value="'.$diving->getId().'"]');
        self::assertSelectorNotExists('option[value="'.$foreignSport->getId().'"]');
        $token = $crawler->filter('input[name="course_division_selection[_token]"]')->attr('value');
        $this->client->request('POST', $url, ['course_division_selection' => [
            'scope' => 'divisions', 'divisions' => [(string) $foreignSport->getId()], '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertFalse($this->em->find(Courses::class, $courseId)->isDivisionReviewed());
        $this->client->request('POST', $url, ['course_division_selection' => [
            'scope' => 'divisions', 'divisions' => [(string) $inactive->getId()], '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Inaktive Sparten können nicht neu zugeordnet werden.');
        $this->client->request('POST', $url, ['course_division_selection' => [
            'scope' => 'divisions', 'divisions' => [], '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Bitte mindestens eine Sparte auswählen.');
        $this->client->request('POST', $url, ['course_division_selection' => [
            'scope' => 'club', 'divisions' => [(string) $diving->getId()], '_token' => $token,
        ]]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, ['course_division_selection' => [
            'scope' => 'divisions', 'divisions' => [(string) $diving->getId()], '_token' => 'invalid',
        ]]);
        self::assertResponseStatusCodeSame(422);
        $this->client->request('POST', $url, ['course_division_selection' => [
            'scope' => 'divisions', 'divisions' => [(string) $diving->getId(), (string) $triathlon->getId()], '_token' => $token,
        ]]);
        self::assertResponseRedirects('/admin/training/course/'.$courseId, 303);
        $this->em->clear();
        $course = $this->em->find(Courses::class, $courseId);
        self::assertTrue($course->isDivisionReviewed());
        self::assertCount(2, $course->getDivisions());
        self::assertSame(12550, FrontendCourseRegistrationService::moneyToCents($course->getCourseFee()));
        $this->client->request('GET', '/admin/training/course/'.$courseId);
        self::assertSelectorTextContains('body', 'Tauchen');
        self::assertSelectorTextContains('body', 'Triathlon');
        $crawler = $this->client->request('GET', '/admin/training/course/'.$courseId.'/edit');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'training_course[title]' => 'Updated dive course',
        ]);
        self::assertResponseRedirects();
        $this->em->clear();
        $course = $this->em->find(Courses::class, $courseId);
        self::assertSame('Updated dive course', $course->getTitle());
        self::assertTrue($course->isDivisionReviewed());
        self::assertCount(2, $course->getDivisions());
        $this->client->request('GET', '/admin/training/divisions/review');
        self::assertSelectorNotExists('a[href="'.$url.'"]');
        $this->em->find(Sport::class, $diving->getId())->setActive(false);
        $this->em->flush();
        $crawler = $this->client->request('GET', $url);
        self::assertSelectorTextContains('body', 'Tauchen (inaktiv)');
        $this->client->submit($crawler->selectButton('Zuordnung speichern')->form());
        self::assertResponseRedirects();
        $this->em->clear();
        $course = $this->em->find(Courses::class, $courseId);
        self::assertCount(2, $course->getDivisions());
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Zuordnung speichern')->form(), [
            'course_division_selection[scope]' => 'club',
            'course_division_selection[divisions]' => [],
        ]);
        self::assertResponseRedirects();
        $this->em->clear();
        $course = $this->em->find(Courses::class, $courseId);
        self::assertTrue($course->isDivisionReviewed());
        self::assertCount(0, $course->getDivisions());
        $crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Zuordnung speichern')->form(), ['course_division_selection[scope]' => 'unreviewed']);
        self::assertResponseRedirects();
        $this->em->clear();
        self::assertFalse($this->em->find(Courses::class, $courseId)->isDivisionReviewed());
        $this->client->loginUser($ordinary);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);
        $foreignManager = $this->staff('foreign-course-manager', User::ROLE_COURSE_ADMIN, $this->em->find(Club::class, $foreignClub->getId()));
        $this->em->flush();
        $this->client->loginUser($foreignManager);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(403);
    }

    public function testCourseAndDivisionClubChangesRejectForeignAssignments(): void
    {
        $course = $this->course();
        $division = (new Sport())->setClub($this->club)->setName('Tauchen');
        $foreignClub = (new Club())->setName('Foreign club');
        $foreignDivision = (new Sport())->setClub($foreignClub)->setName('Foreign division');
        foreach ([$division, $foreignClub, $foreignDivision] as $entity) { $this->em->persist($entity); }
        $this->em->flush();
        $course->updateDivisionAssignment(true, [$division]);
        $this->em->flush();
        $courseId = $course->getId();
        $divisionId = $division->getId();
        $foreignClubId = $foreignClub->getId();
        $this->em->clear();
        $course = $this->em->find(Courses::class, $courseId);
        $division = $this->em->find(Sport::class, $divisionId);
        $foreignClub = $this->em->find(Club::class, $foreignClubId);
        $validator = static::getContainer()->get(ValidatorInterface::class);

        $course->setClub($foreignClub);
        self::assertStringContainsString('Kurs und Sparten müssen zum selben Verein gehören.', (string) $validator->validate($course));
        $course->setClub($division->getClub());
        $division->setClub($foreignClub);
        self::assertStringContainsString('Die Sparte ist Kursen eines anderen Vereins zugeordnet.', (string) $validator->validate($division));
        $division->setClub($course->getClub());

        try {
            $course->updateDivisionAssignment(true, [$foreignDivision]);
            self::fail('A foreign division must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringContainsString('selben Verein', $exception->getMessage());
        }
        self::assertCount(1, $course->getDivisions());
        $course->updateDivisionAssignment(true, []);
        $division->setClub($foreignClub);
        self::assertCount(0, $validator->validate($division));
    }

    public function testSuperAdminCanReviewCoursesWithoutClub(): void
    {
        $superAdmin = $this->staff('division-super-admin', User::ROLE_SUPER_ADMIN);
        $course = $this->course()->setClub(null);
        $this->em->flush();
        $this->client->loginUser($superAdmin);
        $this->client->request('GET', '/admin/training/divisions/review?club=unassigned');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Phase 4 Dive Course');
        self::assertSelectorTextContains('body', 'zuerst einen Verein zuordnen');
        $this->client->request('GET', '/admin/training/course/'.$course->getId().'/divisions');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDashboardUsesSelectedClubAndRequiresASelection(): void
    {
        $super = $this->staff('dashboard-context-super', User::ROLE_SUPER_ADMIN);
        $other = (new Club())->setName('Dashboard Other Club');
        $this->em->persist($other);
        foreach ([
            ['Own course one', $this->club],
            ['Own course two', $this->club],
            ['Other course', $other],
        ] as [$title, $club]) {
            $this->em->persist((new Courses())->setTitle($title)->setCategory('beginner')->setClub($club)->setStatus(CourseStatus::OPEN));
        }
        $this->em->flush();
        $this->client->loginUser($super);
        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-dashboard-metric="courses"]', '2');
        $selection = $crawler->filter('form[name="admin_club"]')->first()->form();
        $crawler = $this->client->request('GET', '/admin/training/course/new');
        self::assertResponseIsSuccessful();
        $staleForm = $crawler->selectButton('Speichern')->form();
        $this->client->submit($selection, [
            'admin_club[club]' => (string) $other->getId(),
        ]);
        self::assertResponseRedirects();
        $this->client->submit($staleForm, [
            'training_course[title]' => 'Stale course',
            'training_course[category]' => 'beginner',
            'training_course[courseFee]' => '25,00',
            'training_course[status]' => 'open',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#training_course', 'CSRF-Token');
        self::assertNull($this->em->getRepository(Courses::class)->findOneBy(['title' => 'Stale course']));
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Dashboard Other Club');
        self::assertSelectorTextContains('[data-dashboard-metric="courses"]', '1');
        $this->client->request('GET', '/admin/training/divisions/review');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Other course');
        self::assertSelectorTextNotContains('body', 'Own course one');
        $this->client->request('GET', '/admin/training/divisions/review?club='.$this->club->getId());
        self::assertResponseStatusCodeSame(403);
        $ownCourse = $this->em->getRepository(Courses::class)->findOneBy(['title' => 'Own course one']);
        $otherCourse = $this->em->getRepository(Courses::class)->findOneBy(['title' => 'Other course']);
        $ownParticipation = (new CourseParticipant())->setCourse($ownCourse)->setPerson($super->getPerson());
        $this->em->persist($ownParticipation);
        $this->em->flush();
        $this->client->request('GET', '/admin/training');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Other course');
        self::assertSelectorTextNotContains('body', 'Own course one');
        $this->client->request('GET', '/admin/training/course/'.$ownCourse->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/training/course/'.$otherCourse->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/training/participant/'.$ownParticipation->getId());
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/admin/training/course/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'training_course[title]' => 'New selected club course',
            'training_course[category]' => 'beginner',
            'training_course[courseFee]' => '25,00',
            'training_course[status]' => 'open',
        ]);
        self::assertResponseRedirects();
        $created = $this->em->getRepository(Courses::class)->findOneBy(['title' => 'New selected club course']);
        self::assertNotNull($created);
        self::assertSame($other->getId(), $created->getClub()->getId());
        $this->client->request('GET', '/admin/training/course/'.$ownCourse->getId().'/divisions');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/training/course/'.$otherCourse->getId().'/divisions');
        self::assertResponseIsSuccessful();

        $withoutClub = $this->user('dashboard-no-club')->setRoles([User::ROLE_SUPER_ADMIN]);
        $this->em->flush();
        $this->client->loginUser($withoutClub);
        $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bitte wählen Sie im Benutzermenü einen Verein aus');
        self::assertSelectorNotExists('[data-dashboard-metric="courses"]');
    }

    public function testWaitlistCancellationPromotesOldestAndDoesNotConsumeCapacity(): void
    {
        $course = $this->course()->setMaxParticipants(1);
        $first = $this->user('first');
        $second = $this->user('second');
        $third = $this->user('third');
        $this->em->flush();
        $service = $this->service();
        $a = $service->register($course->getId(), $first->getPerson());
        $b = $service->register($course->getId(), $second->getPerson());
        $c = $service->register($course->getId(), $third->getPerson());
        self::assertSame(CourseParticipantStatus::REGISTERED, $a->getStatus());
        self::assertSame(CourseParticipantStatus::WAITLISTED, $b->getStatus());
        self::assertSame(CourseParticipantStatus::WAITLISTED, $c->getStatus());
        self::assertSame(1, $course->getParticipantCount());
        $service->cancel($a);
        self::assertSame(CourseParticipantStatus::REGISTERED, $b->getStatus());
        self::assertSame(CourseParticipantStatus::WAITLISTED, $c->getStatus());
        self::assertSame(1, $course->getParticipantCount());
        $this->expectException(\DomainException::class);
        $service->register($course->getId(), $first->getPerson());
    }

    public function testPaymentLedgerRefundsAndDuplicateReferences(): void
    {
        $manager = $this->staff('finance', User::ROLE_COURSE_ADMIN);
        $guest = $this->user('payer');
        $course = $this->course();
        $this->em->flush();
        $participant = $this->service()->register($course->getId(), $guest->getPerson());
        $this->client->loginUser($manager);
        $this->payment($participant, '100,50', 'receipt-1');
        self::assertSame(10050, $this->service()->paidCents($participant));
        $this->payment($participant, '100,50', 'receipt-1');
        self::assertSame(1, $this->em->getRepository(CoursePayment::class)->count([]));
        $this->payment($participant, '26.00', 'overpayment');
        self::assertSame(1, $this->em->getRepository(CoursePayment::class)->count([]));
        $participant = $this->em->find(CourseParticipant::class, $participant->getId());
        $this->service()->cancel($participant);
        $this->payment($participant, '-100.50', 'refund-1');
        self::assertSame(0, $this->service()->paidCents($participant));
        self::assertSame(2, $this->em->getRepository(CoursePayment::class)->count([]));
        $this->payment($participant, '-0.01', 'excess-refund');
        self::assertSame(2, $this->em->getRepository(CoursePayment::class)->count([]));
    }

    public function testGuestAttendanceExamCertificateAndLaterMembershipPreserveIdentity(): void
    {
        $instructor = $this->staff('instructor', User::ROLE_INSTRUCTOR);
        $manager = $this->staff('leader', User::ROLE_TRAINING_MANAGER);
        $guest = $this->user('student');
        $course = $this->course()->setLeadInstructor($instructor->getMember());
        [$schedule, $type] = $this->plan($course, $instructor->getMember());
        $this->em->flush();
        $participant = $this->service()->register($course->getId(), $guest->getPerson());
        static::getContainer()->get(ScheduleParticipantSyncService::class)->sync($schedule);
        $booking = $this->em->getRepository(Booking::class)->findOneBy(['courseParticipant' => $participant]);
        self::assertNotNull($booking);
        self::assertNull($booking->getMember());
        $this->client->loginUser($instructor);
        $crawler = $this->client->request('GET', '/training/assess/' . $schedule->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'student Diver');
        $form = $crawler->selectButton('Bewertungen speichern')->form();
        $this->client->submit($form, [
            'attendance[' . $participant->getId() . ']' => 'present',
            'status[' . $participant->getId() . ']' => 'passed',
        ]);
        self::assertResponseRedirects();
        $result = $this->em->getRepository(TrainingUnitResult::class)->findOneBy(['courseParticipant' => $participant]);
        self::assertSame('passed', $result->getStatus()->value);
        self::assertSame($instructor->getMember()->getId(), $result->getAssessor()->getId());

        $this->client->loginUser($manager);
        $crawler = $this->client->request('GET', '/admin/training/participant/' . $participant->getId());
        $this->client->submit($crawler->selectButton('Bestanden und Nachweis ausstellen')->form(), ['brevetNumber' => 'BREVET-GUEST-2026']);
        self::assertResponseRedirects();
        $participant = $this->em->find(CourseParticipant::class, $participant->getId());
        self::assertTrue($participant->hasPassed());
        self::assertNotNull($participant->getArchivedAt());
        self::assertSame('present', $participant->getTrainingArchive()['attendance'][0]['status']);
        self::assertCount(1, $participant->getTrainingArchive()['evidence']);
        self::assertSame(0, $this->em->getRepository(MemberQualification::class)->count([]));

        $crawler = $this->client->request('GET', '/admin/training/course/' . $course->getId());
        $this->client->submit($crawler->selectButton('Kurs abschließen und Nachweise archivieren')->form());
        $course = $this->em->find(Courses::class, $course->getId());
        self::assertSame(CourseStatus::COMPLETED, $course->getStatus());
        $course->setTitle('Changed live title');
        $this->em->flush();
        $this->client->loginUser($guest);
        foreach (['/training', '/my-training/progress', '/my-training/attendance', '/training/participant/' . $participant->getId() . '/certificate'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
        }
        self::assertSelectorTextContains('body', 'Phase 4 Dive Course');
        self::assertSelectorTextContains('body', 'BREVET-GUEST-2026');
        self::assertSelectorTextNotContains('body', 'Changed live title');
        $guest = $this->em->find(User::class, $guest->getId());
        $personId = $guest->getPerson()->getId();
        $member = (new Member())->setPerson($guest->getPerson())->setClub($this->em->find(Club::class, $this->club->getId()))->setUser($guest)->setMembershipStatus(MembershipStatus::ACTIVE);
        $this->em->persist($member);
        $this->em->flush();
        $this->em->clear();
        $guest = $this->em->find(User::class, $guest->getId());
        $this->client->loginUser($guest);
        $this->client->request('GET', '/training/participant/' . $participant->getId() . '/certificate');
        self::assertResponseIsSuccessful();
        self::assertSame($personId, $guest->getPerson()->getId());
        self::assertSame(1, $this->em->getRepository(CourseParticipant::class)->count(['person' => $guest->getPerson()]));
    }

    public function testRoleAndClubBoundariesProtectAllTrainingActions(): void
    {
        $guest = $this->user('owner');
        $other = $this->user('other');
        $course = $this->course();
        $foreign = (new Club())->setName('Foreign training club');
        $this->em->persist($foreign);
        $foreignAdmin = $this->staff('foreign-admin', User::ROLE_ADMIN, $foreign);
        $instructor = $this->staff('not-assigned', User::ROLE_INSTRUCTOR);
        [$schedule] = $this->plan($course, $instructor->getMember());
        $schedule->setInstructor(null);
        $this->em->flush();
        $participant = $this->service()->register($course->getId(), $guest->getPerson());
        foreach ([$other, $foreignAdmin, $instructor] as $actor) {
            $this->client->loginUser($actor);
            foreach (['/training/participant/' . $participant->getId(), '/training/course/' . $course->getId(), '/training/course/' . $course->getId() . '/edit'] as $path) {
                foreach ([$path, '/admin' . $path] as $url) {
                    $this->client->request('GET', $url);
                    self::assertResponseStatusCodeSame(403);
                }
            }
        }
        $this->client->loginUser($foreignAdmin);
        $this->client->request('GET', '/training/assess/' . $schedule->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($guest);
        $this->client->request('POST', '/training/participant/' . $participant->getId() . '/cancel', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(CourseParticipantStatus::REGISTERED, $participant->getStatus());
        self::assertNull($participant->getArchivedAt());
        foreach (['payment', 'complete'] as $operation) {
            $this->client->request('POST', '/training/participant/' . $participant->getId() . '/' . $operation, ['_token' => 'invalid']);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(0, $this->em->getRepository(CoursePayment::class)->count([]));
    }

    public function testCourseAdministrationFormsAndMakeupSession(): void
    {
        $manager = $this->staff('course-admin', User::ROLE_COURSE_ADMIN);
        $instructor = $this->staff('teacher', User::ROLE_INSTRUCTOR);
        $course = $this->course()->setLeadInstructor($instructor->getMember());
        [$schedule, $type] = $this->plan($course, $instructor->getMember());
        $guest = $this->user('makeup');
        $this->em->flush();
        $participant = $this->service()->register($course->getId(), $guest->getPerson());
        static::getContainer()->get(ScheduleParticipantSyncService::class)->sync($schedule);
        $this->client->loginUser($manager);
        foreach (['/admin/training', '/admin/training/course/new', '/admin/training/course/' . $course->getId() . '/edit', '/admin/training/course/' . $course->getId() . '/unit', '/admin/training/course/' . $course->getId() . '/unit-type'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('aside.sidebar');
            self::assertSelectorExists('[data-training-staff-navigation]');
        }
        $crawler = $this->client->request('GET', '/admin/training/course/' . $course->getId() . '/session');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'form[title]' => 'Makeup exam', 'form[trainingUnitType]' => (string) $type->getId(),
            'form[startDate]' => '2026-12-15', 'form[startTime]' => '18:00', 'form[location]' => 'Pool',
            'form[instructor]' => (string) $instructor->getMember()->getId(), 'form[makeupFor]' => (string) $schedule->getId(),
        ]);
        self::assertResponseRedirects();
        $makeup = $this->em->getRepository(Schedule::class)->findOneBy(['title' => 'Makeup exam']);
        self::assertNotNull($makeup);
        $booking = $this->em->getRepository(Booking::class)->findOneBy(['schedule' => $makeup, 'courseParticipant' => $participant]);
        self::assertNotNull($booking->getMakeupFor());
        self::assertSame($schedule->getId(), $booking->getMakeupFor()->getSchedule()->getId());
        $this->client->request('GET', '/training/assess/' . $schedule->getId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testClosedDeadlinesRejectRegistrationsWithoutClosingEntityManager(): void
    {
        $member = $this->staff('member-student', User::ROLE_MEMBER);
        $course = $this->course()->setRegistrationDeadline(new \DateTimeImmutable('yesterday'));
        $this->em->flush();
        try {
            $this->service()->register($course->getId(), $member->getPerson());
            self::fail('Expired registration deadline must be rejected.');
        } catch (\DomainException $e) { self::assertStringContainsString('Anmeldefrist', $e->getMessage()); }
        self::assertSame(0, $this->em->getRepository(CourseParticipant::class)->count([]));
        self::assertTrue($this->em->isOpen());
    }

    public function testMemberCompletionKeepsQualificationWorkflowAndArchiveIsProtected(): void
    {
        $student = $this->staff('club-student', User::ROLE_MEMBER);
        $course = $this->course();
        [$schedule] = $this->plan($course, $student->getMember());
        $this->em->flush();
        $participant = $this->service()->register($course->getId(), $student->getMember());
        self::assertSame($student->getMember(), $participant->getMember());
        static::getContainer()->get(ScheduleParticipantSyncService::class)->sync($schedule);
        $booking = $this->em->getRepository(Booking::class)->findOneBy(['courseParticipant' => $participant]);
        static::getContainer()->get(\App\Service\TrainingAssessmentService::class)->save($schedule, [$participant], [
            $participant->getId() => ['attendance' => \App\Enum\BookingAttendanceStatus::PRESENT, 'result' => \App\Enum\TrainingUnitResultStatus::PASSED],
        ], [], [$participant->getId() => $booking]);
        $qualification = static::getContainer()->get(CourseCompletionService::class)->pass($participant, null, 'MEMBER-BREVET-42');
        self::assertInstanceOf(MemberQualification::class, $qualification);
        self::assertSame($student->getMember(), $qualification->getMember());
        self::assertSame('MEMBER-BREVET-42', $qualification->getCertificateNumber());
        self::assertSame('MEMBER-BREVET-42', $participant->getTrainingArchive()['brevetNumber']);
        $this->expectException(\DomainException::class);
        $this->em->remove($participant);
    }

    public function testCourseCreationPersistsAllAdministrativeFields(): void
    {
        $manager = $this->staff('creator', User::ROLE_COURSE_ADMIN);
        $instructor = $this->staff('lead-teacher', User::ROLE_TRAINING_MANAGER);
        $qualification = (new Qualification())->setName('New target qualification')->setClub($this->club);
        $this->em->persist($qualification);
        $this->em->flush();
        $this->client->loginUser($manager);
        $crawler = $this->client->request('GET', '/admin/training/course/new');
        $this->client->submit($crawler->selectButton('Speichern')->form(), [
            'training_course[title]' => 'New full course', 'training_course[category]' => 'sonder',
            'training_course[description]' => 'Theory and practice', 'training_course[requirements]' => 'Medical clearance',
            'training_course[requiredEquipment]' => 'Mask and fins', 'training_course[courseFee]' => '49,95',
            'training_course[maxParticipants]' => '8', 'training_course[registrationDeadline]' => '2026-12-31',
            'training_course[status]' => 'open', 'training_course[qualification]' => (string) $qualification->getId(),
            'training_course[leadInstructor]' => (string) $instructor->getMember()->getId(),
        ]);
        self::assertResponseRedirects();
        $course = $this->em->getRepository(Courses::class)->findOneBy(['title' => 'New full course']);
        self::assertNotNull($course);
        self::assertSame('49.95', $course->getCourseFee());
        self::assertSame(8, $course->getMaxParticipants());
        self::assertSame('Mask and fins', $course->getRequiredEquipment());
        self::assertSame($this->club->getId(), $course->getClub()->getId());
        self::assertSame($instructor->getMember()->getId(), $course->getLeadInstructor()->getId());
        self::assertSame('2026-12-31', $course->getRegistrationDeadline()->format('Y-m-d'));
    }

    public function testExistingAdministrativeCourseFormRendersRequiredEquipment(): void
    {
        $admin = $this->staff('legacy-admin', User::ROLE_ADMIN);
        $this->em->flush();
        $this->client->loginUser($admin);
        $url = static::getContainer()->get(\EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator::class)
            ->unsetAll()->setDashboard(\App\Controller\Admin\DashboardController::class)
            ->setController(\App\Controller\Admin\CoursesCrudController::class)
            ->setAction(\EasyCorp\Bundle\EasyAdminBundle\Config\Crud::PAGE_NEW)->generateUrl();
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('textarea[id$="_requiredEquipment"]');
    }

    public function testCourseTemplateExportAndDuplicateStayInBackendAndAcceptPost(): void
    {
        $admin = $this->staff('template-admin', User::ROLE_SUPER_ADMIN);
        $course = $this->course();
        [$schedule] = $this->plan($course, $admin->getMember());
        $schedule->setTrainingUnitSequence(1)->setStartTime(new \DateTimeImmutable('18:00'))->setDuration(60);
        $this->em->flush();
        $this->client->loginUser($admin);
        $urls = static::getContainer()->get(\EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator::class);
        $url = (clone $urls)->unsetAll()->setDashboard(\App\Controller\Admin\DashboardController::class)
            ->setController(\App\Controller\Admin\CoursesCrudController::class)->setAction('createTemplateFromCourse')
            ->setEntityId($course->getId())->generateUrl();
        $crawler = $this->client->request('GET', $url);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Vorlage jetzt speichern')->form(), [
            'name' => 'Saved HTTP template', 'description' => 'Created through the actual form',
        ]);
        self::assertResponseRedirects();
        $template = $this->em->getRepository(\App\Entity\ScheduleTemplate::class)->findOneBy(['name' => 'Saved HTTP template']);
        self::assertNotNull($template);
        self::assertCount(1, $template->getEntries());
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorNotExists('nav[aria-label="Hauptnavigation"]');
        $crawler = $this->client->request('GET', '/admin/course/' . $course->getId() . '/export-template');
        $this->client->submit($crawler->selectButton('Vorlage jetzt speichern')->form(), ['name' => 'x']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorTextContains('.alert-danger', '3 bis 150 Zeichen');
        self::assertSame(1, $this->em->getRepository(\App\Entity\ScheduleTemplate::class)->count([]));
        $this->client->request('POST', '/admin/course/' . $course->getId() . '/export-template', ['name' => 'Forged', '_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->em->getRepository(\App\Entity\ScheduleTemplate::class)->count([]));
        $url = (clone $urls)->unsetAll()->setDashboard(\App\Controller\Admin\DashboardController::class)
            ->setController(\App\Controller\Admin\ScheduleTemplateCrudController::class)->setAction('duplicateTemplate')
            ->setEntityId($template->getId())->generateUrl();
        $crawler = $this->client->request('GET', $url);
        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Vorlage duplizieren')->form(), ['name' => 'Copied HTTP template']);
        self::assertResponseRedirects();
        $copy = $this->em->getRepository(\App\Entity\ScheduleTemplate::class)->findOneBy(['name' => 'Copied HTTP template']);
        self::assertNotNull($copy);
        self::assertCount(1, $copy->getEntries());
        self::assertNotSame($template->getEntries()->first()->getId(), $copy->getEntries()->first()->getId());
        self::assertSame(0, $copy->getEntries()->first()->getDayOffset());
        self::assertSame('18:00', $copy->getEntries()->first()->getStartTime()->format('H:i'));
        $crawler = $this->client->request('GET', '/admin/schedule-template/' . $template->getId() . '/duplicate');
        $this->client->submit($crawler->selectButton('Vorlage duplizieren')->form(), ['name' => '']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSame(2, $this->em->getRepository(\App\Entity\ScheduleTemplate::class)->count([]));
        $this->client->request('POST', '/admin/schedule-template/' . $template->getId() . '/duplicate', ['name' => 'Forged', '_token' => 'forged']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(2, $this->em->getRepository(\App\Entity\ScheduleTemplate::class)->count([]));
    }

    public function testAllAdministrativeIndexAndCreationRoutesRenderWithoutServerErrors(): void
    {
        $admin = $this->staff('route-admin', User::ROLE_SUPER_ADMIN);
        $this->em->flush();
        $this->client->loginUser($admin);
        $router = static::getContainer()->get('router');
        $failures = [];
        $checked = 0;
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/admin')
                || ($route->getMethods() !== [] && !in_array('GET', $route->getMethods(), true))) { continue; }
            if (!in_array($route->getDefault('crudAction'), [null, 'index', 'new'], true)
                || $name === 'admin_equipment_attribute_schema') { continue; }
            $parameters = $route->compile()->getPathVariables();
            if (array_diff($parameters, ['_locale']) !== []) { continue; }
            $url = $router->generate($name, ['_locale' => 'de']);
            $this->client->request('GET', $url);
            $status = $this->client->getResponse()->getStatusCode();
            ++$checked;
            if (in_array($name, ['admin_member_tank_check_booking_new', 'admin_tuv_notification_new'], true)) {
                self::assertResponseStatusCodeSame(403);
            } elseif ($status >= 400) {
                $failures[] = $name . ' ' . $status . ' ' . rawurldecode($this->client->getResponse()->headers->get('X-Debug-Exception', ''));
            } elseif ($status === 200 && str_contains($this->client->getResponse()->headers->get('Content-Type', ''), 'text/html')) {
                if ($this->client->getCrawler()->filter('aside.sidebar')->count() !== 1) {
                    $failures[] = $name . ' does not use the backend layout';
                }
                $this->assertFormRoutes($this->client->getCrawler());
            }
        }
        self::assertGreaterThan(40, $checked);
        self::assertSame([], $failures);
    }

    public function testAdministrativeTrainingPagesAndTheirFormsUseBackendRoutes(): void
    {
        $admin = $this->staff('workflow-admin', User::ROLE_SUPER_ADMIN);
        $course = $this->course()->setLeadInstructor($admin->getMember());
        [$schedule] = $this->plan($course, $admin->getMember());
        $schedule->setTrainingUnitSequence(1)->setStartTime(new \DateTimeImmutable('18:00'))->setDuration(60);
        $guest = $this->user('workflow-guest');
        $this->em->flush();
        $participant = $this->service()->register($course->getId(), $guest->getPerson());
        static::getContainer()->get(ScheduleParticipantSyncService::class)->sync($schedule);
        $this->client->loginUser($admin);
        $router = static::getContainer()->get('router');
        $routes = [
            'admin_course_history' => $course->getId(),
            'admin_course_completion_wizard' => $course->getId(),
            'admin_course_complete_confirm' => $course->getId(),
            'admin_course_import_confirm' => $course->getId(),
            'admin_course_generate_confirm' => $course->getId(),
            'admin_course_training_matrix' => $course->getId(),
            'admin_course_plan' => $course->getId(),
            'admin_course_export_template' => $course->getId(),
            'admin_training_timetable' => $course->getId(),
            'admin_training_progress' => $participant->getId(),
            'admin_participant_pass_confirm' => $participant->getId(),
            'admin_training_results_batch' => $schedule->getId(),
            'admin_schedule_attendance' => $schedule->getId(),
            'admin_schedule_sync_participants' => $schedule->getId(),
            'admin_portal_training_course' => $course->getId(),
            'admin_portal_training_participant' => $participant->getId(),
            'admin_portal_training_assess' => $schedule->getId(),
        ];
        foreach ($routes as $name => $id) {
            $crawler = $this->client->request('GET', $router->generate($name, ['id' => $id]));
            self::assertSame(200, $this->client->getResponse()->getStatusCode(),
                $name . ': ' . rawurldecode($this->client->getResponse()->headers->get('X-Debug-Exception', '')));
            self::assertSelectorExists('aside.sidebar');
            self::assertSelectorNotExists('nav[aria-label="Hauptnavigation"]');
            $this->assertFormRoutes($crawler);
            if ($name === 'admin_course_plan') {
                self::assertStringContainsString('function initializeSchedulePlanning()', $this->client->getResponse()->getContent());
                self::assertSelectorExists('head link[rel="stylesheet"][href="/css/pages/admin-course-schedule-planning.css"]');
                self::assertSelectorNotExists('style');
            }

        }
        $booking = $this->em->getRepository(Booking::class)->findOneBy(['courseParticipant' => $participant]);
        foreach ([
            'admin_courses_detail' => $course->getId(),
            'admin_course_participant_index' => null,
            'admin_course_participant_detail' => $participant->getId(),
            'admin_schedule_detail' => $schedule->getId(),
            'admin_booking_index' => null,
            'admin_booking_detail' => $booking->getId(),
        ] as $name => $id) {
            $crawler = $this->client->request('GET', $router->generate($name, ['_locale' => 'de'] + ($id === null ? [] : ['entityId' => $id])));
            self::assertSame(200, $this->client->getResponse()->getStatusCode(),
                $name . ': ' . rawurldecode($this->client->getResponse()->headers->get('X-Debug-Exception', '')));
            self::assertSelectorExists('aside.sidebar');
            $this->assertFormRoutes($crawler);
        }
        $this->client->request('GET', '/training/course/' . $course->getId());
        self::assertResponseRedirects('/admin/training/course/' . $course->getId(), 303);
        $this->client->request('GET', '/training/participant/' . $participant->getId());
        self::assertResponseRedirects('/admin/training/participant/' . $participant->getId(), 303);
        $this->client->request('POST', '/training/course/new', ['training_course' => ['title' => '']]);
        self::assertResponseRedirects('/admin/training/course/new', 307);
        $this->client->followRedirect();
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('aside.sidebar');
        self::assertSame(1, $this->em->getRepository(Courses::class)->count([]));
        $this->client->request('GET', '/my-training/assess/' . $schedule->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('aside.sidebar');
        $this->assertFormRoutes($this->client->getCrawler(), false);
    }

    public function testLegacyAdministrativePagesDoNotLeakIntoFrontendOrFailToRender(): void
    {
        $admin = $this->staff('legacy-admin', User::ROLE_SUPER_ADMIN);
        $admin->setRoles([User::ROLE_SUPER_ADMIN, User::ROLE_MEMBER]);
        $this->em->flush();
        $this->client->loginUser($admin);
        $failures = [];
        $checked = 0;
        foreach (static::getContainer()->get('router')->getRouteCollection() as $name => $route) {
            if (!str_starts_with($name, 'app_') || str_starts_with($route->getPath(), '/admin')
                || ($route->getMethods() !== [] && !in_array('GET', $route->getMethods(), true))
                || $route->compile()->getPathVariables() !== []) { continue; }
            $controller = $route->getDefault('_controller');
            if (!is_string($controller)) { continue; }
            [$class, $method] = str_contains($controller, '::') ? explode('::', $controller) : [$controller, '__invoke'];
            $reflection = new \ReflectionClass($class);
            $attributes = [...$reflection->getAttributes(\Symfony\Component\Security\Http\Attribute\IsGranted::class),
                ...$reflection->getMethod($method)->getAttributes(\Symfony\Component\Security\Http\Attribute\IsGranted::class)];
            $administration = $name === 'app_settings' || $route->hasDefault('legacy_permission');
            foreach ($attributes as $attribute) {
                if (in_array($attribute->newInstance()->attribute, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], true)) { $administration = true; }
            }
            if (!$administration) { continue; }
            ++$checked;
            $crawler = $this->client->request('GET', static::getContainer()->get('router')->generate($name));
            if ($this->client->getResponse()->isRedirect()) { $crawler = $this->client->followRedirect(); }
            $status = $this->client->getResponse()->getStatusCode();
            if ($status >= 400) {
                $failures[] = $name . ': ' . $status . ' ' . rawurldecode($this->client->getResponse()->headers->get('X-Debug-Exception', ''));
            } elseif ($status === 200 && str_contains($this->client->getResponse()->headers->get('Content-Type', ''), 'text/html')) {
                if ($crawler->filter('aside.sidebar')->count() !== 1) { $failures[] = $name . ': frontend instead of backend'; }
                $this->assertFormRoutes($crawler);
            }
        }
        self::assertGreaterThan(10, $checked);
        self::assertSame([], $failures);
    }

    public function testAllRetiredAdministrationRoutesRedirectReadsAndRejectWritesWithoutChanges(): void
    {
        $admin = $this->staff('retired-admin', User::ROLE_SUPER_ADMIN);
        $admin->setRoles([User::ROLE_SUPER_ADMIN, User::ROLE_MEMBER]);
        $course = $this->course();
        $this->em->flush();
        $this->client->loginUser($admin);
        $router = static::getContainer()->get('router');
        $checked = 0;
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!$route->hasDefault('legacy_permission')) { continue; }
            ++$checked;
            $parameters = array_fill_keys($route->compile()->getPathVariables(), 1);
            $url = $router->generate($name, $parameters);
            if (in_array('GET', $route->getMethods(), true)) {
                $this->client->request('GET', $url);
                self::assertResponseStatusCodeSame(303);
                self::assertStringContainsString('/admin', $this->client->getResponse()->headers->get('Location'));
            }
            if (in_array('POST', $route->getMethods(), true)) {
                $this->client->request('POST', $url, ['_token' => 'stale', 'title' => 'Must not be saved']);
                self::assertResponseStatusCodeSame(410);
                self::assertSelectorExists('aside.sidebar');
                self::assertSelectorTextContains('body', 'keine Daten gespeichert oder gelöscht');
                self::assertSelectorExists('a.btn-primary[href]');
            }
        }
        self::assertSame(54, $checked);
        self::assertSame(1, $this->em->getRepository(Courses::class)->count([]));
        self::assertSame('Phase 4 Dive Course', $this->em->find(Courses::class, $course->getId())->getTitle());
        self::assertSame(1, $this->em->getRepository(Member::class)->count([]));
        self::assertSame(0, $this->em->getRepository(Booking::class)->count([]));
        $instructor = $this->staff('legacy-reader', User::ROLE_INSTRUCTOR);
        $this->em->flush();
        $this->client->loginUser($instructor);
        $this->client->request('GET', '/brevets/');
        self::assertResponseRedirects('/admin/training', 303);
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-training-staff-navigation]');
        $this->client->request('GET', '/brevets/new');
        self::assertResponseStatusCodeSame(403);
        $clubAdmin = $this->staff('legacy-club-admin', User::ROLE_ADMIN);
        $this->em->flush();
        $this->client->loginUser($clubAdmin);
        $this->client->request('GET', '/student/new');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/booking/new');
        self::assertResponseRedirects('/admin/training', 303);
        $this->client->request('GET', '/tank/new');
        self::assertResponseRedirects('/equipment-manager/tuv', 303);
    }

    public function testGuestTimetableIsPersonOwnedPublishedAndFrontendEvenForAdministrators(): void
    {
        $admin = $this->staff('student-admin', User::ROLE_SUPER_ADMIN);
        $guest = $this->user('timetable-guest');
        $stranger = $this->user('timetable-stranger');
        $course = $this->course();
        [$schedule] = $this->plan($course, $admin->getMember());
        $this->em->flush();
        $registration = $this->service()->register($course->getId(), $guest->getPerson());
        $own = $this->service()->register($course->getId(), $admin->getPerson());
        $path = '/my-training/registrations/' . $registration->getId() . '/timetable';
        $this->client->loginUser($guest);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/training/participant/' . $registration->getId());
        self::assertSelectorTextContains('body', 'Terminplan ist noch nicht freigegeben');
        self::assertSelectorTextNotContains('body', 'Classroom and pool');
        $course = $this->em->find(Courses::class, $course->getId());
        $course->publishSchedule();
        $this->em->flush();
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="' . $path . '.csv"]');
        self::assertSelectorNotExists('aside.sidebar');
        self::assertSelectorTextContains('body', 'Classroom and pool');
        $this->client->request('GET', $path . '.csv');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('Classroom and pool', $this->client->getResponse()->getContent());
        $this->client->loginUser($stranger);
        foreach ([$path, $path . '.csv'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->loginUser($admin);
        foreach (['/training', '/training/participant/' . $own->getId(), '/my-training/registrations/' . $own->getId() . '/timetable'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('aside.sidebar');
            self::assertSelectorNotExists('form[action$="/payment"]');
        }
        $this->client->loginUser($guest);
        $this->client->request('GET', '/admin/training');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->em->getRepository(Member::class)->count([]));
    }

    public function testCourseAdministrationPublishesAndEmailsTimetablesToGuestsWithoutContactingAMailServer(): void
    {
        $manager = $this->staff('timetable-manager', User::ROLE_COURSE_ADMIN);
        $member = $this->staff('email-member', User::ROLE_MEMBER);
        $guest = $this->user('email-guest');
        $waiting = $this->user('email-waiting');
        $course = $this->course()->setMaxParticipants(2);
        $this->plan($course, $manager->getMember());
        $this->em->flush();
        $this->service()->register($course->getId(), $guest->getPerson());
        $this->service()->register($course->getId(), $member->getPerson());
        $this->service()->register($course->getId(), $waiting->getPerson());
        $recipients = [];
        $fail = false;
        $mailer = $this->createMock(\Symfony\Component\Mailer\MailerInterface::class);
        $mailer->expects(self::exactly(4))->method('send')->willReturnCallback(
            static function (\Symfony\Component\Mime\Email $email) use (&$recipients, &$fail): void {
                if ($fail) { throw new \Symfony\Component\Mailer\Exception\TransportException('Test mail transport failure.'); }
                $recipients[] = $email->getTo()[0]->getAddress();
            }
        );
        static::getContainer()->set(\Symfony\Component\Mailer\MailerInterface::class, $mailer);
        $this->client->loginUser($manager);
        $path = '/admin/training/timetable/' . $course->getId();
        $crawler = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-training-staff-navigation]');
        $this->client->submit($crawler->selectButton('Terminplan freigeben / aktualisieren')->form());
        self::assertResponseRedirects($path, 303);
        self::assertNotNull($this->em->find(Courses::class, $course->getId())->getSchedulePublishedAt());
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Terminplan per E-Mail senden')->form());
        self::assertResponseRedirects($path, 303);
        sort($recipients);
        self::assertSame(['email-guest@example.invalid', 'email-member@example.invalid'], $recipients);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('body', '2 Empfänger; 1 übersprungen; 0 fehlgeschlagen');
        $fail = true;
        $this->client->submit($crawler->selectButton('Terminplan per E-Mail senden')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', '0 Empfänger; 1 übersprungen; 2 fehlgeschlagen');
    }

    public function testClubAdministratorCanPlanOwnCoursesButCannotAccessAnotherClub(): void
    {
        $admin = $this->staff('club-planner', User::ROLE_ADMIN);
        $course = $this->course();
        [$schedule] = $this->plan($course, $admin->getMember());
        $foreignClub = (new Club())->setName('Other training club');
        $this->em->persist($foreignClub);
        $foreign = $this->course()->setClub($foreignClub)->setTitle('Foreign private training');
        $this->em->flush();
        $this->client->loginUser($admin);
        foreach (['plan', 'import-training-plan', 'generate-schedules', 'export-template'] as $action) {
            $this->client->request('GET', '/admin/course/' . $course->getId() . '/' . $action);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('aside.sidebar');
            $this->client->request('GET', '/admin/course/' . $foreign->getId() . '/' . $action);
            self::assertResponseStatusCodeSame(403);
        }
        $crawler = $this->client->request('GET', '/admin/course/' . $course->getId() . '/plan');
        $form = $crawler->filter('form[name="course_schedule_planning"]')->form();
        $values = $form->getPhpValues();
        $values['course_schedule_planning']['_token'] = 'forged';
        $this->client->request('POST', $form->getUri(), $values, [], ['HTTP_REFERER' => 'https://other.invalid/']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorTextContains('#course_schedule_planning_error1', 'CSRF');
        $crawler = $this->client->request('GET', '/admin/course/' . $course->getId() . '/plan');
        self::assertSelectorExists('select[name$="[instructor]"] option[value="' . $admin->getMember()->getId() . '"]');
        $this->client->submit($crawler->filter('form[name="course_schedule_planning"]')->form(), [
            'course_schedule_planning[schedules][0][location]' => 'Saved planning location',
        ]);
        self::assertResponseRedirects();
        self::assertSame('Saved planning location', $this->em->find(Schedule::class, $schedule->getId())->getLocation());
        self::assertSame($admin->getMember()->getId(), $this->em->find(Schedule::class, $schedule->getId())->getInstructor()->getId());
        foreach (['0', '24.25'] as $invalidDuration) {
            $crawler = $this->client->request('GET', '/admin/course/' . $course->getId() . '/plan');
            $this->client->submit($crawler->filter('form[name="course_schedule_planning"]')->form(), [
                'course_schedule_planning[schedules][0][durationHours]' => $invalidDuration,
            ]);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('.invalid-feedback');
            self::assertNull($this->em->getConnection()->fetchOne('SELECT duration FROM schedule WHERE id = ?', [$schedule->getId()]));
        }
        $this->client->request('POST', '/admin/course/' . $foreign->getId() . '/plan', $values);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/admin/de/training-overview');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Foreign private training');
    }

    private function assertFormRoutes(\Symfony\Component\DomCrawler\Crawler $crawler, bool $backend = true): void
    {
        $router = static::getContainer()->get('router');
        foreach ($crawler->filter('form') as $node) {
            if ($node->getAttribute('method') === 'dialog'
                || in_array($node->getAttribute('id'), ['detail-duplicate', 'action-confirmation-form'], true)) { continue; }
            $form = (new \Symfony\Component\DomCrawler\Crawler($node, $crawler->getUri()))->form();
            $request = \Symfony\Component\HttpFoundation\Request::create($form->getUri(), $form->getMethod());
            try {
                $match = $router->matchRequest($request);
            } catch (\Symfony\Component\Routing\Exception\ExceptionInterface $exception) {
                self::fail($form->getMethod() . ' ' . $form->getUri() . ': ' . $exception->getMessage());
            }
            self::assertArrayHasKey('_route', $match);
            if ($backend && $match['_route'] !== 'app_logout') {
                self::assertStringStartsWith('/admin', $request->getPathInfo());
            }
        }
    }

    private function payment(CourseParticipant $participant, string $amount, string $reference): void
    {
        $crawler = $this->client->request('GET', '/admin/training/participant/' . $participant->getId());
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Zahlung / Erstattung buchen')->form(), [
            'amount' => $amount, 'method' => 'transfer', 'reference' => $reference,
        ]);
        self::assertResponseRedirects();
    }

    private function service(): FrontendCourseRegistrationService
    {
        return static::getContainer()->get(FrontendCourseRegistrationService::class);
    }

    private function course(): Courses
    {
        $qualification = (new Qualification())->setName('Dive brevet')->setClub($this->club)->setIssuer('Test training organization');
        $this->em->persist($qualification);
        $course = (new Courses())->setTitle('Phase 4 Dive Course')->setClub($this->club)->setCategory('beginner')
            ->setStatus(CourseStatus::OPEN)->setCourseFee('125.50')->setQualification($qualification);
        $this->em->persist($course);
        return $course;
    }

    private function user(string $name): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Diver')->setEmail($name . '@example.invalid');
        $user = (new User())->setUser($name)->setEmail($name . '@example.invalid')->setPerson($person)->setPassword('not-used')->setIsVerified(true);
        $this->em->persist($user);
        return $user;
    }

    private function staff(string $name, string $role, ?Club $club = null): User
    {
        $user = $this->user($name)->setRoles([$role]);
        $club ??= $this->club;
        if ($club->getId() !== null) { $club = $this->em->find(Club::class, $club->getId()); }
        $member = (new Member())->setPerson($user->getPerson())
            ->setClub($club)
            ->setUser($user)->setMembershipStatus(MembershipStatus::ACTIVE);
        $this->em->persist($member);
        return $user;
    }

    private function plan(Courses $course, Member $instructor): array
    {
        $type = (new TrainingUnitType())->setClub($this->club)->setCode('EXAM')->setName('Theory and practice exam')->setExamination(true);
        $this->em->persist($type);
        $course->addTrainingUnit((new CourseTrainingUnit())->setTrainingUnitType($type)->setNotes('Demonstrate safe diving'));
        $schedule = (new Schedule())->setTitle('Exam')->setCourses($course)->setTrainingUnitType($type)
            ->setInstructor($instructor)->setStartDate(new \DateTimeImmutable('2026-12-01'))->setLocation('Classroom and pool');
        $course->addSchedule($schedule);
        $this->em->persist($schedule);
        return [$schedule, $type];
    }
}
