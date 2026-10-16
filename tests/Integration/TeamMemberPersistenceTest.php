<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Controller\Admin\TeamMemberCrudController;
use App\Entity\Club;
use App\Entity\Member;
use App\Entity\MemberSport;
use App\Entity\Sport;
use App\Entity\Team;
use App\Entity\TeamMember;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

/**
 * Integration tests for the real CRUD persistence path, never production DB.
 * Requires pdo_sqlite and Symfony's test kernel.
 */
final class TeamMemberPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TeamMemberCrudController $controller;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for isolated integration tests.');
        }

        // Fail closed: override any production DATABASE_URL before kernel boot.
        $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'test';
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL=sqlite:///:memory:');

        self::bootKernel(['environment' => 'test']);
		
		$this->controller = static::getContainer()->get(
			TeamMemberCrudController::class
		);
		
        $container = static::getContainer();
        
		$this->em = $container->get(EntityManagerInterface::class);
        
		if ($this->em->getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\SqlitePlatform === false) {
            throw new \RuntimeException('Refusing to run tests on a non-SQLite database.');
        }
        
		$this->em->getConnection()->executeStatement('PRAGMA foreign_keys = OFF');

        $classes = [Club::class, Member::class, Sport::class, Team::class, MemberSport::class, TeamMember::class];
        
		$metadata = array_map(fn (string $class) => $this->em->getClassMetadata($class), $classes);
		
        (new SchemaTool($this->em))->createSchema($metadata);

        $controller = static::getContainer()->get(
			\App\Controller\Admin\TeamMemberCrudController::class
		);
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->close();
        }
        parent::tearDown();
    }

    /** @return array{TeamMember, Member, Sport} */
    private function fixture(bool $existingSport = false, bool $differentClub = false): array
    {
        $club = (new Club())->setName('Testclub');
        $sport = (new Sport())->setName('Testsport')->setClub($club);
        $team = (new Team())->setName('Testteam')->setSport($sport);
        $memberClub = $differentClub ? (new Club())->setName('Other club') : $club;
        $member = (new Member())
            ->setClub($memberClub)
            ->setFirstname('Test')
            ->setLastname('Person')
            ->setBirthday(new \DateTime('2000-01-01'));
        $this->em->persist($club);
        
		if ($differentClub) {
            $this->em->persist($memberClub);
        }
		
        $this->em->persist($sport);
        $this->em->persist($team);
        $this->em->persist($member);
        
		if ($existingSport) {
            $membership = (new MemberSport())->setSport($sport);
            $member->addSport($membership);
            $this->em->persist($membership);
        }
        
		$this->em->flush();
        
		return [(new TeamMember())->setMember($member)->setTeam($team), $member, $sport];
    }

    private function countRows(string $table): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM ' . $table);
    }

    public function testConsentCreatesBothRecords(): void
    {
        [$assignment] = $this->fixture();
        $assignment->setAssignMissingSport(true);
        $this->controller->persistEntity($this->em, $assignment);
        self::assertSame(1, $this->countRows('team_member'));
        self::assertSame(1, $this->countRows('member_sport'));
    }

    public function testNoConsentDoesNotPersistAnything(): void
    {
        [$assignment] = $this->fixture();
        try {
            $this->controller->persistEntity($this->em, $assignment);
            self::fail('Missing sport without consent should be rejected.');
        } catch (\LogicException $e) {
            self::assertSame(0, $this->countRows('team_member'));
            self::assertSame(0, $this->countRows('member_sport'));
        }
    }

    public function testExistingSportDoesNotDuplicateMembership(): void
    {
        [$assignment] = $this->fixture(existingSport: true);
        $assignment->setAssignMissingSport(true);
        $this->controller->persistEntity($this->em, $assignment);
        self::assertSame(1, $this->countRows('team_member'));
        self::assertSame(1, $this->countRows('member_sport'));
    }

    public function testDifferentClubRejectsBothWrites(): void
    {
        [$assignment] = $this->fixture(differentClub: true);
        $assignment->setAssignMissingSport(true);
        try {
            $this->controller->persistEntity($this->em, $assignment);
            self::fail('Cross-club membership should be rejected.');
        } catch (\LogicException $e) {
            self::assertSame(0, $this->countRows('team_member'));
            self::assertSame(0, $this->countRows('member_sport'));
        }
    }
}
