<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PhaseARoleBoundaryHttpTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Club $club;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'CLUB_FUNCTION_MEMBERSHIP_ENABLED' => '0',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $this->client = static::createClient(['environment' => 'test']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->em))->createSchema($this->em->getMetadataFactory()->getAllMetadata());
        $this->club = (new Club())->setName('Boundary Club');
        $this->em->persist($this->club);
        $this->em->flush();
    }

    public function testBoardTreasurerAndAdminRemainSeparatedByCapability(): void
    {
        $board = $this->account('board', [User::ROLE_BOARD]);
        $treasurer = $this->account('treasurer', [User::ROLE_TREASURER]);
        $admin = $this->account('admin', [User::ROLE_ADMIN]);

        $this->client->loginUser($board);
        $this->client->request('GET', '/admin/membership');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/accounting');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($treasurer);
        $this->client->request('GET', '/admin/membership-finance');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/admin/membership');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/accounting');
        self::assertResponseIsSuccessful();
    }

    public function testBoardCanUseGovernanceAndCalendarWithoutLosingTheAdminNavigation(): void
    {
        $board = $this->account('board-navigation', [User::ROLE_BOARD]);
        $this->client->loginUser($board);

        $this->client->request('GET', '/admin/de/board-task');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorExists('a[href*="routeName=admin_governance"]');

        $this->client->request('GET', '/admin/calendar');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorExists('[data-competition-navigation]');
    }

    public function testDeniedAdminAccessShowsAnActionablePageWithTheAdminNavigation(): void
    {
        $treasurer = $this->account('treasurer-denied', [User::ROLE_TREASURER]);
        $this->client->loginUser($treasurer);

        $this->client->request('GET', '/admin/de/board-task');

        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('body', 'Für diesen Bereich fehlen die Zugriffsrechte');
        self::assertSelectorExists('aside.sidebar');
    }

    private function account(string $name, array $roles): User
    {
        $person = (new Person())->setFirstname(ucfirst($name))->setLastname('Tester');
        $member = (new Member())->setClub($this->club)->setPerson($person);
        $user = (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setRoles($roles)->setMember($member);
        $this->em->persist($person);
        $this->em->persist($member);
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }
}
