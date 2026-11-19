<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Command\CheckCourseReferencesCommand;
use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\Courses;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class CourseOrphanInstructorHttpTest extends WebTestCase
{
    public function testCourseIndexAndReferenceCheckSurviveMissingInstructor(): void
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
        $client = static::createClient(['environment' => 'test']);
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('Kursverein');
        $course = (new Courses())->setClub($club)->setTitle('Kaputte Altreferenz')->setCategory('training');
        $person = (new Person())->setFirstname('Admin')->setLastname('Test');
        $member = (new Member())->setClub($club)->setPerson($person);
        $admin = (new User())->setUser('admin')->setEmail('admin@example.invalid')->setPassword('unused')->setMember($member);
        $function = new ClubFunction($club, 'Administrator');
        $function->setPermissions([User::ROLE_ADMIN]);
        $em->persist($club);
        $em->persist($admin);
        $em->persist($function);
        $em->persist(new ClubFunctionAssignment($function, $admin));
        $em->persist($course);
        $em->flush();
        $em->getConnection()->executeStatement('PRAGMA foreign_keys = OFF');
        $em->getConnection()->executeStatement('UPDATE courses SET lead_instructor_id = 999999 WHERE id = ?', [$course->getId()]);
        $em->clear();

        $client->loginUser($em->find(User::class, $admin->getId()));
        $client->request('GET', '/admin/de/courses');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Kaputte Altreferenz');
        self::assertSelectorTextContains('table', '—');

        $check = new CommandTester(static::getContainer()->get(CheckCourseReferencesCommand::class));
        self::assertSame(Command::FAILURE, $check->execute([]));
        self::assertStringContainsString('Ausbilder-ID 999999', $check->getDisplay());
        self::assertSame(999999, (int) $em->getConnection()->fetchOne('SELECT lead_instructor_id FROM courses WHERE id = ?', [$course->getId()]));
    }
}
