<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\CourseParticipant;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\MemberQualification;
use App\Entity\Person;
use App\Entity\Qualification;
use App\Entity\Schedule;
use App\Entity\TrainingUnitType;
use App\Entity\User;
use App\Enum\CourseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase6TrainingPortalHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;
    private User $manager;
    private Member $instructor;

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
        $this->club = (new Club())->setName('Ausbildungsverein');
        $this->em->persist($this->club);
        $this->em->flush();
        $this->manager = $this->account('manager', [User::ROLE_TRAINING_MANAGER]);
        $this->instructor = $this->account('instructor', [User::ROLE_INSTRUCTOR])->getMember();
    }

    public function testTrainingCourseFormUsesSharedThemeErrorAndActionMarkup(): void
    {
        $course = (new Courses())->setClub($this->club)->setTitle('Themenkurs')->setCategory('training');
        $course->setLeadInstructor($this->instructor);
        $this->em->persist($course);
        $this->em->flush();
        $this->client->loginUser($this->manager);

        $crawler = $this->client->request('GET', '/admin/training/course/'.$course->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form.dw-form');
        self::assertSelectorExists('.dw-form-card');
        self::assertSelectorExists('.dw-form-actions button.btn-primary');
        self::assertSelectorTextContains('.dw-form-heading', 'Pflichtfelder sind gekennzeichnet');
        self::assertSelectorExists('label.required');
        $this->client->submit($crawler->selectButton('Speichern')->form([
            'training_course[title]' => '',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback, ul.form-errors, .form-error-message');
    }

    public function testTrainingOverviewShowsApprovalsExamsAndQualificationAlerts(): void
    {
        $course = (new Courses())->setClub($this->club)->setTitle('Prüfungskurs')->setCategory('training')
            ->setStatus(CourseStatus::OPEN)->setLeadInstructor($this->instructor);
        $examType = (new TrainingUnitType())->setClub($this->club)->setCode('EX')->setName('Abschlussprüfung')
            ->setExamination(true);
        $schedule = (new Schedule())->setCourses($course)->setTitle('Praxisprüfung')
            ->setTrainingUnitType($examType)->setStartDate(new \DateTimeImmutable('+14 days'))
            ->setInstructor($this->instructor);
        $course->addSchedule($schedule);
        $participant = (new CourseParticipant())->setCourse($course)->setMember($this->instructor);
        $course->addParticipant($participant);
        $qualification = (new Qualification())->setClub($this->club)->setName('Medizinische Freigabe');
        $memberQualification = (new MemberQualification())->setMember($this->instructor)
            ->setQualification($qualification)->setValidUntil(new \DateTimeImmutable('+30 days'));
        foreach ([$course, $examType, $schedule, $participant, $qualification, $memberQualification] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        $this->client->loginUser($this->manager);
        $this->client->request('GET', '/admin/de/training-overview');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Terminpläne zur Freigabe');
        self::assertSelectorTextContains('body', 'Prüfungskurs');
        self::assertSelectorTextContains('body', 'Praxisprüfung');
        self::assertSelectorTextContains('body', '0 / 1');
        self::assertSelectorTextContains('body', 'Qualifikationen mit Prüfbedarf');
        self::assertSelectorTextContains('body', 'Läuft innerhalb von 90 Tagen ab');
        self::assertSelectorExists('a[href="/admin/training/timetable/'.$course->getId().'"]');
    }

    public function testInstructorDashboardCannotViewAnotherMembersCourseActions(): void
    {
        $otherInstructor = $this->account('other-instructor', [User::ROLE_INSTRUCTOR])->getMember();
        $course = (new Courses())->setClub($this->club)->setTitle('Fremder Kurs')->setCategory('training')
            ->setLeadInstructor($otherInstructor);
        $this->em->persist($course);
        $this->em->flush();
        $this->client->loginUser($this->account('viewer', [User::ROLE_INSTRUCTOR]));
        $this->client->request('GET', '/instructor-portal/courses');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Fremder Kurs');
        $this->client->request('GET', '/training/course/'.$course->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
    }

    private function account(string $name, array $roles): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Ausbildung');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')
            ->setMember($member)->setRoles([])->setIsVerified(true);
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
