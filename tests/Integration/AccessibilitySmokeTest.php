<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use App\Entity\Club;
use App\Entity\Member;
use App\Entity\Person;
use App\Entity\User;

final class AccessibilitySmokeTest extends WebTestCase
{
    public function testPublicShellAndLoginHaveBaselineWcagSemantics(): void
    {
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $_ENV['MAILER_DSN'] = $_SERVER['MAILER_DSN'] = 'null://null';
        $client = static::createClient(['environment' => 'test']);
        $client->disableReboot();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $club = (new Club())->setName('Accessibility');
        $em->persist($club);
        $person = (new Person())->setFirstname('Accessible')->setLastname('Member')->setEmail('accessible@example.invalid');
        $member = (new Member())->setClub($club)->setPerson($person);
        $user = (new User())->setUser('accessible')->setEmail('accessible@example.invalid')->setPassword('unused')->setMember($member);
        $person2 = (new Person())->setFirstname('Admin')->setLastname('User');
        $member2 = (new Member())->setClub($club)->setPerson($person2);
        $admin = (new User())->setUser('accessible-admin')->setEmail('admin@example.invalid')->setPassword('unused')->setRoles([User::ROLE_ADMIN])->setMember($member2);
        foreach ([$person, $member, $user, $person2, $member2, $admin] as $entity) { $em->persist($entity); }
        $em->flush();
        foreach (['/', '/login'] as $path) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSame('de', $crawler->filter('html')->attr('lang'));
            self::assertCount(1, $crawler->filter('#main-content'));
            self::assertCount(1, $crawler->filter('a.skip-link'));
            self::assertNotEmpty((string) $crawler->filter('title')->text());
            foreach ($crawler->filter('img') as $image) {
                self::assertTrue($image->hasAttribute('alt'), 'Images must have alternative text.');
            }
        }
        $crawler = $client->request('GET', '/login');
        foreach ($crawler->filter('input:not([type=hidden])') as $input) {
            $id = $input->getAttribute('id');
            self::assertNotEmpty($id);
            self::assertGreaterThan(0, $crawler->filter('label[for="'.$id.'"]')->count(), 'Every form control needs a label.');
        }

        $client->loginUser($user);
        $crawler = $client->request('GET', '/my');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#main-content'));
        self::assertCount(1, $crawler->filter('nav[aria-label]'));

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#main-content[tabindex="-1"]'));
        self::assertCount(1, $crawler->filter('a.skip-link'));
    }
}
