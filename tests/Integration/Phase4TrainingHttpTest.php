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
        $crawler = $this->client->request('GET', '/training/participant/' . $participant->getId());
        $this->client->submit($crawler->selectButton('Bestanden und Nachweis ausstellen')->form(), ['brevetNumber' => 'BREVET-GUEST-2026']);
        self::assertResponseRedirects();
        $participant = $this->em->find(CourseParticipant::class, $participant->getId());
        self::assertTrue($participant->hasPassed());
        self::assertNotNull($participant->getArchivedAt());
        self::assertSame('present', $participant->getTrainingArchive()['attendance'][0]['status']);
        self::assertCount(1, $participant->getTrainingArchive()['evidence']);
        self::assertSame(0, $this->em->getRepository(MemberQualification::class)->count([]));

        $crawler = $this->client->request('GET', '/training/course/' . $course->getId());
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
                $this->client->request('GET', $path);
                self::assertResponseStatusCodeSame(403);
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
        foreach (['/training', '/training/course/new', '/training/course/' . $course->getId() . '/edit', '/training/course/' . $course->getId() . '/unit', '/training/course/' . $course->getId() . '/unit-type'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
        }
        $crawler = $this->client->request('GET', '/training/course/' . $course->getId() . '/session');
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
        $crawler = $this->client->request('GET', '/training/course/new');
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

    private function payment(CourseParticipant $participant, string $amount, string $reference): void
    {
        $crawler = $this->client->request('GET', '/training/participant/' . $participant->getId());
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
        $member = (new Member())->setPerson($user->getPerson())->setClub($club ?? $this->club)->setUser($user)->setMembershipStatus(MembershipStatus::ACTIVE);
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
