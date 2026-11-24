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
            $this->assertPageSemantics($crawler);
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
        $this->assertPageSemantics($crawler);

        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();
        $this->assertPageSemantics($crawler);
        self::assertCount(1, $crawler->filter('#main-content[tabindex="-1"]'));
        self::assertCount(1, $crawler->filter('a.skip-link'));
    }

    private function assertPageSemantics(\Symfony\Component\DomCrawler\Crawler $crawler): void
    {
        self::assertSame('de', $crawler->filter('html')->attr('lang'));
        self::assertCount(1, $crawler->filter('#main-content'));
        self::assertCount(1, $crawler->filter('a.skip-link'));
        self::assertNotEmpty((string) $crawler->filter('title')->text());
        self::assertGreaterThan(0, $crawler->filter('h1')->count());
        self::assertGreaterThan(
            0,
            $crawler->filter('nav[aria-label], [role="navigation"][aria-label]')->count(),
            $crawler->getUri().' | '.$crawler->filter('h1')->first()->text(),
        );

        foreach ($crawler->filter('img') as $image) {
            self::assertTrue($image->hasAttribute('alt'), 'Images must have alternative text: '.$image->getAttribute('src'));
        }
        $ids = [];
        foreach ($crawler->filter('[id]') as $element) {
            $id = $element->getAttribute('id');
            self::assertArrayNotHasKey($id, $ids, 'Element IDs must be unique: '.$id);
            $ids[$id] = true;
        }
        foreach ($crawler->filter('a, button') as $control) {
            $name = trim($control->textContent);
            self::assertTrue(
                $name !== '' || $control->hasAttribute('aria-label') || $control->hasAttribute('title')
                || $this->hasNamedImage($control),
                'Links and buttons need an accessible name: '.$control->ownerDocument->saveHTML($control),
            );
        }
        foreach ($crawler->filter('input:not([type=hidden]), select, textarea') as $control) {
            $id = $control->getAttribute('id');
            self::assertTrue(
                $control->hasAttribute('aria-label')
                || $control->hasAttribute('aria-labelledby')
                || ($id !== '' && $crawler->filter('label[for="'.$id.'"]')->count() > 0),
                'Every form control needs an accessible label.',
            );
        }
    }

    private function hasNamedImage(\DOMElement $element): bool
    {
        foreach ($element->getElementsByTagName('img') as $image) {
            if ($image->hasAttribute('alt') && trim((string) $image->getAttribute('alt')) !== '') {
                return true;
            }
        }
        return false;
    }
}
