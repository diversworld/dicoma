<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\ClubFunctionAssignment;
use App\Entity\Member;
use App\Entity\MembershipCharge;
use App\Entity\Person;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class Phase10FinanceAccessHttpTest extends WebTestCase
{
    public function testDirectTreasurerRoleShowsAllEntitledMenuEntriesWithoutFunctionModel(): void
    {
        if (!extension_loaded('pdo_sqlite')) { self::markTestSkipped('pdo_sqlite required.'); }
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '1',
            'CLUB_FUNCTION_MEMBERSHIP_ENABLED' => '0',
        ] as $name => $value) {
            $_ENV[$name] = $_SERVER[$name] = $value; putenv($name.'='.$value);
        }
        $client = static::createClient(['environment' => 'test']);
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('Direkte Kassenrolle');
        $treasurer = $this->account($club, 'direct-treasurer');
        $treasurer->setRoles([User::ROLE_TREASURER]);
        $em->persist($club);
        $em->persist($treasurer);
        $em->flush();
        $client->loginUser($treasurer);
        $client->request('GET', '/login');
        self::assertResponseRedirects('/admin');
        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/admin/membership-finance"]');
        self::assertSelectorExists('a[href*="routeName=admin_accounting"]');
        self::assertSelectorExists('a[href*="routeName=admin_settings_contributions"]');
    }

    public function testFinanceReadIsAvailableToTreasurerButNotOrdinaryMember(): void
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
        $club = (new Club())->setName('Finanzzugriff');
        $treasurer = $this->account($club, 'treasurer');
        $member = $this->account($club, 'member');
        $function = new ClubFunction($club, 'Kassenwart');
        $function->setModulePermissions(['finance:read']);
        $em->persist($club);
        $em->persist($treasurer);
        $em->persist($member);
        $em->persist($function);
        $em->persist(new ClubFunctionAssignment($function, $treasurer));
        for ($index = 1; $index <= 51; ++$index) {
            $em->persist(new MembershipCharge(
                $treasurer->getMember(),
                'pagination:'.$index,
                'Forderung '.$index,
                1000,
                new \DateTimeImmutable('2030-01-01'),
            ));
        }
        $em->flush();

        $client->loginUser($treasurer);
        $crawler = $client->request('GET', '/admin/membership-finance');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside.sidebar');
        self::assertSelectorExists('[data-training-staff-navigation]');
        self::assertSelectorExists('a[href*="routeName=admin_membership_finance"]');
        self::assertSelectorTextContains('body', 'IBAN, Mandatsnachweise, Kontaktdaten');
        $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-dashboard-finance]', 'Offene Forderungen');
        self::assertSelectorExists('a[href="/admin/membership-finance"]');
        $client->request('GET', '/admin/membership-finance');
        self::assertSelectorExists('a[href="/admin/membership-finance?page=2"]');
        $client->request('GET', '/admin/membership-finance?page=99');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('tbody', 'Forderung 51');
        $client->loginUser($member);
        $client->request('GET', '/admin/membership-finance');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/admin');
        self::assertSelectorNotExists('[data-dashboard-finance]');
    }

    private function account(Club $club, string $name): User
    {
        $person = (new Person())->setFirstname($name)->setLastname('Test')->setEmail($name.'@example.invalid');
        $member = (new Member())->setClub($club)->setPerson($person);
        return (new User())->setUser($name)->setEmail($name.'@example.invalid')->setPassword('unused')->setMember($member);
    }
}
