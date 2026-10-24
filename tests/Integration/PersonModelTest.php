<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;

final class PersonModelTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite is required for isolated persistence tests.');
        }
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 2) . '/src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $this->em = EntityManager::create(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        $metadata = array_map($this->em->getClassMetadata(...), [Person::class, Club::class, User::class, Member::class]);
        (new SchemaTool($this->em))->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $this->em->getConnection()->close();
            $this->em->close();
        }
        parent::tearDown();
    }

    public function testGuestCanBecomeMemberWithoutDuplicatingIdentity(): void
    {
        $person = (new Person())->setFirstname('Guest')->setLastname('Diver')->setPostalCode('00123');
        $user = (new User())->setUser('guest')->setEmail('guest@example.invalid')->setPassword('test')->setPerson($person);
        $this->em->persist($user);
        $this->em->flush();
        $id = $person->getId();
        $club = (new Club())->setName('Testclub');
        $member = (new Member())->setPerson($person)->setClub($club)->setUser($user);
        $this->em->persist($club);
        $this->em->persist($member);
        $this->em->flush();
        $this->em->clear();
        $loaded = $this->em->find(User::class, $user->getId());
        self::assertSame($id, $loaded->getPerson()->getId());
        self::assertSame($loaded->getPerson(), $loaded->getMember()->getPerson());
        self::assertSame('00123', $loaded->getMember()->getPostalCode());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM person'));
    }

    public function testDeletingAccountPreservesMembershipAndIdentity(): void
    {
        $member = (new Member())->setFirstname('Keep')->setLastname('Identity');
        $user = (new User())->setUser('delete')->setEmail('delete@example.invalid')->setPassword('test')->setMember($member);
        $this->em->persist($user);
        $this->em->flush();
        $memberId = $member->getId();
        $personId = $member->getPerson()->getId();
        $this->em->remove($user);
        $this->em->flush();
        $this->em->clear();
        self::assertNotNull($this->em->find(Person::class, $personId));
        self::assertNull($this->em->find(Member::class, $memberId)->getUser());
    }

    public function testDeletingMembershipPreservesAccountAndIdentity(): void
    {
        $member = (new Member())->setFirstname('Keep')->setLastname('Account');
        $user = (new User())->setUser('keep')->setEmail('keep@example.invalid')->setPassword('test')->setMember($member);
        $this->em->persist($user);
        $this->em->flush();
        $id = $user->getId();
        $this->em->remove($member);
        $this->em->flush();
        $this->em->clear();
        $loaded = $this->em->find(User::class, $id);
        self::assertNotNull($loaded->getPerson());
        self::assertNull($loaded->getMember());
    }
}
