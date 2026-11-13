<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SingleClubBootstrapHttpTest extends WebTestCase
{
    public function testSuperadminCanCreateOnlyTheFirstClubWithoutConfiguredId(): void
    {
        foreach ([
            'DATABASE_URL' => 'sqlite:///:memory:',
            'MAILER_DSN' => 'null://null',
            'PUBLIC_SITE_CLUB_ID' => '0',
        ] as $key => $value) {
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $client = static::createClient(['environment' => 'test']);
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $admin = (new User())->setUser('bootstrap-admin')->setEmail('bootstrap@example.invalid')
            ->setPassword('unused')->setIsVerified(true)->setRoles([User::ROLE_SUPER_ADMIN]);
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/de/club/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="Club"]')->form(['Club[name]' => 'Erster Verein']);
        $client->submit($form);
        self::assertResponseRedirects();
        self::assertSame(1, $em->getRepository(Club::class)->count([]));

        $client->request('GET', '/admin/de/club');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Erster Verein');
        $client->request('GET', '/admin/de/club/new');
        self::assertResponseStatusCodeSame(403);
    }
}
