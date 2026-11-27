<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\ClubFunction;
use App\Entity\Member;
use App\Entity\User;
use App\Security\BootstrapRegistrationLock;
use App\Security\InstallationGuard;
use App\Service\InstallationBootstrap;
use App\Service\InstallationCatalog;
use App\Service\InstallationEnvironment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Filesystem\Filesystem;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class InstallationCatalogTest extends WebTestCase
{
    public function testCatalogInstallsMissingCoreFeaturesIdempotently(): void
    {
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'PUBLIC_SITE_CLUB_ID' => '0',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        self::bootKernel(['environment' => 'test']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('Testverein')->setActive(true);
        $em->persist($club);
        $em->flush();

        $catalog = self::getContainer()->get(InstallationCatalog::class);
        self::assertSame(16, count($catalog->components($club)));
        self::assertNotEmpty(array_filter($catalog->components($club), static fn (array $item): bool => !$item['installed']));

        $catalog->installMissing($club);
        $em->flush();
        self::assertSame([], array_values(array_filter($catalog->components($club), static fn (array $item): bool => !$item['installed'])));
        self::assertSame([], $catalog->installMissing($club));
        self::assertGreaterThanOrEqual(12, $em->getRepository(ClubFunction::class)->count(['club' => $club]));
    }

    public function testFreshBootstrapCreatesClubAdministratorMemberAndCoreFeatures(): void
    {
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'PUBLIC_SITE_CLUB_ID' => '0',
            'DICOMA_INSTALL_ENABLED' => '1',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        unset($_ENV['DICOMA_INSTALL_COMPLETED'], $_SERVER['DICOMA_INSTALL_COMPLETED']);
        putenv('DICOMA_INSTALL_COMPLETED');
        self::bootKernel(['environment' => 'test']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $directory = sys_get_temp_dir().'/dicoma-install-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700, true);
        $bootstrap = new InstallationBootstrap(
            $em,
            self::getContainer()->get(InstallationCatalog::class),
            new InstallationGuard($directory),
            new BootstrapRegistrationLock($directory),
            self::getContainer()->get(UserPasswordHasherInterface::class),
            new InstallationEnvironment($directory, new Filesystem()),
        );

        $result = $bootstrap->install([
            'clubName' => 'Neuer Verein',
            'clubShortName' => 'NV',
            'clubEmail' => 'verein@example.invalid',
            'username' => 'superadmin',
            'firstname' => 'Admin',
            'lastname' => 'Verein',
            'email' => 'admin@example.invalid',
            'password' => 'Sicheres-Passwort-2026!',
        ]);

        self::assertSame('Neuer Verein', $result['club']->getName());
        self::assertSame([User::ROLE_SUPER_ADMIN], $result['user']->getStoredRoles());
        self::assertSame(1, $em->getRepository(Club::class)->count([]));
        self::assertSame(1, $em->getRepository(User::class)->count([]));
        self::assertSame(1, $em->getRepository(Member::class)->count([]));
        self::assertFileExists($directory.'/var/install-completed.marker');
        $env = (string) file_get_contents($directory.'/.env.local');
        self::assertStringContainsString('SITE_THEME=airspace', $env);
        self::assertStringContainsString('PUBLIC_SITE_CLUB_ID=1', $env);
        self::assertStringContainsString('DICOMA_INSTALL_ENABLED=1', $env);
        self::assertStringContainsString('DICOMA_INSTALL_COMPLETED=1', $env);
        self::assertMatchesRegularExpression('/^DICOMA_MEMBERSHIP_BANK_KEY="[A-Za-z0-9+\/]{43}="$' . '/m', $env);
        self::assertSame([], array_values(array_filter(
            self::getContainer()->get(InstallationCatalog::class)->components($result['club']),
            static fn (array $item): bool => !$item['installed'],
        )));

        unlink($directory.'/var/install-completed.marker');
        unlink($directory.'/.env.local');
        unlink($directory.'/var/lock/dicoma-bootstrap.lock');
        rmdir($directory.'/var/lock');
        rmdir($directory.'/var');
        rmdir($directory);
    }

    public function testFeatureUpdaterIsAvailableOnlyAfterAuthentication(): void
    {
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'PUBLIC_SITE_CLUB_ID' => '0',
            'DICOMA_INSTALL_ENABLED' => '0',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $client = static::createClient(['environment' => 'test']);
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('Updateverein')->setActive(true);
        $admin = (new User())->setUser('update-admin')->setEmail('update@example.invalid')
            ->setPassword('unused')->setIsVerified(true)->setRoles([User::ROLE_SUPER_ADMIN]);
        $em->persist($club);
        $em->persist($admin);
        $em->flush();

        $client->request('GET', '/install');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/admin/installation');
        self::assertResponseRedirects('/login');

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/installation');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Installation und Funktionsprüfung');
        $client->submit($crawler->selectButton('Alle fehlenden Funktionen installieren')->form());
        self::assertResponseRedirects('/admin/installation');
        self::assertSame([], array_values(array_filter(
            static::getContainer()->get(InstallationCatalog::class)->components($club),
            static fn (array $item): bool => !$item['installed'],
        )));
    }
}
