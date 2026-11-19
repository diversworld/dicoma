<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\Schedule;
use App\Entity\User;
use App\Enum\BookingAttendanceStatus;
use App\Enum\CourseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CourseAttendanceFlowHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

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
        $this->club = (new Club())->setName('Kursanwesenheit');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testCourseRegistrationAutomaticallyAssignsEveryScheduleAndOldBookingRouteIsGone(): void
    {
        $student = $this->account('student', []);
        $instructor = $this->account('instructor', [User::ROLE_INSTRUCTOR]);
        $course = (new Courses())->setClub($this->club)->setTitle('Gesamtkurs')->setCategory('training')
            ->setStatus(CourseStatus::OPEN)->setLeadInstructor($instructor->getMember());
        $first = (new Schedule())->setCourses($course)->setTitle('Termin eins')->setStartDate(new \DateTimeImmutable('+7 days'));
        $second = (new Schedule())->setCourses($course)->setTitle('Termin zwei')->setStartDate(new \DateTimeImmutable('+14 days'));
        $course->addSchedule($first);
        $course->addSchedule($second);
        foreach ([$course, $first, $second] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();

        $this->client->loginUser($student);
        $crawler = $this->client->request('GET', '/courses/');
        $token = $crawler->filter('form[action="/my-training/courses/'.$course->getId().'/register"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/my-training/courses/'.$course->getId().'/register', ['_token' => $token]);
        self::assertResponseRedirects();
        $participant = $this->em->getRepository(CourseParticipant::class)->findOneBy([
            'course' => $course, 'person' => $student->getPerson(),
        ]);
        self::assertNotNull($participant);
        self::assertSame(2, $this->em->getRepository(Booking::class)->count(['courseParticipant' => $participant]));

        $this->client->request('POST', '/booking/book/'.$first->getId(), ['_token' => 'ignored']);
        self::assertResponseStatusCodeSame(410);
        $this->client->request('GET', '/courses/'.$course->getId());
        self::assertSelectorTextContains('body', 'Mit Kursanmeldung eingeschlossen');
        self::assertSelectorTextNotContains('body', 'Termin buchen');
    }

    public function testParticipantExcuseIsReviewedByInstructorAndForeignParticipantCannotSubmitIt(): void
    {
        $student = $this->account('student', []);
        $foreign = $this->account('foreign', []);
        $instructor = $this->account('instructor', [User::ROLE_INSTRUCTOR]);
        $course = (new Courses())->setClub($this->club)->setTitle('Prüfkurs')->setCategory('training')
            ->setStatus(CourseStatus::OPEN)->setLeadInstructor($instructor->getMember());
        $schedule = (new Schedule())->setCourses($course)->setTitle('Theorietermin')
            ->setStartDate(new \DateTimeImmutable('+7 days'))->setInstructor($instructor->getMember());
        $course->addSchedule($schedule);
        $participant = (new CourseParticipant())->setCourse($course)->setMember($student->getMember());
        $participant->setPerson($student->getPerson());
        $course->addParticipant($participant);
        $this->em->persist($course);
        $this->em->persist($schedule);
        $this->em->persist($participant);
        $this->em->flush();
        $sync = static::getContainer()->get(\App\Service\ScheduleParticipantSyncService::class);
        self::assertSame(1, $sync->sync($schedule));
        $booking = $this->em->getRepository(Booking::class)->findOneBy(['courseParticipant' => $participant]);

        $this->client->loginUser($foreign);
        $crawler = $this->client->request('GET', '/my-training/attendance');
        $token = $crawler->filter('form[action="/my-training/attendance/'.$booking->getId().'/excuse"] input[name="_token"]')->count()
            ? $crawler->filter('form[action="/my-training/attendance/'.$booking->getId().'/excuse"] input[name="_token"]')->attr('value')
            : 'invalid';
        $this->client->request('POST', '/my-training/attendance/'.$booking->getId().'/excuse', [
            '_token' => $token, 'reason' => 'Fremde Eingabe',
        ]);
        self::assertResponseStatusCodeSame(404);

        $this->client->loginUser($student);
        $crawler = $this->client->request('GET', '/my-training/attendance');
        $token = $crawler->filter('form[action="/my-training/attendance/'.$booking->getId().'/excuse"] input[name="_token"]')->attr('value');
        $this->client->request('POST', '/my-training/attendance/'.$booking->getId().'/excuse', [
            '_token' => $token, 'reason' => 'Dienstreise',
        ]);
        self::assertResponseRedirects('/my-training/attendance', 303);
        $this->client->request('GET', '/my-training/attendance');
        self::assertSelectorTextContains('body', 'An den Ausbilder gemeldet');

        $this->client->loginUser($instructor);
        $this->client->request('GET', '/my-training/assess/'.$schedule->getId());
        self::assertSelectorTextContains('body', 'Dienstreise');
        $crawler = $this->client->request('GET', '/my-training/assess/'.$schedule->getId());
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/my-training/assess/'.$schedule->getId(), [
            '_token' => $token,
            'excuse_decision' => [(string) $participant->getId() => 'makeup_required'],
            'status' => [(string) $participant->getId() => ''],
            'attendance' => [(string) $participant->getId() => ''],
        ]);
        self::assertResponseRedirects();
        $this->em->clear();
        $booking = $this->em->getRepository(Booking::class)->findOneBy(['courseParticipant' => $participant->getId()]);
        self::assertSame(BookingAttendanceStatus::MAKEUP_REQUIRED, $booking->getAttendanceStatus());
        self::assertSame('makeup_required', $booking->getExcuseDecision());
        self::assertNotNull($booking->getExcuseReviewedAt());
        self::assertSame($instructor->getMember()->getId(), $booking->getExcuseReviewedBy()?->getId());
    }

    private function account(string $name, array $roles): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Teilnehmer')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setMember($member)->setIsVerified(true);
        $this->em->persist($user);
        if ($roles !== []) {
            $function = new ClubFunction($this->club, 'Testfunktion '.$name);
            $function->setPermissions($roles);
            $this->em->persist($function);
            $this->em->persist(new ClubFunctionAssignment($function, $user));
        }
        $this->em->flush();
        return $user;
    }
}
